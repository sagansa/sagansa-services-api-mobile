<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Store;
use App\Models\ProductVariant;
use App\Models\ProductModification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    /**
     * Get products available for the specified store
     */
    /**
     * Get products available for the specified store
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $storeId = $request->query('store_id');
        $targetTenantId = $user->tenant_id;
        
        // Validate that the store belongs to an accessible tenant
        if ($storeId) {
            $store = Store::where('id', $storeId)->first();
                
            if (!$store) {
                return response()->json([
                    'success' => false,
                    'message' => 'Store not found'
                ], 404);
            }

            // Check if user has access to this store's tenant
            $hasAccess = $store->tenant_id === $user->tenant_id;
            
            if (!$hasAccess) {
                // Check if user has access-pos role in this tenant
                $allowedRoles = \Illuminate\Support\Facades\DB::connection('mysql_auth')->table('roles')
                    ->join('role_has_permissions', 'roles.id', '=', 'role_has_permissions.role_id')
                    ->join('permissions', 'role_has_permissions.permission_id', '=', 'permissions.id')
                    ->where('permissions.name', 'access-pos')
                    ->pluck('roles.name')
                    ->toArray();

                $hasRole = \Illuminate\Support\Facades\DB::connection('mysql_auth')->table('tenant_user')
                    ->where('user_id', $user->uuid)
                    ->where('tenant_id', $store->tenant_id)
                    ->whereIn('role', $allowedRoles)
                    ->exists();
                    
                if ($hasRole) {
                    $hasAccess = true;
                }
            }

            if (!$hasAccess) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have access to this store'
                ], 403);
            }

            $targetTenantId = $store->tenant_id;
        }

        // Get active products
        // For cross-tenant access: if store_id is provided and store belongs to different tenant,
        // we should get products from that tenant's perspective
        if ($storeId) {
            // Get products available for the specific store (can be cross-tenant)
            $query = Product::whereHas('stores', function($q) use ($storeId) {
                $q->where('stores.id', $storeId);
            })
            ->where('is_active', true)
            ->with(['stores' => function($q) use ($storeId) {
                $q->where('stores.id', $storeId);
            }]);
            
            \Log::info('Filtering by store_id', ['store_id' => $storeId]);
        } else {
            // No store specified: get products from user's own tenant
            $query = Product::where('tenant_id', $targetTenantId)
                ->where('is_active', true);
        }

        \Log::info('ProductController::index - After query setup', [
            'tenant_id' => $targetTenantId,
            'user_tenant_id' => $user->tenant_id,
            'store_id' => $storeId,
        ]);

        // Add variant relationships and category
        // We need to use withoutTenantScope for category because it uses TenantScope
        // and we might be fetching products from another tenant
        // For variants, we also ensure they load regardless of tenant_id
        $query->with([
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
            }
        ]);

        $products = $query->get();

        \Log::info('ProductController::index - After query', [
            'products_count' => $products->count(),
            'product_names' => $products->pluck('name')->toArray(),
        ]);

        // Log detailed variant/modification info
        foreach ($products as $product) {
            \Log::info('Product details', [
                'name' => $product->name,
                'variant_groups_count' => $product->variantGroups->count(),
                'modifications_count' => $product->modifications->count(),
                'variant_groups' => $product->variantGroups->map(function($group) {
                    return [
                        'name' => $group->name,
                        'variants_count' => $group->variants->count(),
                        'variants' => $group->variants->pluck('name'),
                    ];
                }),
                'modifications' => $product->modifications->pluck('name'),
            ]);
        }

        // Transform products to include store-specific price if store_id is provided
        if ($storeId) {
            $products = $products->map(function($product) {
                $storePrice = $product->stores->first();
                if ($storePrice && $storePrice->pivot && $storePrice->pivot->price) {
                    $product->price = $storePrice->pivot->price;
                }
                // Remove stores relationship from response to keep it clean
                unset($product->stores);
                return $product;
            });
        }

        return response()->json([
            'success' => true,
            'data' => $products
        ]);
    }

    /**
     * Get a specific product
     */
    public function show(Request $request, string $productId): JsonResponse
    {
        $user = $request->user();
        $storeId = $request->query('store_id');
        
        // If store_id is provided, get product via store relationship (allows cross-tenant)
        if ($storeId) {
            $store = Store::where('id', $storeId)->first();
            
            if (!$store) {
                return response()->json([
                    'success' => false,
                    'message' => 'Store not found'
                ], 404);
            }
            
            // Check if user has access to this store's tenant
            $hasAccess = $store->tenant_id === $user->tenant_id;
            
            if (!$hasAccess) {
                // Check if user has access-pos role in this tenant
                $allowedRoles = \Illuminate\Support\Facades\DB::connection('mysql_auth')->table('roles')
                    ->join('role_has_permissions', 'roles.id', '=', 'role_has_permissions.role_id')
                    ->join('permissions', 'role_has_permissions.permission_id', '=', 'permissions.id')
                    ->where('permissions.name', 'access-pos')
                    ->pluck('roles.name')
                    ->toArray();

                $hasRole = \Illuminate\Support\Facades\DB::connection('mysql_auth')->table('tenant_user')
                    ->where('user_id', $user->uuid)
                    ->where('tenant_id', $store->tenant_id)
                    ->whereIn('role', $allowedRoles)
                    ->exists();
                    
                if ($hasRole) {
                    $hasAccess = true;
                }
            }

            if (!$hasAccess) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have access to this store'
                ], 403);
            }
            
            // Get product via store relationship (allows cross-tenant products)
            $query = Product::where('id', $productId)
                ->where('is_active', true)
                ->whereHas('stores', function($q) use ($storeId) {
                    $q->where('stores.id', $storeId);
                })
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
                ]);
        } else {
            // No store specified: get product from user's own tenant
            $query = Product::where('id', $productId)
                ->where('tenant_id', $user->tenant_id)
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
                    }
                ]);
        }

        $product = $query->first();

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found'
            ], 404);
        }

        // If store_id is provided, use store-specific price from pivot
        if ($storeId && $product->stores->isNotEmpty()) {
            $storePrice = $product->stores->first();
            if ($storePrice && $storePrice->pivot && $storePrice->pivot->price) {
                $product->price = $storePrice->pivot->price;
            }
            // Remove stores relationship from response
            unset($product->stores);
        }

        return response()->json([
            'success' => true,
            'data' => $product
        ]);
    }

    /**
     * Create a new product
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        
        if (!$user->tenant_id) {
            return response()->json([
                'success' => false,
                'message' => 'User does not belong to any tenant'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|integer|min:0',
            'image' => 'nullable|string|max:255',
            'sku' => 'nullable|string|max:100|unique:products',
            'barcode' => 'nullable|string|max:100|unique:products',
            'stock' => 'nullable|integer|min:0',
            'request' => 'boolean',
            'remaining' => 'boolean',
            'is_active' => 'boolean',
            'unit_id' => 'nullable|exists:units,id',
            'category_id' => 'nullable|exists:categories,id',
            'variants' => 'nullable|array',
            'variants.*.name' => 'required_with:variants|string|max:255',
            'variants.*.price' => 'required_with:variants|integer|min:0',
            'variants.*.sku' => 'nullable|string|max:100',
            'variants.*.stock' => 'nullable|integer|min:0',
            'modifications' => 'nullable|array',
            'modifications.*.name' => 'required_with:modifications|string|max:255',
            'modifications.*.price' => 'required_with:modifications|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $product = Product::create([
            'name' => $request->name,
            'slug' => Str::slug($request->name),
            'description' => $request->description,
            'price' => $request->price,
            'image' => $request->image,
            'sku' => $request->sku,
            'barcode' => $request->barcode,
            'stock' => $request->stock,
            'request' => $request->request ?? false,
            'remaining' => $request->remaining ?? false,
            'is_active' => $request->is_active ?? true,
            'unit_id' => $request->unit_id,
            'category_id' => $request->category_id,
            'tenant_id' => $user->tenant_id,
            'user_id' => $user->uuid ?: $user->id,
        ]);

        // Create variants if provided
        if ($request->has('variants')) {
            foreach ($request->variants as $variant) {
                $product->variants()->create([
                    'name' => $variant['name'],
                    'sku' => $variant['sku'] ?? null,
                    'price' => $variant['price'],
                    'stock' => $variant['stock'] ?? null,
                    'is_active' => true,
                ]);
            }
        }

        // Create modifications if provided
        if ($request->has('modifications')) {
            foreach ($request->modifications as $modification) {
                $product->modifications()->create([
                    'name' => $modification['name'],
                    'price' => $modification['price'],
                    'is_active' => true,
                ]);
            }
        }

        // Load relationships
        $product->load(['variants', 'modifications']);

        return response()->json([
            'success' => true,
            'message' => 'Product created successfully',
            'data' => $product
        ], 201);
    }

    /**
     * Update a product
     */
    public function update(Request $request, string $productId): JsonResponse
    {
        $user = $request->user();
        
        $product = Product::where('id', $productId)
            ->where('tenant_id', $user->tenant_id)
            ->first();

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'sometimes|required|integer|min:0',
            'image' => 'nullable|string|max:255',
            'sku' => 'nullable|string|max:100|unique:products,sku,' . $product->id,
            'barcode' => 'nullable|string|max:100|unique:products,barcode,' . $product->id,
            'stock' => 'nullable|integer|min:0',
            'request' => 'boolean',
            'remaining' => 'boolean',
            'is_active' => 'boolean',
            'unit_id' => 'nullable|exists:units,id',
            'category_id' => 'nullable|exists:categories,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $updateData = $request->only([
            'name', 'description', 'price', 'image', 'sku', 'barcode', 
            'stock', 'request', 'remaining', 'is_active', 'unit_id', 'category_id'
        ]);

        if ($request->has('name')) {
            $updateData['slug'] = Str::slug($request->name);
        }

        $product->update($updateData);

        // Sync store associations if provided
        if ($request->has('stores') || $request->has('store_ids')) {
            $storeData = [];
            
            // If stores array with prices is provided
            if ($request->has('stores') && is_array($request->stores)) {
                foreach ($request->stores as $storeItem) {
                    if (isset($storeItem['id'])) {
                        $storeData[$storeItem['id']] = [
                            'price' => $storeItem['price'] ?? $product->price,
                        ];
                    }
                }
            }
            // If only store_ids array is provided (without prices)
            elseif ($request->has('store_ids') && is_array($request->store_ids)) {
                foreach ($request->store_ids as $storeId) {
                    $storeData[$storeId] = [
                        'price' => $product->price, // Use product default price
                    ];
                }
            }
            
            // Sync the stores (this will add/update/remove as needed)
            $product->stores()->sync($storeData);
        }

        return response()->json([
            'success' => true,
            'message' => 'Product updated successfully',
            'data' => $product->load(['variants', 'modifications', 'stores'])
        ]);
    }

    /**
     * Delete a product
     */
    public function destroy(Request $request, string $productId): JsonResponse
    {
        $user = $request->user();
        
        $product = Product::where('id', $productId)
            ->where('tenant_id', $user->tenant_id)
            ->first();

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found'
            ], 404);
        }

        $product->delete();

        return response()->json([
            'success' => true,
            'message' => 'Product deleted successfully'
        ]);
    }
}
