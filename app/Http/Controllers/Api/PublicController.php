<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\Store;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

        $stores = Store::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->select(['id', 'name', 'nickname', 'tenant_id'])
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
                $storePrice = $product->stores->first();
                if ($storePrice && $storePrice->pivot && $storePrice->pivot->price) {
                    $product->price = $storePrice->pivot->price;
                }
                unset($product->stores);
                return $product;
            });

        return response()->json([
            'success' => true,
            'data' => $products
        ]);
    }
}
