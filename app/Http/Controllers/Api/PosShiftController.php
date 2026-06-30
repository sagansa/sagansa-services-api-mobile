<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PosShiftAuditLog;
use App\Models\PosShiftSession;
use App\Models\PosShiftStockItem;
use App\Models\PosShiftStockMovement;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Http\Exceptions\HttpResponseException;
use Throwable;

class PosShiftController extends Controller
{
    public function active(Request $request): JsonResponse
    {
        $request->validate([
            'store_id' => ['required', 'uuid', 'exists:stores,id'],
        ]);

        $shift = $this->activeShiftForStore((string) $request->query('store_id'));

        return response()->json([
            'success' => true,
            'data' => $shift ? $this->serializeShift($shift) : null,
        ]);
    }

    public function openingProducts(Request $request): JsonResponse
    {
        $request->validate([
            'store_id' => ['required', 'uuid', 'exists:stores,id'],
        ]);

        $products = $this->trackedProducts((string) $request->query('store_id'))->map(function (Product $product) use ($request) {
            $previousItem = PosShiftStockItem::query()
                ->where('product_id', $product->id)
                ->whereHas('shiftSession', function ($query) use ($request) {
                    $query->where('store_id', $request->query('store_id'))
                        ->whereIn('status', [PosShiftSession::STATUS_CLOSED, PosShiftSession::STATUS_FORCE_CLOSED]);
                })
                ->latest('updated_at')
                ->first();

            return [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'stock' => (int) $product->stock,
                'remaining' => (bool) $product->remaining,
                'previous_closing_stock' => $previousItem?->actual_closing_stock,
            ];
        })->values();

        return response()->json([
            'success' => true,
            'data' => $products,
        ]);
    }

