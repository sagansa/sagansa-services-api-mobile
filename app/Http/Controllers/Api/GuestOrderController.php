<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Store;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GuestOrderController extends Controller
{
    /**
     * Create a new guest order
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'tenant_id' => 'required|exists:tenants,id',
            'store_id' => 'required|exists:stores,id',
            'customer_name' => 'nullable|string|max:255',
            'customer_email' => 'nullable|email|max:255',
            'customer_phone' => 'nullable|string|max:20',
            'table_code' => 'nullable|string|max:50',
            'subtotal' => 'required|numeric|min:0',
            'discount_total' => 'required|numeric|min:0',
            'tax_total' => 'required|numeric|min:0',
            'service_total' => 'required|numeric|min:0',
            'grand_total' => 'required|numeric|min:0',
            'payment_type_id' => 'nullable|exists:payment_type,id',
            'order_items' => 'required|array',
            'order_items.*.product_id' => 'required|exists:products,id',
            'order_items.*.product_variant_id' => 'nullable|exists:product_variants,id',
            'order_items.*.quantity' => 'required|integer|min:1',
            'order_items.*.unit_price' => 'required|numeric|min:0',
            'order_items.*.total_price' => 'required|numeric|min:0',
            'order_items.*.name_snapshot' => 'required|string',
            'order_items.*.notes' => 'nullable|string',
        ]);

        // Verify that the store belongs to the tenant
        $store = Store::where('id', $request->store_id)
            ->where('tenant_id', $request->tenant_id)
            ->first();
        
        if (!$store) {
            return response()->json([
                'success' => false,
                'message' => 'Store not found or does not belong to the specified tenant'
            ], 404);
        }

        try {
            DB::beginTransaction();

            $order = Order::create([
                'tenant_id' => $request->tenant_id,
                'store_id' => $request->store_id,
                'created_by' => null, // Guest order
                'customer_name' => $request->customer_name,
                'customer_email' => $request->customer_email,
                'customer_phone' => $request->customer_phone,
                'table_code' => $request->table_code,
                'status' => 'pending',
                'subtotal' => $request->subtotal,
                'discount_total' => $request->discount_total,
                'tax_total' => $request->tax_total,
                'service_total' => $request->service_total,
                'grand_total' => $request->grand_total,
                'payment_type_id' => $request->payment_type_id,
                'source' => 'web-order',
                'is_offline' => false,
            ]);

            // Create order items
            foreach ($request->order_items as $itemData) {
                $orderItem = $order->orderItems()->create([
                    'product_id' => $itemData['product_id'],
                    'product_variant_id' => $itemData['product_variant_id'] ?? null,
                    'name_snapshot' => $itemData['name_snapshot'],
                    'quantity' => $itemData['quantity'],
                    'unit_price' => $itemData['unit_price'],
                    'total_price' => $itemData['total_price'],
                    'notes' => $itemData['notes'] ?? null,
                ]);

                // Handle modifications if any
                if (isset($itemData['modifications']) && is_array($itemData['modifications'])) {
                    foreach ($itemData['modifications'] as $modData) {
                        $orderItem->orderItemModifications()->create([
                            'product_modification_id' => $modData['product_modification_id'],
                            'price' => $modData['price'],
                            'quantity' => $modData['quantity'] ?? 1,
                        ]);
                    }
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $order->load(['orderItems', 'orderItems.product', 'orderItems.productVariant'])
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create order: ' . $e->getMessage()
            ], 500);
        }
    }
}
