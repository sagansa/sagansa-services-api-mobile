<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CustomerType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerTypeController extends Controller
{
    public function index(Request $request, string $storeId): JsonResponse
    {
        $user = $request->user();
        $store = \App\Models\Store::find($storeId);

        if (!$store) {
            return response()->json([
                'success' => false,
                'message' => 'Store not found'
            ], 404);
        }

        // Check access
        $hasAccess = $store->tenant_id === $user->tenant_id;
        
        if (!$hasAccess) {
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

        $customerTypes = CustomerType::where('store_id', $storeId)
            ->where('is_active', true)
            ->with('linkedPaymentMethod')
            ->orderBy('order')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $customerTypes
        ]);
    }

    public function store(Request $request, string $storeId): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:100',
            'order' => 'nullable|integer',
        ]);

        $customerType = CustomerType::create([
            'store_id' => $storeId,
            'name' => $request->name,
            'order' => $request->order ?? 0,
            'is_active' => true,
        ]);

        return response()->json([
            'success' => true,
            'data' => $customerType
        ], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $customerType = CustomerType::findOrFail($id);

        $request->validate([
            'name' => 'sometimes|required|string|max:100',
            'order' => 'nullable|integer',
            'is_active' => 'sometimes|boolean',
        ]);

        $customerType->update($request->only(['name', 'order', 'is_active']));

        return response()->json([
            'success' => true,
            'data' => $customerType
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $customerType = CustomerType::findOrFail($id);
        $customerType->delete();

        return response()->json([
            'success' => true,
            'message' => 'Customer type deleted successfully'
        ]);
    }
}
