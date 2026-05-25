<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Shift;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ShiftController extends Controller
{
    public function current(Request $request): JsonResponse
    {
        $user = Auth::user();
        $userId = $user->uuid;
        $storeId = $request->query('store_id');

        $query = Shift::where('user_id', $userId)
            ->where('status', 'open');

        if ($storeId) {
            $query->where('store_id', $storeId);
        }

        $shift = $query->first();

        return response()->json([
            'success' => true,
            'data' => $shift
        ]);
    }

    public function open(Request $request): JsonResponse
    {
        $request->validate([
            'store_id' => 'required|exists:stores,id',
            'start_cash' => 'required|numeric|min:0',
        ]);

        $user = Auth::user();
        $userId = $user->uuid;

        // Check if user already has an open shift ANYWHERE (requirement: locked to store)
        // Or strictly check if they have an open shift at THIS store?
        // Requirement 4: "ketika open shift sudah dimasukkan, maka tidak bisa lagi mengubah store karena terikat dengan store tersebut sampai dilakukan close shift"
        // This implies a user can only have ONE open shift at a time across ALL stores.
        
        $existingShift = Shift::where('user_id', $userId)
            ->where('status', 'open')
            ->first();

        if ($existingShift) {
            return response()->json([
                'success' => false,
                'message' => 'You already have an open shift. Please close it first.',
                'data' => $existingShift
            ], 400);
        }

        $shift = Shift::create([
            'store_id' => $request->store_id,
            'user_id' => $userId,
            'start_cash' => $request->start_cash,
            'status' => 'open',
            'started_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'data' => $shift,
            'message' => 'Shift opened successfully'
        ], 201);
    }

    public function close(Request $request): JsonResponse
    {
        $request->validate([
            'end_cash' => 'required|numeric|min:0',
        ]);

        $user = Auth::user();
        $userId = $user->uuid;
        
        // Find the current open shift for this user
        $shift = Shift::where('user_id', $userId)
            ->where('status', 'open')
            ->first();

        if (!$shift) {
            return response()->json([
                'success' => false,
                'message' => 'No active shift found to close.'
            ], 404);
        }

        $shift->update([
            'end_cash' => $request->end_cash,
            'status' => 'closed',
            'ended_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'data' => $shift,
            'message' => 'Shift closed successfully'
        ]);
    }
}
