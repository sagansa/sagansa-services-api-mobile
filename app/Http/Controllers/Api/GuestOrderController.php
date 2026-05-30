<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductModification;
use App\Models\ProductVariant;
use App\Models\Store;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GuestOrderController extends Controller
{
    /**
     * Get guest order history by phone number.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'phone' => 'required|string|max:30',
            'store_id' => 'nullable|exists:stores,id',
        ]);

        $phone = preg_replace('/[^\d+]/', '', $request->query('phone'));

        $orders = Order::withoutGlobalScope('tenant')
            ->with(['orderItems'])
            ->where('source', 'web-order')
            ->where('customer_phone', $phone)
            ->when($request->query('store_id'), function ($query, $storeId) {
                $query->where('store_id', $storeId);
            })
            ->latest()
            ->limit(25)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $orders,
        ]);
    }

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
            'customer_phone' => 'required|string|max:30',
            'table_code' => 'nullable|string|max:50',
            'subtotal' => 'required|numeric|min:0',
            'discount_total' => 'required|numeric|min:0',
            'tax_total' => 'required|numeric|min:0',
            'service_total' => 'required|numeric|min:0',
            'grand_total' => 'required|numeric|min:0',
            'payment_type_id' => 'nullable|exists:payment_type,id',
            'payment_method' => 'nullable|string|max:50',
            'order_items' => 'required|array',
            'order_items.*.product_id' => 'required|string',
            'order_items.*.product_variant_id' => 'nullable|string',
            'order_items.*.quantity' => 'required|integer|min:1',
            'order_items.*.unit_price' => 'required|numeric|min:0',
            'order_items.*.total_price' => 'required|numeric|min:0',
            'order_items.*.name_snapshot' => 'required|string',
            'order_items.*.notes' => 'nullable|string',
            'order_items.*.modifications' => 'nullable|array',
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

            $customerPhone = preg_replace('/[^\d+]/', '', $request->customer_phone);

            $order = Order::create([
                'tenant_id' => $request->tenant_id,
                'store_id' => $request->store_id,
                'created_by' => null, // Guest order
                'customer_name' => $request->customer_name,
                'customer_email' => $request->customer_email,
                'customer_phone' => $customerPhone,
                'table_code' => $request->table_code,
                'status' => 'pending',
                'subtotal' => $request->subtotal,
                'discount_total' => $request->discount_total,
                'tax_total' => $request->tax_total,
                'service_total' => $request->service_total,
                'grand_total' => $request->grand_total,
                'payment_type_id' => $request->payment_type_id,
                'payment_method' => $request->payment_method,
                'source' => 'web-order',
                'is_offline' => false,
            ]);

            // Create order items
            foreach ($request->order_items as $itemData) {
                $product = Product::withoutGlobalScope('tenant')->find($itemData['product_id']);
                $variant = !empty($itemData['product_variant_id'])
                    ? ProductVariant::withoutGlobalScope('tenant')->find($itemData['product_variant_id'])
                    : null;

                $modificationsSnapshot = collect($itemData['modifications'] ?? [])
                    ->map(function ($modData) {
                        $modificationId = $modData['product_modification_id'] ?? null;
                        $modification = $modificationId
                            ? ProductModification::withoutGlobalScope('tenant')->find($modificationId)
                            : null;

                        return [
                            'id' => $modificationId,
                            'name' => $modification?->name,
                            'price' => $modData['price'] ?? 0,
                            'quantity' => $modData['quantity'] ?? 1,
                        ];
                    })
                    ->values()
                    ->all();

                $orderItem = $order->orderItems()->create([
                    'store_id' => $request->store_id,
                    'quantity' => $itemData['quantity'],
                    'unit_price' => $itemData['unit_price'],
                    'total_price' => $itemData['total_price'],
                    'notes' => $itemData['notes'] ?? null,
                    'product_snapshot' => [
                        'id' => $itemData['product_id'],
                        'name' => $product?->name ?? $itemData['name_snapshot'],
                        'price' => $product?->price,
                    ],
                    'variant_snapshot' => $variant ? [
                        'id' => $variant->id,
                        'name' => $variant->name,
                        'price' => $variant->price,
                    ] : null,
                    'modifications_snapshot' => $modificationsSnapshot,
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $order->load(['orderItems'])
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
