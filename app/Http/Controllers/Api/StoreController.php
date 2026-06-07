<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Store;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class StoreController extends Controller
{
    /**
     * Get stores for the authenticated user's tenant
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        
        if (!$user->tenant_id) {
            return response()->json([
                'success' => false,
                'message' => 'User does not belong to any tenant'
            ], 403);
        }

        $activeTenantId = $request->active_tenant_id ?? $user->tenant_id;
        
        // Get stores associated with accessible tenants
        $stores = Store::withoutGlobalScope('tenant')
            ->where('tenant_id', $activeTenantId)
            ->with(['paymentMethods' => function($query) {
                $query->where('is_active', true);
            }, 'tenant:id,name']) // Load tenant name
            ->select([
                'id',
                'tenant_id',
                'name',
                'nickname',
                'email',
                'status',
                'radius',
                'latitude',
                'longitude',
                'tax_rate',
                'tax_name',
                'tax_type',
                'service_charge_type',
                'service_charge_rate',
                'service_charge_amount',
                'receipt_header',
                'receipt_footer',
                'email_receipt_logo',
                'print_receipt_logo',
                'address',
                'phone',
            ])
            ->when($request->has('status'), function ($query) use ($request) {
                return $query->where('status', $request->status);
            })
            ->orderBy('name')
            ->get()
            ->map(function ($store) {
                $store->tenant_name = $store->tenant ? $store->tenant->name : null;
                unset($store->tenant); // Clean up response
                return $store;
            });

        return response()->json([
            'success' => true,
            'data' => $stores,
            'meta' => [
                'total' => $stores->count(),
                'tenant_id' => $activeTenantId,
                'accessible_tenants' => [$activeTenantId],
            ]
        ]);
    }

    /**
     * Get a specific store
     */
    public function show(Request $request, string $storeId): JsonResponse
    {
        $user = $request->user();
        
        $store = Store::where('id', $storeId)
            ->where('tenant_id', $user->tenant_id)
            ->with(['paymentMethods' => function($query) {
                $query->where('is_active', true);
            }])
            ->select([
                'id',
                'tenant_id',
                'name',
                'nickname',
                'email',
                'status',
                'radius',
                'latitude',
                'longitude',
                'tax_rate',
                'tax_name',
                'tax_type',
                'service_charge_type',
                'service_charge_rate',
                'service_charge_amount',
                'receipt_header',
                'receipt_footer',
                'email_receipt_logo',
                'print_receipt_logo',
                'address',
                'phone',
            ])
            ->first();

        if (!$store) {
            return response()->json([
                'success' => false,
                'message' => 'Store not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $store
        ]);
    }

    /**
     * Create a new store
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
            'nickname' => 'nullable|string|max:255',

            'email' => 'nullable|email|max:255',
            'status' => 'nullable|in:active,inactive',
            'radius' => 'nullable|integer|min:0|max:10000',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'tax_rate' => 'nullable|numeric|min:0|max:100',
            'tax_name' => 'nullable|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $store = Store::create([
            'tenant_id' => $user->tenant_id,
            'name' => $request->name,
            'nickname' => $request->nickname,

            'email' => $request->email,
            'status' => $request->status ?? 'active',
            'radius' => $request->radius ?? 100,
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
            'tax_rate' => $request->tax_rate ?? 0,
            'tax_name' => $request->tax_name ?? 'Pajak',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Store created successfully',
            'data' => $store
        ], 201);
    }

    /**
     * Update a store
     */
    public function update(Request $request, string $storeId): JsonResponse
    {
        $user = $request->user();
        
        $store = Store::where('id', $storeId)
            ->where('tenant_id', $user->tenant_id)
            ->first();

        if (!$store) {
            return response()->json([
                'success' => false,
                'message' => 'Store not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'nickname' => 'nullable|string|max:255',

            'email' => 'nullable|email|max:255',
            'status' => 'nullable|in:active,inactive',
            'radius' => 'nullable|integer|min:0|max:10000',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'tax_rate' => 'nullable|numeric|min:0|max:100',
            'tax_name' => 'nullable|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $store->update(array_merge(
            $request->only([
                'name', 'nickname', 'email', 'status', 'radius', 'latitude', 'longitude', 'tax_rate', 'tax_name', 'service_charge_rate', 'service_charge_amount'
            ]),
            [
                'tax_type' => $request->tax_type ?? 'exclusive',
                'service_charge_type' => $request->service_charge_type ?? 'percentage',
            ]
        ));

        return response()->json([
            'success' => true,
            'message' => 'Store updated successfully',
            'data' => $store
        ]);
    }
}
