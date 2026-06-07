<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\Store;
use App\Models\Product;
use App\Models\PaymentMethod;
use App\Models\CustomerType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class PublicController extends Controller
{
    /**
     * Get all active tenants
     */
    public function tenants(): JsonResponse
    {
        $tenants = Tenant::select(['id', 'name'])->get();

        return response()->json([
            'success' => true,
            'data' => $tenants
        ]);
    }

    /**
     * Get stores for a specific tenant
     */
    public function stores(Request $request): JsonResponse
    {
        $tenantId = $request->query('tenant_id');
        
        if (!$tenantId) {
            return response()->json([
                'success' => false,
                'message' => 'Tenant ID is required'
            ], 400);
        }

        $storeColumns = ['id', 'name', 'nickname', 'tenant_id', 'latitude', 'longitude'];

        if (Schema::hasColumn('stores', 'phone')) {
            $storeColumns[] = 'phone';
        }

        if (Schema::hasColumn('stores', 'no_telp')) {
            $storeColumns[] = 'no_telp';
        }

        $stores = Store::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->select($storeColumns)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $stores
        ]);
    }

    /**
     * Get products for a specific store
     */
    public function products(Request $request): JsonResponse
    {
        $storeId = $request->query('store_id');
        
        if (!$storeId) {
            return response()->json([
                'success' => false,
                'message' => 'Store ID is required'
            ], 400);
        }

        $store = Store::withoutGlobalScope('tenant')->find($storeId);
        
        if (!$store) {
            return response()->json([
                'success' => false,
                'message' => 'Store not found'
            ], 404);
        }

        $products = Product::withoutGlobalScope('tenant')
            ->whereHas('stores', function($q) use ($storeId) {
                $q->where('stores.id', $storeId);
            })
            ->where('is_active', true)
            ->with([
                'category' => function($q) {
                    $q->withoutGlobalScopes();
                }, 
                'variantGroups' => function($q) {
                    $q->withoutGlobalScopes()->orderBy('order')->with(['variants' => function($vq) {
                        $vq->withoutGlobalScopes()->orderBy('name');
                    }]);
                },
                'variantCombinations' => function($q) {
                    $q->withoutGlobalScopes();
                },
                'modifications' => function($q) {
                    $q->withoutGlobalScopes();
                },
                'stores' => function($q) use ($storeId) {
                    $q->where('stores.id', $storeId);
                }
            ])
            ->get()
            ->map(function($product) {
                $storeProduct = $product->stores->first();
                $pivot = $storeProduct?->pivot;
                $storePrice = $pivot?->price;

                if ($storePrice !== null) {
                    $product->price = (int) $storePrice;
                }

                $stock = $product->stock;
                $tracksStock = $product->remaining === true;
                $hasStock = !$tracksStock || (int) $stock > 0;
                $isAvailable = $product->is_active && $hasStock;

                $product->setAttribute('isAvailable', $isAvailable);
                $product->setAttribute('stock', $stock);

                unset($product->stores);

                return $product;
            });

        return response()->json([
            'success' => true,
            'data' => $products
        ]);
    }

    /**
     * Get active payment methods for customer menu checkout.
     */
    public function paymentMethods(Request $request): JsonResponse
    {
        $storeId = $request->query('store_id');

        if (!$storeId) {
            return response()->json([
                'success' => false,
                'message' => 'Store ID is required'
            ], 400);
        }

        $customerTypePaymentMethodIds = CustomerType::query()
            ->where('store_id', $storeId)
            ->where('is_active', true)
            ->where('auto_payment', true)
            ->whereNotNull('linked_payment_method_id')
            ->pluck('linked_payment_method_id');

        $paymentMethods = PaymentMethod::query()
            ->where('store_id', $storeId)
            ->where('is_active', true)
            ->where('type', '!=', 'cash')
            ->whereNotIn('id', $customerTypePaymentMethodIds)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get(['id', 'store_id', 'type', 'name', 'is_active', 'require_proof', 'details']);

        return response()->json([
            'success' => true,
            'data' => $paymentMethods,
        ]);
    }
}
