<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Table;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TableController extends Controller
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

        $tables = Table::where('store_id', $storeId)
            ->orderBy('table_number')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $tables
        ]);
    }

    public function store(Request $request, string $storeId): JsonResponse
    {
        $request->validate([
            'table_number' => 'required|string|max:50',
            'capacity' => 'nullable|integer|min:1',
        ]);

        $table = Table::create([
            'store_id' => $storeId,
            'table_number' => $request->table_number,
            'capacity' => $request->capacity,
            'is_available' => true,
        ]);

        return response()->json([
            'success' => true,
            'data' => $table
        ], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $table = Table::findOrFail($id);

        $request->validate([
            'table_number' => 'sometimes|required|string|max:50',
            'capacity' => 'nullable|integer|min:1',
            'is_available' => 'sometimes|boolean',
        ]);

        $table->update($request->only(['table_number', 'capacity', 'is_available']));

        return response()->json([
            'success' => true,
            'data' => $table
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $table = Table::findOrFail($id);
        $table->delete();

        return response()->json([
            'success' => true,
            'message' => 'Table deleted successfully'
        ]);
    }
}
