<?php

use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\CustomerTypeController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PaymentMethodController;
use App\Http\Controllers\Api\PrinterController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\RefundController;
use App\Http\Controllers\Api\SavedOrderController;
use App\Http\Controllers\Api\ShiftController;
use App\Http\Controllers\Api\StoreController;
use App\Http\Controllers\Api\TableController;
use Illuminate\Support\Facades\Route;

// APIs owned by mobiles/point-of-sale.
Route::middleware(['auth:sanctum', 'active.tenant'])->group(function () {
    // Store management for POS setup.
    Route::post('/stores', [StoreController::class, 'store']);
    Route::put('/stores/{storeId}', [StoreController::class, 'update']);

    // Tables and customer types used in checkout flow.
    Route::get('/stores/{storeId}/tables', [TableController::class, 'index']);
    Route::post('/stores/{storeId}/tables', [TableController::class, 'store']);
    Route::put('/tables/{id}', [TableController::class, 'update']);
    Route::delete('/tables/{id}', [TableController::class, 'destroy']);

    Route::get('/stores/{storeId}/customer-types', [CustomerTypeController::class, 'index']);
    Route::post('/stores/{storeId}/customer-types', [CustomerTypeController::class, 'store']);
    Route::put('/customer-types/{id}', [CustomerTypeController::class, 'update']);
    Route::delete('/customer-types/{id}', [CustomerTypeController::class, 'destroy']);

    // POS catalog.
    Route::get('/categories', [CategoryController::class, 'index']);
    Route::get('/products', [ProductController::class, 'index']);
    Route::post('/products', [ProductController::class, 'store']);
    Route::get('/products/{productId}', [ProductController::class, 'show']);
    Route::put('/products/{productId}', [ProductController::class, 'update']);
    Route::delete('/products/{productId}', [ProductController::class, 'destroy']);

    // POS order lifecycle.
    Route::get('/orders', [OrderController::class, 'index']);
    Route::post('/orders', [OrderController::class, 'store']);
    Route::get('/orders/{orderId}', [OrderController::class, 'show']);
    Route::put('/orders/{orderId}', [OrderController::class, 'update']);

    Route::get('/orders/{order}/refund-eligibility', [RefundController::class, 'checkEligibility']);
    Route::post('/orders/{order}/refund', [RefundController::class, 'store']);
    Route::get('/refunds', [RefundController::class, 'index']);
    Route::get('/refunds/{refund}', [RefundController::class, 'show']);
    Route::post('/refunds/{refund}/approve', [RefundController::class, 'approve']);
    Route::post('/refunds/{refund}/reject', [RefundController::class, 'reject']);

    // Saved carts/orders.
    Route::get('/saved-orders', [SavedOrderController::class, 'index']);
    Route::post('/saved-orders', [SavedOrderController::class, 'store']);
    Route::delete('/saved-orders/{id}', [SavedOrderController::class, 'destroy']);

    // Payment display helpers.
    Route::get('/payment-methods/{id}/qris', [PaymentMethodController::class, 'qris']);

    // Printers and print jobs.
    Route::get('/printers', [PrinterController::class, 'index']);
    Route::get('/printers/{printerId}', [PrinterController::class, 'show']);
    Route::post('/printers', [PrinterController::class, 'store']);
    Route::put('/printers/{printerId}', [PrinterController::class, 'update']);
    Route::delete('/printers/{printerId}', [PrinterController::class, 'destroy']);
    Route::post('/printer-jobs', [PrinterController::class, 'createJob']);
    Route::get('/printer-jobs/{jobId}', [PrinterController::class, 'getJobStatus']);

    // Cashier shifts.
    Route::get('/shifts/current', [ShiftController::class, 'current']);
    Route::post('/shifts/open', [ShiftController::class, 'open']);
    Route::post('/shifts/close', [ShiftController::class, 'close']);

    // Customer records selected during checkout.
    Route::get('/customers', [CustomerController::class, 'index']);
    Route::post('/customers', [CustomerController::class, 'store']);
    Route::get('/customers/{id}', [CustomerController::class, 'show']);
    Route::put('/customers/{id}', [CustomerController::class, 'update']);
    Route::delete('/customers/{id}', [CustomerController::class, 'destroy']);
});
