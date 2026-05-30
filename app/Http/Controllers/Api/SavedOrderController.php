<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SavedOrder;
use App\Models\Table;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SavedOrderController extends Controller
{
    public function index(Request $request)
    {
        $storeId = $request->query('store_id');
        
        if (!$storeId) {
            return response()->json(['message' => 'Store ID is required'], 400);
        }

        Log::info('Fetching saved orders', [
            'store_id' => $storeId,
            'user_id' => Auth::user()?->uuid ?: Auth::user()?->id,
            'tenant_id' => Auth::user()->tenant_id ?? null,
        ]);

        $user = Auth::user();
        Log::info('Debug SavedOrders', [
            'request_store_id' => $storeId,
            'user_id' => $user->uuid ?: $user->id,
            'user_tenant_id' => $user->tenant_id,
        ]);

        // Check count without scopes
        $allCount = SavedOrder::withoutGlobalScopes()->where('store_id', $storeId)->count();
        Log::info('Total saved orders in DB for this store (ignoring tenant)', ['count' => $allCount]);

        $savedOrders = SavedOrder::byStore($storeId)
            ->with(['table', 'customerType'])
            ->latest()
            ->get();
            
        Log::info('Saved orders fetched with scopes', ['count' => $savedOrders->count()]);

        return response()->json(['data' => $savedOrders]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'store_id' => 'required|exists:stores,id',
            'name' => 'required|string|max:255',
            'items' => 'required|array',
            'total' => 'required|numeric',
            'order_type' => 'nullable|string|in:dine-in,takeaway',
            'table_id' => 'nullable|exists:tables,id',
            'customer_type_id' => 'nullable|exists:customer_types,id',
            'notes' => 'nullable|string',
        ]);

        Log::info('Creating saved order', [
            'store_id' => $request->store_id,
            'user_id' => Auth::user()?->uuid ?: Auth::user()?->id,
            'tenant_id' => Auth::user()->tenant_id ?? null,
        ]);

        $user = Auth::user();
        $userKey = (string) ($user->uuid ?: $user->id);

        $savedOrder = DB::transaction(function () use ($request, $userKey) {
            if ($request->order_type === 'dine-in' && $request->table_id) {
                Table::where('id', $request->table_id)
                    ->where('store_id', $request->store_id)
                    ->update(['is_available' => false]);
            }

            return SavedOrder::create([
                'store_id' => $request->store_id,
                'user_id' => $userKey,
                'name' => $request->name,
                'items' => $request->items,
                'total' => $request->total,
                'order_type' => $request->order_type,
                'table_id' => $request->table_id,
                'customer_type_id' => $request->customer_type_id,
                'notes' => $request->notes,
            ]);
        });
        
        Log::info('Saved order created', ['id' => $savedOrder->id, 'tenant_id' => $savedOrder->tenant_id]);

        return response()->json(['data' => $savedOrder], 201);
    }

    public function destroy(Request $request, $id)
    {
        Log::info('Attempting to delete saved order', ['id' => $id, 'user_id' => Auth::user()?->uuid ?: Auth::user()?->id]);
        
        try {
            $savedOrder = SavedOrder::findOrFail($id);

            DB::transaction(function () use ($savedOrder, $request) {
                $shouldReleaseTable = $request->boolean('release_table', true);

                if ($shouldReleaseTable && $savedOrder->order_type === 'dine-in' && $savedOrder->table_id) {
                    $hasOtherSavedOrderForTable = SavedOrder::where('id', '!=', $savedOrder->id)
                        ->where('store_id', $savedOrder->store_id)
                        ->where('order_type', 'dine-in')
                        ->where('table_id', $savedOrder->table_id)
                        ->exists();

                    if (! $hasOtherSavedOrderForTable) {
                        Table::where('id', $savedOrder->table_id)
                            ->where('store_id', $savedOrder->store_id)
                            ->update(['is_available' => true]);
                    }
                }

                $savedOrder->delete();
            });

            Log::info('Saved order deleted successfully', ['id' => $id]);
            return response()->json(['message' => 'Saved order deleted successfully']);
        } catch (\Exception $e) {
            Log::error('Failed to delete saved order', ['id' => $id, 'error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to delete saved order'], 500);
        }
    }
}
