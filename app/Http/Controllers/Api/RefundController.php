<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Refund;
use App\Models\RefundItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class RefundController extends Controller
{
    /**
     * Check if an order is eligible for refund
     */
    public function checkEligibility(Order $order)
    {
        try {
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
            $availableItems = $order->orderItems()->get()->map(function ($item) {
                $availableQty = $item->quantity - $item->quantity_refunded;
                return [
                    'order_item_id' => $item->id,
                    'product_name' => $item->product_snapshot['name'] ?? 'Unknown',
                    'quantity' => $item->quantity,
                    'quantity_refunded' => $item->quantity_refunded,
                    'available_quantity' => $availableQty,
                    'unit_price' => $item->unit_price,
                    'max_refund_amount' => $availableQty * $item->unit_price,
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
                    'total_refunded' => $order->total_refunded,
                    'available_refund_amount' => $order->grand_total - $order->total_refunded,
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
     * Process a refund
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
                $orderItem = OrderItem::find($item['order_item_id']);

                if (!$orderItem || $orderItem->order_id !== $order->id) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Invalid order item',
                    ], 400);
                }

                $availableQty = $orderItem->quantity - $orderItem->quantity_refunded;

                if ($item['quantity'] > $availableQty) {
                    return response()->json([
                        'success' => false,
                        'message' => "Quantity exceeds available refund quantity for item {$orderItem->product_snapshot['name']}",
                    ], 400);
                }

                $itemRefundAmount = $item['quantity'] * $orderItem->unit_price;
                $totalRefundAmount += $itemRefundAmount;

                $validatedItems[] = [
                    'order_item' => $orderItem,
                    'quantity' => $item['quantity'],
                    'unit_price' => $orderItem->unit_price,
                    'total_refund_amount' => $itemRefundAmount,
                    'reason' => $item['reason'] ?? null,
                ];
            }

            // Check total refund amount
            if (($order->total_refunded + $totalRefundAmount) > $order->grand_total) {
                return response()->json([
                    'success' => false,
                    'message' => 'Total refund amount exceeds order total',
                ], 400);
            }

            // Process refund in transaction
            $refund = DB::transaction(function () use ($order, $request, $validatedItems, $totalRefundAmount) {
                // Create refund record
                $refund = Refund::create([
                    'tenant_id' => $order->tenant_id,
                    'order_id' => $order->id,
                    'refund_number' => Refund::generateRefundNumber(),
                    'refund_type' => ($order->total_refunded + $totalRefundAmount) >= $order->grand_total 
                        ? Refund::TYPE_FULL 
                        : Refund::TYPE_PARTIAL,
                    'total_amount' => $totalRefundAmount,
                    'reason' => $request->reason,
                    'notes' => $request->notes,
                    'refunded_by' => auth()->id(),
                    'refunded_at' => now(),
                    'payment_method' => $request->payment_method ?? 'cash',
                    'status' => Refund::STATUS_COMPLETED,
                ]);

                // Create refund items
                foreach ($validatedItems as $item) {
                    RefundItem::create([
                        'refund_id' => $refund->id,
                        'order_item_id' => $item['order_item']->id,
                        'quantity_refunded' => $item['quantity'],
                        'unit_price' => $item['unit_price'],
                        'total_refund_amount' => $item['total_refund_amount'],
                        'reason' => $item['reason'],
                    ]);

                    // Update order item
                    $item['order_item']->increment('quantity_refunded', $item['quantity']);
                    $item['order_item']->increment('refund_amount', $item['total_refund_amount']);
                }

                // Update order totals
                $order->increment('total_refunded', $totalRefundAmount);
                $order->increment('refund_count');

                // Update payment status
                if ($order->total_refunded >= $order->grand_total) {
                    $order->payments()->update(['status' => 'refunded']);
                } else {
                    $order->payments()->update(['status' => 'partial_refund']);
                }

                return $refund;
            });

            // Load relationships for response
            $refund->load('refundItems.orderItem');

            return response()->json([
                'success' => true,
                'message' => 'Refund processed successfully',
                'refund' => [
                    'id' => $refund->id,
                    'refund_number' => $refund->refund_number,
                    'refund_type' => $refund->refund_type,
                    'total_amount' => $refund->total_amount,
                    'status' => $refund->status,
                    'refunded_at' => $refund->refunded_at->toDateTimeString(),
                    'items' => $refund->refundItems->map(function ($item) {
                        return [
                            'product_name' => $item->orderItem->product_snapshot['name'] ?? 'Unknown',
                            'quantity_refunded' => $item->quantity_refunded,
                            'unit_price' => $item->unit_price,
                            'total_refund_amount' => $item->total_refund_amount,
                        ];
                    }),
                ],
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
            $query = Refund::with(['order', 'refundedBy', 'refundItems']);

            // Filters
            if ($request->has('store_id')) {
                $query->byStore($request->store_id);
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
            $refunds = $query->orderBy('refunded_at', 'desc')->paginate($perPage);

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
            $refund->load(['order', 'refundedBy', 'refundItems.orderItem']);

            return response()->json([
                'success' => true,
                'refund' => $refund,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch refund details: ' . $e->getMessage(),
            ], 500);
        }
    }
}
