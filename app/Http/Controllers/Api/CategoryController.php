<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    /**
     * Get categories for the authenticated user's tenant
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $storeId = $request->query('store_id');
        $targetTenantId = $user->tenant_id;
        
        if (!$user->tenant_id) {
            return response()->json([
                'success' => false,
                'message' => 'User does not belong to any tenant'
            ], 403);
        }

        // Validate that the store belongs to an accessible tenant
        if ($storeId) {
            $store = \App\Models\Store::where('id', $storeId)->first();
                
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
                $allowedRoles = \Illuminate\Support\Facades\DB::table('roles')
                    ->join('role_has_permissions', 'roles.id', '=', 'role_has_permissions.role_id')
                    ->join('permissions', 'role_has_permissions.permission_id', '=', 'permissions.id')
                    ->where('permissions.name', 'access-pos')
                    ->pluck('roles.name')
                    ->toArray();

                $hasRole = \Illuminate\Support\Facades\DB::table('tenant_user')
                    ->where('user_id', $user->id)
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
        
        // Get categories associated with target tenant
        // Use withoutGlobalScope('tenant') to bypass the automatic user tenant filter
        $categories = Category::withoutGlobalScope('tenant')
            ->where('tenant_id', $targetTenantId)
            ->select(['id', 'name', 'tenant_id', 'created_at', 'updated_at'])
            ->orderBy('name')
            ->get();

        \Log::info('CategoryController::index', [
            'user_id' => $user->id,
            'user_tenant_id' => $user->tenant_id,
            'request_store_id' => $storeId,
            'target_tenant_id' => $targetTenantId,
            'categories_count' => $categories->count(),
            'categories' => $categories->pluck('name'),
        ]);

        return response()->json([
            'success' => true,
            'categories' => $categories, // Using 'categories' key to match frontend expectation
            'data' => $categories, // Also providing 'data' for standard API consistency
            'meta' => [
                'total' => $categories->count(),
                'tenant_id' => $targetTenantId,
            ]
        ]);
    }
}
