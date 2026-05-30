<?php

use App\Http\Controllers\Api\GuestOrderController;
use App\Http\Controllers\Api\PaymentMethodController;
use App\Http\Controllers\Api\PublicController;
use Illuminate\Support\Facades\Route;

// Public APIs owned by apps/menu for customer self-order.
Route::prefix('public')->group(function () {
    Route::get('/tenants', [PublicController::class, 'tenants']);
    Route::get('/stores', [PublicController::class, 'stores']);
    Route::get('/products', [PublicController::class, 'products']);
    Route::get('/payment-methods', [PublicController::class, 'paymentMethods']);
    Route::get('/payment-methods/{id}/qris', [PaymentMethodController::class, 'qris']);
});

Route::get('/guest/orders', [GuestOrderController::class, 'index']);
Route::post('/guest/orders', [GuestOrderController::class, 'store']);