    public function open(Request $request): JsonResponse
    {
        if (! $this->supportsShiftStockControl()) {
            return response()->json([
                'success' => false,
                'message' => 'POS shift stock control tables are not available. Run the latest ops migrations first.',
            ], 409);
        }

        $validated = $request->validate([
            'store_id' => ['required', 'uuid', 'exists:stores,id'],
            'business_date' => ['nullable', 'date'],
            'opening_note' => ['nullable', 'string'],
            'items' => ['sometimes', 'array'],
            'items.*.product_id' => ['required', 'uuid', 'exists:products,id'],
            'items.*.opening_stock' => ['required', 'integer', 'min:0'],
            'items.*.opening_variance_note' => ['nullable', 'string'],
        ]);

        $storeId = (string) $validated['store_id'];
        $businessDate = Carbon::parse($validated['business_date'] ?? now())->toDateString();
        $user = Auth::user();
        $userId = $user?->uuid ?: $user?->id;

        $trackedProductIds = $this->trackedProducts($storeId)->pluck('id')->map(fn ($id) => (string) $id)->all();
        $payloadItems = collect($validated['items'] ?? [])->keyBy(fn ($item) => (string) $item['product_id']);

        $missingProductIds = collect($trackedProductIds)->diff($payloadItems->keys());
        if ($missingProductIds->isNotEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'All tracked products must have opening stock.',
                'errors' => ['items' => ['Missing tracked products: ' . $missingProductIds->implode(', ')]],
            ], 422);
        }

        try {
            $shift = DB::transaction(function () use ($storeId, $businessDate, $validated, $payloadItems, $trackedProductIds, $user, $userId) {
            $existingOpenShift = PosShiftSession::query()
                ->where('store_id', $storeId)
                ->where('status', PosShiftSession::STATUS_OPEN)
                ->lockForUpdate()
                ->first();

            if ($existingOpenShift) {
                return $existingOpenShift;
            }

            $shift = PosShiftSession::create([
                'tenant_id' => $user?->tenant_id,
                'store_id' => $storeId,
                'opened_by_user_id' => $userId,
                'opened_at' => now(),
                'business_date' => $businessDate,
                'status' => PosShiftSession::STATUS_OPEN,
                'opening_note' => $validated['opening_note'] ?? null,
            ]);

            foreach ($trackedProductIds as $productId) {
                $payload = $payloadItems->get($productId);
                $previousItem = PosShiftStockItem::query()
                    ->where('product_id', $productId)
                    ->whereHas('shiftSession', function ($query) use ($storeId) {
                        $query->where('store_id', $storeId)
                            ->whereIn('status', [PosShiftSession::STATUS_CLOSED, PosShiftSession::STATUS_FORCE_CLOSED]);
                    })
                    ->latest('updated_at')
                    ->first();
                $openingStock = (int) $payload['opening_stock'];
                $openingVariance = $previousItem?->actual_closing_stock !== null
                    ? $openingStock - (int) $previousItem->actual_closing_stock
                    : null;

                if ($openingVariance !== null && $openingVariance !== 0 && empty($payload['opening_variance_note'])) {
                    throw new HttpResponseException(response()->json([
                        'success' => false,
                        'message' => 'Opening variance note is required when opening stock differs from previous close.',
                        'errors' => ['items' => ["Opening variance note required for product {$productId}."]],
                    ], 422));
                }

                $stockItemPayload = [
                    'shift_session_id' => $shift->id,
                    'product_id' => $productId,
                    'opening_stock' => $openingStock,
                    'addition_stock' => 0,
                    'sold_quantity' => 0,
                    'expected_closing_stock' => $openingStock,
                    'opening_variance' => $openingVariance,
                    'opening_variance_note' => $payload['opening_variance_note'] ?? null,
                ];

                if ($this->supportsAdjustmentStock()) {
                    $stockItemPayload['adjustment_stock'] = 0;
                }

                PosShiftStockItem::create($stockItemPayload);

                PosShiftStockMovement::create([
                    'shift_session_id' => $shift->id,
                    'product_id' => $productId,
                    'type' => PosShiftStockMovement::TYPE_OPENING,
                    'quantity' => $openingStock,
                    'note' => $payload['opening_variance_note'] ?? null,
                    'created_by_user_id' => $userId,
                ]);
            }

            PosShiftAuditLog::create([
                'shift_session_id' => $shift->id,
                'action' => 'open',
                'after_payload' => $shift->load('stockItems')->toArray(),
                'created_by_user_id' => $userId,
            ]);

            return $shift;
            });
        } catch (HttpResponseException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            Log::error('Failed to open POS stock shift.', [
                'store_id' => $storeId,
                'business_date' => $businessDate,
                'user_id' => $userId,
                'tracked_product_count' => count($trackedProductIds),
                'payload_item_count' => $payloadItems->count(),
                'exception' => get_class($exception),
                'message' => $exception->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => config('app.debug')
                    ? 'Failed to open POS stock shift: ' . $exception->getMessage()
                    : 'Failed to open POS stock shift. Please contact admin.',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Shift opened successfully.',
            'data' => $this->serializeShift($shift->refresh()),
        ], 201);
    }

    public function stock(Request $request, string $shift): JsonResponse
    {
        $session = PosShiftSession::with(['stockItems.product', 'movements'])
            ->findOrFail($shift);

        return response()->json([
            'success' => true,
            'data' => $this->serializeShift($session),
        ]);
    }

    public function addStock(Request $request, string $shift): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'uuid', 'exists:products,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'note' => ['required', 'string'],
        ]);

        $user = Auth::user();
        $userId = $user?->uuid ?: $user?->id;

        $session = DB::transaction(function () use ($shift, $validated, $userId) {
            $session = PosShiftSession::query()->lockForUpdate()->findOrFail($shift);
            if (! $session->isOpen()) {
                abort(response()->json([
                    'success' => false,
                    'message' => 'Cannot add stock to a closed shift.',
                ], 422));
            }

            $item = PosShiftStockItem::query()
                ->where('shift_session_id', $session->id)
                ->where('product_id', $validated['product_id'])
                ->lockForUpdate()
                ->firstOrFail();

            $before = $item->toArray();
            $item->addition_stock = (int) $item->addition_stock + (int) $validated['quantity'];
            $item->recalculateExpected();
            $item->save();

            PosShiftStockMovement::create([
                'shift_session_id' => $session->id,
                'product_id' => $item->product_id,
                'type' => PosShiftStockMovement::TYPE_ADDITION,
                'quantity' => (int) $validated['quantity'],
                'note' => $validated['note'],
                'created_by_user_id' => $userId,
            ]);

            PosShiftAuditLog::create([
                'shift_session_id' => $session->id,
                'action' => 'stock_addition',
                'before_payload' => $before,
                'after_payload' => $item->fresh()->toArray(),
                'reason' => $validated['note'],
                'created_by_user_id' => $userId,
            ]);

            return $session;
        });

        return response()->json([
            'success' => true,
            'message' => 'Stock addition saved.',
            'data' => $this->serializeShift($session->refresh()),
        ]);
    }

    public function adjustStock(Request $request, string $shift): JsonResponse
    {
        if (! $this->supportsAdjustmentStock()) {
            return response()->json([
                'success' => false,
                'message' => 'Stock adjustment is not available until the latest stock migration is applied.',
            ], 409);
        }

        $validated = $request->validate([
            'product_id' => ['required', 'uuid', 'exists:products,id'],
            'quantity' => ['required', 'integer', 'not_in:0'],
            'reason' => ['required', 'string', 'max:64'],
            'note' => ['required', 'string'],
        ]);

        $user = Auth::user();
        $userId = $user?->uuid ?: $user?->id;

        $session = DB::transaction(function () use ($shift, $validated, $userId) {
            $session = PosShiftSession::query()->lockForUpdate()->findOrFail($shift);
            if (! $session->isOpen()) {
                abort(response()->json([
                    'success' => false,
                    'message' => 'Cannot adjust stock on a closed shift.',
                ], 422));
            }

            $item = PosShiftStockItem::query()
                ->where('shift_session_id', $session->id)
                ->where('product_id', $validated['product_id'])
                ->lockForUpdate()
                ->firstOrFail();

            $quantity = (int) $validated['quantity'];
            $before = $item->toArray();
            $item->adjustment_stock = (int) $item->adjustment_stock + $quantity;
            $item->recalculateExpected();

            if ((int) $item->expected_closing_stock < 0) {
                abort(response()->json([
                    'success' => false,
                    'message' => 'Stock adjustment cannot make expected closing stock negative.',
                ], 422));
            }

            $item->save();

            $note = '[' . $validated['reason'] . '] ' . $validated['note'];
            PosShiftStockMovement::create([
                'shift_session_id' => $session->id,
                'product_id' => $item->product_id,
                'type' => PosShiftStockMovement::TYPE_CORRECTION,
                'quantity' => $quantity,
                'note' => $note,
                'created_by_user_id' => $userId,
            ]);

            PosShiftAuditLog::create([
                'shift_session_id' => $session->id,
                'action' => 'stock_adjustment',
                'before_payload' => $before,
                'after_payload' => $item->fresh()->toArray(),
                'reason' => $note,
                'created_by_user_id' => $userId,
            ]);

            return $session;
        });

        return response()->json([
            'success' => true,
            'message' => 'Stock adjustment saved.',
            'data' => $this->serializeShift($session->refresh()),
        ]);
    }

    public function close(Request $request, string $shift): JsonResponse
    {
        $validated = $request->validate([
            'closing_note' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'uuid', 'exists:products,id'],
            'items.*.actual_closing_stock' => ['required', 'integer', 'min:0'],
            'items.*.closing_note' => ['nullable', 'string'],
        ]);

        $user = Auth::user();
        $userId = $user?->uuid ?: $user?->id;

        $session = DB::transaction(function () use ($shift, $validated, $userId) {
            $session = PosShiftSession::query()->lockForUpdate()->findOrFail($shift);
            if (! $session->isOpen()) {
                abort(response()->json([
                    'success' => false,
                    'message' => 'Shift is already closed.',
                ], 422));
            }

            $payloadItems = collect($validated['items'])->keyBy(fn ($item) => (string) $item['product_id']);
            $stockItems = PosShiftStockItem::query()
                ->where('shift_session_id', $session->id)
                ->lockForUpdate()
                ->get();

            $missingProductIds = $stockItems->pluck('product_id')->map(fn ($id) => (string) $id)->diff($payloadItems->keys());
            if ($missingProductIds->isNotEmpty()) {
                abort(response()->json([
                    'success' => false,
                    'message' => 'All tracked products must have closing stock.',
                    'errors' => ['items' => ['Missing products: ' . $missingProductIds->implode(', ')]],
                ], 422));
            }

            foreach ($stockItems as $item) {
                $payload = $payloadItems->get((string) $item->product_id);
                $item->recalculateExpected();
                $actual = (int) $payload['actual_closing_stock'];
                $variance = $actual - (int) $item->expected_closing_stock;

                if ($variance !== 0 && empty($payload['closing_note'])) {
                    abort(response()->json([
                        'success' => false,
                        'message' => 'Closing note is required when stock variance exists.',
                        'errors' => ['items' => ["Closing note required for product {$item->product_id}."]],
                    ], 422));
                }

                $item->actual_closing_stock = $actual;
                $item->variance = $variance;
                $item->closing_note = $payload['closing_note'] ?? null;
                $item->save();

                PosShiftStockMovement::create([
                    'shift_session_id' => $session->id,
                    'product_id' => $item->product_id,
                    'type' => PosShiftStockMovement::TYPE_CLOSING,
                    'quantity' => $actual,
                    'note' => $payload['closing_note'] ?? null,
                    'created_by_user_id' => $userId,
                ]);
            }

            $before = $session->toArray();
            $session->update([
                'closed_by_user_id' => $userId,
                'closed_at' => now(),
                'status' => PosShiftSession::STATUS_CLOSED,
                'closing_note' => $validated['closing_note'] ?? null,
            ]);

            PosShiftAuditLog::create([
                'shift_session_id' => $session->id,
                'action' => 'close',
                'before_payload' => $before,
                'after_payload' => $session->fresh(['stockItems'])->toArray(),
                'reason' => $validated['closing_note'] ?? null,
                'created_by_user_id' => $userId,
            ]);

            return $session;
        });

        return response()->json([
            'success' => true,
            'message' => 'Shift closed successfully.',
            'data' => $this->serializeShift($session->refresh()),
        ]);
    }

    private function activeShiftForStore(string $storeId): ?PosShiftSession
    {
        return PosShiftSession::with(['stockItems.product'])
            ->where('store_id', $storeId)
            ->where('status', PosShiftSession::STATUS_OPEN)
            ->first();
    }

    private function trackedProducts(string $storeId)
    {
        return Product::withoutGlobalScope('tenant')
            ->where('is_active', true)
            ->where('remaining', true)
            ->where(function ($query) {
                $query->whereNull('type')->orWhere('type', 'single');
            })
            ->whereHas('stores', function ($query) use ($storeId) {
                $query->where('stores.id', $storeId);
            })
            ->orderBy('name')
            ->get();
    }

    private function serializeShift(PosShiftSession $shift): array
    {
        $shift->loadMissing(['store', 'opener', 'closer', 'stockItems.product', 'movements']);

        return [
            'id' => $shift->id,
            'tenant_id' => $shift->tenant_id,
            'store_id' => $shift->store_id,
            'store' => $shift->store ? [
                'id' => $shift->store->id,
                'name' => $shift->store->name,
                'nickname' => $shift->store->nickname ?? null,
            ] : null,
            'opened_by_user_id' => $shift->opened_by_user_id,
            'closed_by_user_id' => $shift->closed_by_user_id,
            'opened_at' => $shift->opened_at?->toISOString(),
            'closed_at' => $shift->closed_at?->toISOString(),
            'business_date' => $shift->business_date?->toDateString(),
            'status' => $shift->status,
            'opening_note' => $shift->opening_note,
            'closing_note' => $shift->closing_note,
            'is_force_closed' => (bool) $shift->is_force_closed,
            'items' => $shift->stockItems->map(function (PosShiftStockItem $item) {
                return [
                    'id' => $item->id,
                    'product_id' => $item->product_id,
                    'product' => $item->product ? [
                        'id' => $item->product->id,
                        'name' => $item->product->name,
                        'sku' => $item->product->sku,
                    ] : null,
                    'opening_stock' => (int) $item->opening_stock,
                    'addition_stock' => (int) $item->addition_stock,
                    'adjustment_stock' => $this->supportsAdjustmentStock() ? (int) $item->adjustment_stock : 0,
                    'sold_quantity' => (int) $item->sold_quantity,
                    'expected_closing_stock' => (int) $item->expected_closing_stock,
                    'actual_closing_stock' => $item->actual_closing_stock !== null ? (int) $item->actual_closing_stock : null,
                    'variance' => $item->variance !== null ? (int) $item->variance : null,
                    'opening_variance' => $item->opening_variance !== null ? (int) $item->opening_variance : null,
                    'opening_variance_note' => $item->opening_variance_note,
                    'closing_note' => $item->closing_note,
                ];
            })->values(),
        ];
    }

    private function supportsAdjustmentStock(): bool
    {
        return Schema::hasColumn('pos_shift_stock_items', 'adjustment_stock');
    }

    private function supportsShiftStockControl(): bool
    {
        return Schema::hasTable('pos_shift_sessions')
            && Schema::hasTable('pos_shift_stock_items')
            && Schema::hasTable('pos_shift_stock_movements')
            && Schema::hasTable('pos_shift_audit_logs');
    }
}
