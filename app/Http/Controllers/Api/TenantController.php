<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TenantController extends Controller
{
    /**
     * Get user's accessible tenants for tenant selection
     */
    public function accessible(Request $request): JsonResponse
    {
        $user = $request->user();
        $tenants = collect();

        // Add user's primary tenant
        if ($user->tenant) {
            $tenants->push([
                'id' => $user->tenant->id,
                'name' => $user->tenant->name,
                'is_primary' => true,
                'is_owner' => $user->tenant->owner_id === $user->id,
            ]);
        }

        // Add additional accessible tenants via many-to-many relationship
        foreach ($user->tenants as $tenant) {
            // Skip if already added as primary
            if ($tenant->id === $user->tenant_id) {
                continue;
            }

            $tenants->push([
                'id' => $tenant->id,
                'name' => $tenant->name,
                'is_primary' => false,
                'is_owner' => $tenant->owner_id === $user->id,
                'role' => $tenant->pivot->role ?? 'employee',
            ]);
        }

        return response()->json([
            'success' => true,
            'tenants' => $tenants->values(),
        ]);
    }
}
