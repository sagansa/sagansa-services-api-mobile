<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Refund;
use App\Models\RefundItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

class RefundController extends Controller
{
    /**
     * Check if an order is eligible for refund
     */
    public function checkEligibility(Order $order)
    {
        try {
            $order->loadMissing('orderItems');

            // Check if order is paid
            if (!$order->isPaid()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order must be paid before refund',
                ], 400);
            }

            // Check if already fully refunded
            if ($order->total_refunded >= $order->grand_total) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order is already fully refunded',
                ], 400);
            }

            // Get available items for refund
            $availableItems = $order->orderItems()->with(['refundItems.refund'])->get()->map(function ($item) {
                $quantityRefunded = (int) ($item->quantity_refunded ?? 0);
                $quantityPending = (int) $item->refundItems
                    ->filter(fn ($refundItem) => $refundItem->refund?->status === Refund::STATUS_PENDING)
                    ->sum('quantity_refunded');
                $unitPrice = (float) ($item->unit_price ?? 0);
                $availableQty = (int) $item->quantity - $quantityRefunded - $quantityPending;
                return [
                    'order_item_id' => $item->id,
                    'product_name' => data_get($item->product_snapshot, 'name', 'Unknown'),
                    'quantity' => $item->quantity,
                    'quantity_refunded' => $quantityRefunded,
                    'quantity_pending_refund' => $quantityPending,
                    'available_quantity' => $availableQty,
                    'unit_price' => $unitPrice,
                    'max_refund_amount' => $availableQty * $unitPrice,
                ];
            })->filter(function ($item) {
                return $item['available_quantity'] > 0;
            })->values();

            return response()->json([
                'success' => true,
                'order' => [
                    'id' => $order->id,
                    'receipt_number' => $order->receipt_number,
                    'grand_total' => $order->grand_total,
                    'total_refunded' => (float) ($order->total_refunded ?? 0),
                    'available_refund_amount' => (float) $order->grand_total - (float) ($order->total_refunded ?? 0),
                ],
                'available_items' => $availableItems,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to check refund eligibility: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Request a refund. The actual refund is processed after manager/owner approval.
     */
    public function store(Request $request, Order $order)
    {
        try {
            // Validation
            $validator = Validator::make($request->all(), [
                'items' => 'required|array|min:1',
                'items.*.order_item_id' => 'required|exists:order_items,id',
                'items.*.quantity' => 'required|integer|min:1',
                'items.*.reason' => 'nullable|string|max:500',
                'reason' => 'required|string|max:500',
                'notes' => 'nullable|string|max:1000',
                'payment_method' => 'nullable|string|max:50',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            // Check if order is paid
            if (!$order->isPaid()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order must be paid before refund',
                ], 400);
            }

            // Validate each item
            $totalRefundAmount = 0;
            $validatedItems = [];

            foreach ($request->items as $item) {
                $orderItem = OrderItem::with(['refundItems.refund'])->find($item['order_item_id']);

                if (!$orderItem || $orderItem->order_id !== $order->id) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Invalid order item',
                    ], 400);
                }

                $quantityRefunded = (int) ($orderItem->quantity_refunded ?? 0);
                $quantityPending = (int) $orderItem->refundItems
                    ->filter(fn ($refundItem) => $refundItem->refund?->status === Refund::STATUS_PENDING)
                    ->sum('quantity_refunded');
                $availableQty = (int) $orderItem->quantity - $quantityRefunded - $quantityPending;

                if ($item['quantity'] > $availableQty) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Quantity exceeds available refund quantity for item ' . data_get($orderItem->product_snapshot, 'name', 'Unknown'),
                    ], 400);
                }

                $unitPrice = (float) ($orderItem->unit_price ?? 0);
                $itemRefundAmount = $item['quantity'] * $unitPrice;
                $totalRefundAmount += $itemRefundAmount;

                $validatedItems[] = [
                    'order_item' => $orderItem,
                    'quantity' => $item['quantity'],
                    'unit_price' => $unitPrice,
                    'total_refund_amount' => $itemRefundAmount,
                    'reason' => $item['reason'] ?? null,
                ];
            }

            // Check total refund amount
            if (((float) ($order->total_refunded ?? 0) + $totalRefundAmount) > (float) $order->grand_total) {
                return response()->json([
                    'success' => false,
                    'message' => 'Total refund amount exceeds order total',
                ], 400);
            }

            // Create pending refund request in transaction.
            $user = $request->user();
            $userKey = (string) ($user->uuid ?: $user->id);

            $refund = DB::transaction(function () use ($order, $request, $validatedItems, $totalRefundAmount, $userKey) {
                $refund = Refund::create([
                    'tenant_id' => $order->tenant_id,
                    'order_id' => $order->id,
                    'refund_number' => Refund::generateRefundNumber(),
                    'refund_type' => ((float) ($order->total_refunded ?? 0) + $totalRefundAmount) >= (float) $order->grand_total 
                        ? Refund::TYPE_FULL 
                        : Refund::TYPE_PARTIAL,
                    'total_amount' => $totalRefundAmount,
                    'reason' => $request->reason,
                    'notes' => $request->notes,
                    'refunded_by' => $userKey,
                    'refunded_at' => now(),
                    'payment_method' => $request->payment_method ?? 'cash',
                    'status' => Refund::STATUS_PENDING,
                ]);

                foreach ($validatedItems as $item) {
                    RefundItem::create([
                        'refund_id' => $refund->id,
                        'order_item_id' => $item['order_item']->id,
                        'quantity_refunded' => $item['quantity'],
                        'unit_price' => $item['unit_price'],
                        'total_refund_amount' => $item['total_refund_amount'],
                        'reason' => $item['reason'],
                    ]);
                }

                return $refund;
            });

            // Load relationships for response
            $refund->load('refundItems.orderItem');

            return response()->json([
                'success' => true,
                'message' => 'Refund request submitted and waiting for manager/owner approval',
                'refund' => $this->formatRefund($refund),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to process refund: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get list of refunds
     */
    public function index(Request $request)
    {
        try {
            if ($request->get('status') === Refund::STATUS_PENDING
                && !$request->has('order_id')
                && !$this->canApproveAnyRefund($request)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only manager or owner can view pending refund approvals',
                ], 403);
            }

            $isApprovalList = $request->get('status') === Refund::STATUS_PENDING && !$request->has('order_id');

            $query = ($isApprovalList ? Refund::withoutGlobalScope('tenant') : Refund::query())
                ->with([
                    'order' => fn ($query) => $query->withoutGlobalScope('tenant')->with('store'),
                    'refundedBy',
                    'approvedBy',
                    'rejectedBy',
                    'refundItems.orderItem',
                ]);

            // Filters
            if ($request->has('store_id')) {
                $query->byStore($request->store_id);
            }

            if ($request->has('order_id')) {
                $query->byOrder($request->order_id);
            }

            if ($request->has('status')) {
                $query->byStatus($request->status);
            }

            if ($request->has('refund_type')) {
                $query->byType($request->refund_type);
            }

            if ($request->has('start_date') && $request->has('end_date')) {
                $query->byDateRange($request->start_date, $request->end_date);
            }

            // Pagination
            $perPage = $request->get('per_page', 15);
            $refunds = $query->orderByDesc('created_at')->paginate($perPage);
            $refunds->getCollection()->transform(fn (Refund $refund) => $this->formatRefund($refund));

            return response()->json([
                'success' => true,
                'refunds' => $refunds,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch refunds: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get refund details
     */
    public function show(Refund $refund)
    {
        try {
            $refund->load(['order', 'refundedBy', 'approvedBy', 'rejectedBy', 'refundItems.orderItem']);

            return response()->json([
                'success' => true,
                'refund' => $this->formatRefund($refund),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch refund details: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function approve(Request $request, Refund $refund)
    {
        try {
            if (!$this->canApproveRefund($request, $refund)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only manager or owner can approve refunds',
                ], 403);
            }

            if ($refund->status !== Refund::STATUS_PENDING) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only pending refunds can be approved',
                ], 400);
            }

            $approver = $request->user();
            $approverKey = (string) ($approver->uuid ?: $approver->id);

            $refund = DB::transaction(function () use ($refund, $approverKey) {
                $refund->loadMissing(['order.payments', 'refundItems.orderItem']);
                $order = $refund->order()->lockForUpdate()->firstOrFail();

                foreach ($refund->refundItems as $refundItem) {
                    $orderItem = OrderItem::query()->lockForUpdate()->findOrFail($refundItem->order_item_id);
                    $availableQty = (int) $orderItem->quantity - (int) ($orderItem->quantity_refunded ?? 0);

                    if ((int) $refundItem->quantity_refunded > $availableQty) {
                        throw new \RuntimeException('Refund quantity is no longer available for item ' . data_get($orderItem->product_snapshot, 'name', 'Unknown'));
                    }

                    $orderItem->increment('quantity_refunded', (int) $refundItem->quantity_refunded);
                    $orderItem->increment('refund_amount', (float) $refundItem->total_refund_amount);
                }

                $order->increment('total_refunded', (float) $refund->total_amount);
                $order->increment('refund_count');
                $order->refresh();

                if (Schema::hasColumn('order_payments', 'status')) {
                    $order->payments()->update([
                        'status' => (float) ($order->total_refunded ?? 0) >= (float) $order->grand_total
                            ? 'refunded'
                            : 'partial_refund',
                    ]);
                }

                if (Schema::hasColumn('orders', 'payment_status')) {
                    $order->update([
                        'payment_status' => (float) ($order->total_refunded ?? 0) >= (float) $order->grand_total
                            ? 'refunded'
                            : 'partial_refund',
                        'status' => (float) ($order->total_refunded ?? 0) >= (float) $order->grand_total
                            ? 'refunded'
                            : $order->status,
                    ]);
                }

                $refund->update([
                    'status' => Refund::STATUS_COMPLETED,
                    'approved_by' => $approverKey,
                    'approved_at' => now(),
                ]);

                return $refund->fresh(['order.store', 'refundedBy', 'approvedBy', 'refundItems.orderItem']);
            });

            return response()->json([
                'success' => true,
                'message' => 'Refund approved successfully',
                'refund' => $this->formatRefund($refund),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to approve refund: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function reject(Request $request, Refund $refund)
    {
        try {
            if (!$this->canApproveRefund($request, $refund)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only manager or owner can reject refunds',
                ], 403);
            }

            $validated = $request->validate([
                'rejection_reason' => ['nullable', 'string', 'max:1000'],
            ]);

            if ($refund->status !== Refund::STATUS_PENDING) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only pending refunds can be rejected',
                ], 400);
            }

            $rejector = $request->user();
            $rejectorKey = (string) ($rejector->uuid ?: $rejector->id);

            $refund->update([
                'status' => Refund::STATUS_REJECTED,
                'rejected_by' => $rejectorKey,
                'rejected_at' => now(),
                'rejection_reason' => $validated['rejection_reason'] ?? null,
            ]);

            $refund->load(['order.store', 'refundedBy', 'rejectedBy', 'refundItems.orderItem']);

            return response()->json([
                'success' => true,
                'message' => 'Refund rejected successfully',
                'refund' => $this->formatRefund($refund),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to reject refund: ' . $e->getMessage(),
            ], 500);
        }
    }

    private function canApproveRefund(Request $request, Refund $refund): bool
    {
        $user = $request->user();
        if (!$user) {
            return false;
        }

        $activeTenantId = $request->attributes->get('active_tenant_id') ?? $request->input('active_tenant_id') ?? $user->tenant_id;
        $userKey = (string) ($user->uuid ?: $user->id);
        $role = strtolower((string) ($user->role ?? ''));

        if (in_array($role, ['manager', 'owner'], true)) {
            return true;
        }

        if (method_exists($user, 'hasRole') && ($user->hasRole('manager') || $user->hasRole('owner'))) {
            return true;
        }

        if (DB::connection('mysql_auth')->table('tenant_user')
            ->where('user_id', $userKey)
            ->where('tenant_id', $activeTenantId)
            ->whereIn('role', ['manager', 'owner'])
            ->exists()) {
            return true;
        }

        return DB::table('tenants')
            ->where('id', $refund->tenant_id)
            ->where('owner_id', $userKey)
            ->exists();
    }

    private function canApproveAnyRefund(Request $request): bool
    {
        $user = $request->user();
        if (!$user) {
            return false;
        }

        $activeTenantId = $request->attributes->get('active_tenant_id') ?? $request->input('active_tenant_id') ?? $user->tenant_id;
        $userKey = (string) ($user->uuid ?: $user->id);
        $role = strtolower((string) ($user->role ?? ''));

        if (in_array($role, ['manager', 'owner'], true)) {
            return true;
        }

        if (method_exists($user, 'hasRole') && ($user->hasRole('manager') || $user->hasRole('owner'))) {
            return true;
        }

        if (Schema::connection('mysql_auth')->hasTable('tenant_user')
            && DB::connection('mysql_auth')->table('tenant_user')
                ->where('user_id', $userKey)
                ->where('tenant_id', $activeTenantId)
                ->whereIn('role', ['manager', 'owner'])
                ->exists()) {
            return true;
        }

        return DB::table('tenants')
            ->where('id', $activeTenantId)
            ->where('owner_id', $userKey)
            ->exists()
            || DB::table('tenants')
                ->where('owner_id', $userKey)
                ->exists();
    }

    private function formatRefund(Refund $refund): array
    {
        return [
            'id' => $refund->id,
            'order_id' => $refund->order_id,
            'refund_number' => $refund->refund_number,
            'refund_type' => $refund->refund_type,
            'total_amount' => (float) $refund->total_amount,
            'reason' => $refund->reason,
            'notes' => $refund->notes,
            'status' => $refund->status,
            'order' => $refund->order ? [
                'id' => $refund->order->id,
                'receipt_number' => $refund->order->receipt_number,
                'order_number' => $refund->order->order_number ?? null,
                'store_id' => $refund->order->store_id,
                'store_name' => $refund->order->store?->nickname ?: $refund->order->store?->name,
                'grand_total' => (float) $refund->order->grand_total,
                'order_type' => $refund->order->order_type,
                'customer_name' => $refund->order->customer_name,
                'created_at' => $refund->order->created_at?->toDateTimeString(),
            ] : null,
            'refunded_by' => $refund->refunded_by,
            'refunded_at' => $refund->refunded_at?->toDateTimeString(),
            'approved_by' => $refund->approved_by,
            'approved_at' => $refund->approved_at?->toDateTimeString(),
            'rejected_by' => $refund->rejected_by,
            'rejected_at' => $refund->rejected_at?->toDateTimeString(),
            'rejection_reason' => $refund->rejection_reason,
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
    }
}
