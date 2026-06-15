<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemModification;
use App\Models\PosShiftSession;
use App\Models\PosShiftStockItem;
use App\Models\PosShiftStockMovement;
use App\Models\Store;
use App\Models\Product;
use App\Models\ProductModification;
use App\Models\ProductVariant;
use App\Models\ProductVariantCombination;
use App\Models\Refund;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

class OrderController extends Controller
{
    /**
     * Create a new order
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $userKey = (string) ($user->uuid ?: $user->id);
        
        $request->validate([
            'store_id' => 'required|exists:stores,id',
            'customer_name' => 'nullable|string|max:255',
            'table_code' => 'nullable|string|max:50',
            'status' => 'required|in:pending,completed,cancelled,refunded',
            'subtotal' => 'required|numeric|min:0',
            'discount_total' => 'required|numeric|min:0',
            'tax_total' => 'required|numeric|min:0',
            'service_total' => 'required|numeric|min:0',
            'grand_total' => 'required|numeric|min:0',
            'payment_method_id' => 'nullable|exists:payment_methods,id',
            'source' => 'required|in:pos,web-order',
            'customer_id' => 'nullable|exists:customers,id',
            'order_type' => 'nullable|in:dine-in,takeaway,delivery',
            'table_id' => 'nullable|exists:tables,id',
            'customer_type_id' => 'nullable|exists:customer_types,id',
            'order_items' => 'required|array',
            'order_items.*.product_id' => 'nullable|exists:products,id',
            'order_items.*.product_variant_id' => 'nullable|exists:product_variants,id',
            'order_items.*.variant_combination_id' => 'nullable|exists:product_variant_combinations,id',
            'order_items.*.quantity' => 'required|integer|min:1',
            'order_items.*.unit_price' => 'required|numeric|min:0',
            'order_items.*.total_price' => 'required|numeric|min:0',
            'order_items.*.name' => 'nullable|string',
            'order_items.*.product' => 'nullable|array',
            'order_items.*.variant' => 'nullable',
            'order_items.*.modifications' => 'nullable|array',
            'order_items.*.notes' => 'nullable|string',
            'is_offline' => 'boolean',
            'device_identifier' => 'nullable|string|max:255',
            'proof_of_payment' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
        ]);

        // Verify that the store exists
        // TODO: Add proper cross-tenant access control via user_stores table
        $store = Store::where('id', $request->store_id)->first();
        
        if (!$store) {
            return response()->json([
                'success' => false,
                'message' => 'Store not found'
            ], 404);
        }

        try {
            DB::beginTransaction();

            $proofOfPaymentPath = null;
            if ($request->hasFile('proof_of_payment')) {
                $file = $request->file('proof_of_payment');
                $path = $file->store('proof-of-payments', 'public');
                $proofOfPaymentPath = $path;
            }

            $paymentSnapshot = null;
            if ($request->payment_method_id) {
                $paymentMethod = \App\Models\PaymentMethod::find($request->payment_method_id);
                if ($paymentMethod) {
                    $paymentSnapshot = $paymentMethod->toArray();
                }
            }

            $customerTypeSnapshot = null;
            if ($request->customer_type_id) {
                $customerType = \App\Models\CustomerType::find($request->customer_type_id);
                if ($customerType) {
                    $customerTypeSnapshot = $customerType->toArray();
                }
            }

            // Auto-set paid_at and payment_status if order has payment method
            $paidAt = null;
            $paymentStatus = 'pending';
            
            if ($request->payment_method_id && $request->status === 'completed') {
                $paidAt = now();
                $paymentStatus = 'paid';
            }

            $activeShift = PosShiftSession::query()
                ->where('store_id', $request->store_id)
                ->where('status', PosShiftSession::STATUS_OPEN)
                ->lockForUpdate()
                ->first();

            if (! $activeShift) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Open shift is required before creating POS orders.',
                ], 422);
            }

            if ($activeShift->business_date && $activeShift->business_date->toDateString() < now()->toDateString()) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Previous business date shift must be closed before creating new orders.',
                    'data' => $activeShift,
                ], 409);
            }

            $trackedConsumption = $this->collectTrackedStockConsumption($request->order_items);
            $this->assertShiftStockAvailable($activeShift, $trackedConsumption);

            $order = Order::create([
                'tenant_id' => $store->tenant_id, // Use store's tenant_id for cross-tenant support
                'store_id' => $request->store_id,
                'shift_session_id' => $activeShift->id,
                'created_by' => $userKey,
                'customer_id' => $request->customer_id,
                'customer_name' => $request->customer_name,
                'table_code' => $request->table_code,
                'status' => $request->status,
                'payment_status' => $paymentStatus,
                'paid_at' => $paidAt,
                'subtotal' => $request->subtotal,
                'discount_total' => $request->discount_total,
                'tax_total' => $request->tax_total,
                'service_total' => $request->service_total,
                'grand_total' => $request->grand_total,
                'payment_method' => $request->payment_method, // Save payment method name
                'payment_snapshot' => $paymentSnapshot,
                'source' => $request->source,
                'is_offline' => $request->is_offline ?? false,
                'device_identifier' => $request->device_identifier,
                'proof_of_payment' => $proofOfPaymentPath,
                'order_type' => $request->order_type,
                'table_id' => $request->table_id,
                'customer_type_id' => $request->customer_type_id,
                'customer_type_snapshot' => $customerTypeSnapshot,
            ]);

            // Create order items with JSON snapshots
            foreach ($request->order_items as $itemData) {
                if ($request->customer_type_id && !empty($itemData['product_id'])) {
                    $channelPrice = $this->resolveChannelPrice(
                        $request->store_id,
                        $itemData['product_id'],
                        $request->customer_type_id
                    );

                    if ($channelPrice !== null) {
                        $variantTotal = $this->sumVariantPriceAdjustments($itemData);

                        $modificationsTotal = collect($itemData['modifications'] ?? [])
                            ->sum(fn ($modification) => (float) ($modification['price'] ?? 0));
                        $itemData['unit_price'] = (float) $channelPrice + $variantTotal + $modificationsTotal;
                        $itemData['total_price'] = $itemData['unit_price'] * (int) $itemData['quantity'];

                        if (isset($itemData['product']) && is_array($itemData['product'])) {
                            $itemData['product']['price'] = (float) $channelPrice;
                            $itemData['product']['customer_type_price'] = (float) $channelPrice;
                        }
                    }
                }

                // Create snapshots from the request data
                $productSnapshot = $itemData['product'] ?? null;
                $variantSnapshot = isset($itemData['variant']) ? $itemData['variant'] : null;
                $modificationsSnapshot = isset($itemData['modifications']) && is_array($itemData['modifications']) 
                    ? $itemData['modifications'] 
                    : [];

                $orderItem = $order->orderItems()->create([
                    'store_id' => $request->store_id, // Add store_id for proper data segregation
                    'quantity' => $itemData['quantity'],
                    'unit_price' => $itemData['unit_price'],
                    'total_price' => $itemData['total_price'],
                    'notes' => $itemData['notes'] ?? null,
                    'product_snapshot' => $productSnapshot,
                    'variant_snapshot' => $variantSnapshot,
                    'modifications_snapshot' => $modificationsSnapshot,
                ]);

                if (!in_array($request->status, ['cancelled', 'refunded'], true)) {
                    $this->deductStockForOrderItem($itemData, (int) $itemData['quantity']);
                    $this->recordShiftSaleForOrderItem($activeShift, $order, $orderItem, $itemData, (int) $itemData['quantity'], $userKey);
                }
            }

            // Create OrderPayment record
            if ($request->payment_type_id) {
                $order->payments()->create([
                    'amount' => $request->grand_total,
                    'payment_type_id' => $request->payment_type_id,
                    'status' => $paymentStatus,
                    'captured_at' => $paidAt,
                    'is_offline' => $request->is_offline ?? false,
                ]);
            }

            if ($request->order_type === 'dine-in' && $request->table_id && $request->status === 'completed') {
                \App\Models\Table::where('id', $request->table_id)
                    ->where('store_id', $request->store_id)
                    ->update(['is_available' => true]);
            }

            // Update inventory (simplified);

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $order->load(['orderItems'])
            ], 201);
        } catch (\Illuminate\Http\Exceptions\HttpResponseException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Exception $e) {
            DB::rollBack();
            \Illuminate\Support\Facades\Log::error('Order creation failed: ' . $e->getMessage());
            \Illuminate\Support\Facades\Log::error($e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => 'Failed to create order: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine()
            ], 500);
        }
    }

    private function deductStockForOrderItem(array $itemData, int $orderQuantity): void
    {
        if (!empty($itemData['product_id'])) {
            $product = Product::withoutGlobalScope('tenant')
                ->with(['bundleItems.componentProduct'])
                ->lockForUpdate()
                ->find($itemData['product_id']);

            if ($product) {
                if (($product->type ?: 'single') === 'bundle') {
                    foreach ($product->bundleItems as $bundleItem) {
                        $this->decrementTrackedProductStock(
                            (string) $bundleItem->component_product_id,
                            max(1, (int) $bundleItem->quantity) * $orderQuantity
                        );
                    }
                } else {
                    $this->decrementTrackedProductStock((string) $product->id, $orderQuantity);
                }
            }
        }

        foreach (($itemData['modifications'] ?? []) as $modificationData) {
            $modificationId = $modificationData['product_modification_id'] ?? $modificationData['id'] ?? null;
            if (!$modificationId) {
                continue;
            }

            $modification = ProductModification::withoutGlobalScope('tenant')
                ->with('linkedProduct')
                ->find($modificationId);

            if (!$modification?->linked_product_id) {
                continue;
            }

            $modificationQuantity = max(1, (int) ($modificationData['quantity'] ?? 1));
            $linkedQuantity = max(1, (int) ($modification->linked_product_quantity ?? 1));

            $this->decrementTrackedProductStock(
                (string) $modification->linked_product_id,
                $orderQuantity * $modificationQuantity * $linkedQuantity
            );
        }
    }

    private function collectTrackedStockConsumption(array $orderItems): array
    {
        $consumption = [];

        foreach ($orderItems as $itemData) {
            $quantity = max(1, (int) ($itemData['quantity'] ?? 1));

            foreach ($this->trackedProductsForOrderItem($itemData, $quantity) as $productId => $trackedQuantity) {
                $consumption[$productId] = ($consumption[$productId] ?? 0) + $trackedQuantity;
            }
        }

        return $consumption;
    }

    private function sumVariantPriceAdjustments(array $itemData): float
    {
        $variantSnapshot = $itemData['variant'] ?? [];

        if ($variantSnapshot && ! array_is_list($variantSnapshot)) {
            $variantSnapshot = [$variantSnapshot];
        }

        $variantTotal = collect($variantSnapshot)
            ->filter(fn ($variant) => is_array($variant))
            ->sum(fn ($variant) => (float) ($variant['price'] ?? 0));

        if ($variantTotal > 0) {
            return (float) $variantTotal;
        }

        if (! empty($itemData['product_variant_id'])) {
            return (float) (ProductVariant::withoutGlobalScope('tenant')
                ->where('id', $itemData['product_variant_id'])
                ->value('price') ?? 0);
        }

        return 0.0;
    }

    private function trackedProductsForOrderItem(array $itemData, int $orderQuantity): array
    {
        $tracked = [];

        if (!empty($itemData['product_id'])) {
            $product = Product::withoutGlobalScope('tenant')
                ->with(['bundleItems.componentProduct'])
                ->find($itemData['product_id']);

            if ($product) {
                if (($product->type ?: 'single') === 'bundle') {
                    foreach ($product->bundleItems as $bundleItem) {
                        $component = $bundleItem->componentProduct;
                        if ($component?->remaining) {
                            $productId = (string) $bundleItem->component_product_id;
                            $tracked[$productId] = ($tracked[$productId] ?? 0)
                                + max(1, (int) $bundleItem->quantity) * $orderQuantity;
                        }
                    }
                } elseif ($product->remaining) {
                    $productId = (string) $product->id;
                    $tracked[$productId] = ($tracked[$productId] ?? 0) + $orderQuantity;
                }
            }
        }

        foreach (($itemData['modifications'] ?? []) as $modificationData) {
            $modificationId = $modificationData['product_modification_id'] ?? $modificationData['id'] ?? null;
            if (!$modificationId) {
                continue;
            }

            $modification = ProductModification::withoutGlobalScope('tenant')
                ->with('linkedProduct')
                ->find($modificationId);

            if (!$modification?->linked_product_id || !$modification->linkedProduct?->remaining) {
                continue;
            }

            $modificationQuantity = max(1, (int) ($modificationData['quantity'] ?? 1));
            $linkedQuantity = max(1, (int) ($modification->linked_product_quantity ?? 1));
            $productId = (string) $modification->linked_product_id;
            $tracked[$productId] = ($tracked[$productId] ?? 0)
                + $orderQuantity * $modificationQuantity * $linkedQuantity;
        }

        return $tracked;
    }

    private function assertShiftStockAvailable(PosShiftSession $shift, array $consumption): void
    {
        foreach ($consumption as $productId => $quantity) {
            $item = PosShiftStockItem::where('shift_session_id', $shift->id)
                ->where('product_id', $productId)
                ->lockForUpdate()
                ->first();

            if (!$item) {
                abort(response()->json([
                    'success' => false,
                    'message' => 'Tracked product is missing from active shift stock.',
                    'errors' => ['product_id' => [$productId]],
                ], 422));
            }

            $item->recalculateExpected();
            if ((int) $item->expected_closing_stock < (int) $quantity) {
                abort(response()->json([
                    'success' => false,
                    'message' => 'Insufficient shift stock for tracked product.',
                    'errors' => ['product_id' => [$productId]],
                ], 422));
            }
        }
    }

    private function recordShiftSaleForOrderItem(
        PosShiftSession $shift,
        Order $order,
        OrderItem $orderItem,
        array $itemData,
        int $orderQuantity,
        string $userKey
    ): void {
        foreach ($this->trackedProductsForOrderItem($itemData, $orderQuantity) as $productId => $quantity) {
            $item = PosShiftStockItem::where('shift_session_id', $shift->id)
                ->where('product_id', $productId)
                ->lockForUpdate()
                ->first();

            if (!$item) {
                continue;
            }

            $item->sold_quantity = (int) $item->sold_quantity + (int) $quantity;
            $item->recalculateExpected();
            $item->save();

            PosShiftStockMovement::create([
                'shift_session_id' => $shift->id,
                'product_id' => $productId,
                'order_id' => $order->id,
                'order_item_id' => $orderItem->id,
                'type' => PosShiftStockMovement::TYPE_SALE,
                'quantity' => -1 * (int) $quantity,
                'note' => 'Order sale',
                'created_by_user_id' => $userKey,
            ]);
        }
    }

    private function decrementTrackedProductStock(string $productId, int $quantity): void
    {
        if ($quantity < 1) {
            return;
        }

        $product = Product::withoutGlobalScope('tenant')
            ->lockForUpdate()
            ->find($productId);

        if (!$product || !$product->remaining) {
            return;
        }

        $product->update([
            'stock' => max(0, (int) $product->stock - $quantity),
        ]);
    }

    /**
     * Get orders for the authenticated user
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        
        // Bypass TenantScope to support cross-tenant access via store_id
        $query = Order::withoutGlobalScope('tenant')
            ->with([
                'store',
                'orderItems',
                'refunds' => fn ($query) => $query
                    ->withoutGlobalScope('tenant')
                    ->with('refundItems.orderItem')
                    ->where('status', Refund::STATUS_PENDING),
            ])
            ->orderBy('created_at', 'desc');

        // Add optional filters
        if ($request->has('store_id')) {
            $query->where('store_id', $request->store_id);
        }
        
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        
        if ($request->has('source')) {
            $query->where('source', $request->source);
        }
        
        // Log debug info
        \Log::info('OrderController::index', [
            'user_id' => $user->uuid ?: $user->id,
            'user_tenant_id' => $user->tenant_id,
            'request_store_id' => $request->store_id,
        ]);

        $orders = $query->get()
            ->map(fn (Order $order) => $this->appendRefundSummary($order))
            ->values();

        \Log::info('Order Query Result', [
            'count' => $orders->count(),
            'sql' => $query->toSql(),
            'bindings' => $query->getBindings(),
        ]);

        return response()->json([
            'success' => true,
            'data' => $orders
        ]);
    }

    /**
     * Get a specific order
     */
    public function show(Request $request, string $orderId): JsonResponse
    {
        $user = $request->user();
        
        // Bypass TenantScope to support cross-tenant access
        $order = Order::withoutGlobalScope('tenant')
            ->where('id', $orderId)
            ->with([
                'orderItems',
                'store',
                'payments',
                'customer',
                'refunds' => fn ($query) => $query
                    ->withoutGlobalScope('tenant')
                    ->with('refundItems.orderItem')
                    ->where('status', Refund::STATUS_PENDING),
            ])
            ->first();

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->appendRefundSummary($order)
        ]);
    }

    /**
     * Update an order
     */
    public function update(Request $request, string $orderId): JsonResponse
    {
        $user = $request->user();
        
        $order = Order::withoutGlobalScope('tenant')
            ->where('id', $orderId)
            ->first();

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found'
            ], 404);
        }

        $request->validate([
            'status' => 'sometimes|in:pending,completed,cancelled,refunded',
            'paid_at' => 'nullable|date',
            'payment_method_id' => 'nullable|exists:payment_methods,id',
            'payment_method' => 'nullable|string|max:255',
        ]);

        $updates = $request->only(['status', 'paid_at']);

        $paymentMethod = null;
        if ($request->filled('payment_method_id')) {
            $paymentMethod = \App\Models\PaymentMethod::where('id', $request->payment_method_id)
                ->where('store_id', $order->store_id)
                ->firstOrFail();

            $updates['payment_method'] = $paymentMethod->name;
            $updates['payment_snapshot'] = $paymentMethod->toArray();
            $updates['paid_at'] = $updates['paid_at'] ?? now();
            $updates['status'] = $updates['status'] ?? 'completed';

            if (Schema::hasColumn('orders', 'payment_status')) {
                $updates['payment_status'] = 'paid';
            }
        } elseif ($request->filled('payment_method')) {
            $updates['payment_method'] = $request->payment_method;
            $updates['paid_at'] = $updates['paid_at'] ?? now();
            $updates['status'] = $updates['status'] ?? 'completed';

            if (Schema::hasColumn('orders', 'payment_status')) {
                $updates['payment_status'] = 'paid';
            }
        }

        $order->update($updates);

        if ($paymentMethod && Schema::hasTable('order_payments')) {
            $paymentData = [
                'order_id' => $order->id,
                'amount' => $order->grand_total,
            ];

            if (Schema::hasColumn('order_payments', 'tenant_id')) {
                $paymentData['tenant_id'] = $order->tenant_id;
            }
            if (Schema::hasColumn('order_payments', 'payment_method')) {
                $paymentData['payment_method'] = $paymentMethod->name;
            }
            if (Schema::hasColumn('order_payments', 'payment_type_id') && $order->payment_type_id) {
                $paymentData['payment_type_id'] = $order->payment_type_id;
            }
            if (Schema::hasColumn('order_payments', 'status')) {
                $paymentData['status'] = 'paid';
            }
            if (Schema::hasColumn('order_payments', 'captured_at')) {
                $paymentData['captured_at'] = $order->paid_at ?? now();
            }
            if (Schema::hasColumn('order_payments', 'paid_at')) {
                $paymentData['paid_at'] = $order->paid_at ?? now();
            }
            if (Schema::hasColumn('order_payments', 'is_offline')) {
                $paymentData['is_offline'] = false;
            }
            if (Schema::hasColumn('order_payments', 'fee')) {
                $paymentData['fee'] = 0;
            }
            if (Schema::hasColumn('order_payments', 'total_amount')) {
                $paymentData['total_amount'] = $order->grand_total;
            }

            if (!Schema::hasColumn('order_payments', 'payment_type_id') || $order->payment_type_id) {
                $order->payments()->create($paymentData);
            }
        }

        return response()->json([
            'success' => true,
            'data' => $this->appendRefundSummary($order->fresh()->load([
                'orderItems',
                'store',
                'payments',
                'refunds' => fn ($query) => $query
                    ->withoutGlobalScope('tenant')
                    ->with('refundItems.orderItem')
                    ->where('status', Refund::STATUS_PENDING),
            ]))
        ]);
    }

    private function appendRefundSummary(Order $order): Order
    {
        $pendingRefunds = $order->relationLoaded('refunds')
            ? $order->refunds->where('status', Refund::STATUS_PENDING)
            : $order->refunds()
                ->withoutGlobalScope('tenant')
                ->where('status', Refund::STATUS_PENDING)
                ->get();

        $pendingAmount = (float) $pendingRefunds->sum('total_amount');

        $order->setAttribute('pending_refund_count', $pendingRefunds->count());
        $order->setAttribute('pending_refund_amount', $pendingAmount);
        $order->setAttribute('has_pending_refund', $pendingRefunds->isNotEmpty());
        $order->setAttribute('refund_status', $pendingRefunds->isNotEmpty() ? 'pending_approval' : null);
        $order->setAttribute('pending_refunds', $pendingRefunds->map(function (Refund $refund) use ($order) {
            return [
                'id' => $refund->id,
                'order_id' => $refund->order_id,
                'refund_number' => $refund->refund_number,
                'refund_type' => $refund->refund_type,
                'total_amount' => (float) $refund->total_amount,
                'reason' => $refund->reason,
                'notes' => $refund->notes,
                'status' => $refund->status,
                'refunded_at' => $refund->refunded_at?->toDateTimeString(),
                'order' => [
                    'id' => $order->id,
                    'receipt_number' => $order->receipt_number,
                    'order_number' => $order->order_number ?? null,
                    'store_id' => $order->store_id,
                    'store_name' => $order->store?->nickname ?: $order->store?->name,
                    'grand_total' => (float) $order->grand_total,
                    'order_type' => $order->order_type,
                    'customer_name' => $order->customer_name,
                    'created_at' => $order->created_at?->toDateTimeString(),
                ],
                'items' => $refund->refundItems->map(function ($item) {
                    return [
                        'order_item_id' => $item->order_item_id,
                        'product_name' => data_get($item->orderItem->product_snapshot, 'name', 'Unknown'),
                        'quantity_refunded' => (int) $item->quantity_refunded,
                        'unit_price' => (float) $item->unit_price,
                        'total_refund_amount' => (float) $item->total_refund_amount,
                        'reason' => $item->reason,
                    ];
                })->values(),
            ];
        })->values());
        $order->unsetRelation('refunds');

        return $order;
    }

    private function resolveChannelPrice(string $storeId, string $productId, string $customerTypeId): ?float
    {
        $price = DB::table('product_prices')
            ->where('store_id', $storeId)
            ->where('product_id', $productId)
            ->where('customer_type_id', $customerTypeId)
            ->where('is_active', true)
            ->value('price');

        return $price === null ? null : (float) $price;
    }
}
