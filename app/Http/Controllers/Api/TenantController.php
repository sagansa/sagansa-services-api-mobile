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
        $seenIds = [];

        // Get tenant_id from user detail (cross-database safe)
        $detailTenantId = $user->detail?->tenant_id;

        \Log::info('[TenantController] accessible called', [
            'user_id' => $user->id,
            'user_uuid' => $user->uuid,
            'detail_tenant_id' => $detailTenantId,
        ]);

        // 1. Add user's primary tenant from user_details
        if ($detailTenantId) {
            $primaryTenant = Tenant::on('mysql')->find($detailTenantId);
            if ($primaryTenant) {
                $tenants->push([
                    'id' => $primaryTenant->id,
                    'name' => $primaryTenant->name,
                    'is_primary' => true,
                    'is_owner' => $primaryTenant->owner_id === $user->uuid,
                    'role' => $user->detail?->role ?? 'staff',
                ]);
                $seenIds[] = $primaryTenant->id;
            }
        }

        // 2. Add tenant owned by this user (owner_id = user's uuid)
        $ownedTenant = Tenant::on('mysql')->where('owner_id', $user->uuid)->first();
        if ($ownedTenant && !in_array($ownedTenant->id, $seenIds)) {
            $tenants->push([
                'id' => $ownedTenant->id,
                'name' => $ownedTenant->name,
                'is_primary' => true,
                'is_owner' => true,
                'role' => 'owner',
            ]);
            $seenIds[] = $ownedTenant->id;
        }

        // 3. Add additional accessible tenants via tenant_user pivot
        try {
            $pivotTenants = Tenant::on('mysql')
                ->join('tenant_user', 'tenants.id', '=', 'tenant_user.tenant_id')
                ->where('tenant_user.user_id', $user->uuid)
                ->select('tenants.*', 'tenant_user.role as pivot_role')
                ->get();

            foreach ($pivotTenants as $tenant) {
                if (in_array($tenant->id, $seenIds)) {
                    continue;
                }
                $tenants->push([
                    'id' => $tenant->id,
                    'name' => $tenant->name,
                    'is_primary' => false,
                    'is_owner' => $tenant->owner_id === $user->uuid,
                    'role' => $tenant->pivot_role ?? 'employee',
                ]);
                $seenIds[] = $tenant->id;
            }
        } catch (\Throwable $e) {
            \Log::warning('[TenantController] tenant_user pivot query failed', [
                'error' => $e->getMessage(),
            ]);
        }

        \Log::info('[TenantController] tenants found', [
            'count' => $tenants->count(),
            'tenant_ids' => $tenants->pluck('id')->toArray(),
        ]);

        return response()->json([
            'success' => true,
            'tenants' => $tenants->values(),
        ]);
    }
}
