<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ShiftStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShiftStoreController extends Controller
{
    /**
     * List shift stores for the authenticated tenant.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->tenant_id) {
            return response()->json([
                'success' => false,
                'message' => 'User does not belong to any tenant',
            ], 403);
        }

        $activeTenantId = $request->active_tenant_id ?? $user->tenant_id;

        $shiftStores = ShiftStore::withoutGlobalScope('tenant')
            ->where('tenant_id', $activeTenantId)
            ->select(['id', 'name', 'shift_start_time', 'shift_end_time', 'duration'])
            ->orderBy('shift_start_time')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $shiftStores,
            'meta' => [
                'total' => $shiftStores->count(),
                'tenant_id' => $activeTenantId,
                'accessible_tenants' => [$activeTenantId],
            ],
        ]);
    }
}
