<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemModification;
use App\Models\Store;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
            'proof_of_payment' => 'nullable|image|max:5120', // Max 5MB
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

            $order = Order::create([
                'tenant_id' => $store->tenant_id, // Use store's tenant_id for cross-tenant support
                'store_id' => $request->store_id,
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

                // No need to create separate modification records - they're in the snapshot
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

    /**
     * Get orders for the authenticated user
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        
        // Bypass TenantScope to support cross-tenant access via store_id
        $query = Order::withoutGlobalScope('tenant')
            ->with(['orderItems'])
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

        $orders = $query->get();

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
            ->with(['orderItems', 'payments', 'customer'])
            ->first();

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $order
        ]);
    }

    /**
     * Update an order
     */
    public function update(Request $request, string $orderId): JsonResponse
    {
        $user = $request->user();
        
        $order = Order::where('id', $orderId)
            ->where('tenant_id', $user->tenant_id)
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
        ]);

        $order->update($request->only(['status', 'paid_at']));

        return response()->json([
            'success' => true,
            'data' => $order->load(['orderItems'])
        ]);
    }
}
