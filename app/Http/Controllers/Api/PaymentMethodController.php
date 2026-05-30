<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PaymentMethod;
use App\Services\QrisService;
use Illuminate\Http\Request;
use InvalidArgumentException;

class PaymentMethodController extends Controller
{
    public function qris(Request $request, string $id, QrisService $qrisService)
    {
        $paymentMethod = PaymentMethod::findOrFail($id);

        if ($paymentMethod->type !== 'qris') {
            return response()->json([
                'message' => 'Payment method is not QRIS.',
            ], 422);
        }

        $details = $paymentMethod->details ?? [];
        $payload = $details['qris_payload'] ?? null;

        if (! $payload) {
            return response()->json([
                'message' => 'QRIS payload is not available. Upload a readable QRIS image first.',
            ], 422);
        }

        $validated = $request->validate([
            'amount' => 'required|numeric|min:1',
        ]);

        try {
            $dynamicPayload = $qrisService->withAmount($payload, $validated['amount']);
            $svg = $qrisService->toSvg($dynamicPayload);
        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response($svg, 200, [
            'Content-Type' => 'image/svg+xml',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
            'X-QRIS-Payload' => $dynamicPayload,
        ]);
    }
}
