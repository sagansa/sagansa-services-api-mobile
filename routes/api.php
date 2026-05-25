<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\StoreController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\RefundController;
use App\Http\Controllers\Api\TableController;
use App\Http\Controllers\Api\CustomerTypeController;
use App\Http\Controllers\Api\SavedOrderController;
use App\Http\Controllers\Api\PresenceController;
use App\Http\Controllers\Api\PrinterController;
use App\Http\Controllers\Api\ShiftStoreController;
use App\Http\Controllers\SetupController;
use Illuminate\Support\Facades\Route;

// Handle preflight OPTIONS requests for auth endpoints
Route::options('/auth/{any}', function (Illuminate\Http\Request $request) {
    $origin = $request->headers->get('Origin', '*');
    $requestHeaders = $request->headers->get('Access-Control-Request-Headers', '*');
    return response()->noContent(204)
        ->header('Access-Control-Allow-Origin', $origin)
        ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS')
        ->header('Access-Control-Allow-Headers', $requestHeaders)
        ->header('Access-Control-Allow-Credentials', 'false');
})->where('any', '.*');

// Authentication Routes
Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login']);
    Route::post('register', [AuthController::class, 'register']);
    Route::get('invitations/{token}', [AuthController::class, 'showInvitation']);
    Route::post('invitations/{token}', [AuthController::class, 'completeInvitation']);
    
    Route::post('invitations/{token}', [AuthController::class, 'completeInvitation']);
    
    // Guest Orders
    Route::post('/guest/orders', [\App\Http\Controllers\Api\GuestOrderController::class, 'store']);
    
    // Protected routes
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('user', [AuthController::class, 'me']);
        Route::get('validate-token', [AuthController::class, 'validateToken']);
    });
});

Route::middleware(['auth:sanctum', 'active.tenant'])->group(function () {
    // Stores
    Route::get('/stores', [StoreController::class, 'index']);
    Route::post('/stores', [StoreController::class, 'store']);
    Route::get('/stores/{storeId}', [StoreController::class, 'show']);
    Route::put('/stores/{storeId}', [StoreController::class, 'update']);
    
    // Tables
    Route::get('/stores/{storeId}/tables', [TableController::class, 'index']);
    Route::post('/stores/{storeId}/tables', [TableController::class, 'store']);
    Route::put('/tables/{id}', [TableController::class, 'update']);
    Route::delete('/tables/{id}', [TableController::class, 'destroy']);

    // Customer Types
    Route::get('/stores/{storeId}/customer-types', [CustomerTypeController::class, 'index']);
    Route::post('/stores/{storeId}/customer-types', [CustomerTypeController::class, 'store']);
    Route::put('/customer-types/{id}', [CustomerTypeController::class, 'update']);
    Route::delete('/customer-types/{id}', [CustomerTypeController::class, 'destroy']);

    // Categories
    Route::get('/categories', [\App\Http\Controllers\Api\CategoryController::class, 'index']);

    // Products
    Route::get('/products', [ProductController::class, 'index']);
    Route::post('/products', [ProductController::class, 'store']);
    Route::get('/products/{productId}', [ProductController::class, 'show']);
    Route::put('/products/{productId}', [ProductController::class, 'update']);
    Route::delete('/products/{productId}', [ProductController::class, 'destroy']);
    
    // Orders
    Route::get('/orders', [OrderController::class, 'index']);
    Route::post('/orders', [OrderController::class, 'store']);
    Route::get('/orders/{orderId}', [OrderController::class, 'show']);
    Route::put('/orders/{orderId}', [OrderController::class, 'update']);
    
    // Refunds
    Route::get('/orders/{order}/refund-eligibility', [RefundController::class, 'checkEligibility']);
    Route::post('/orders/{order}/refund', [RefundController::class, 'store']);
    Route::get('/refunds', [RefundController::class, 'index']);
    Route::get('/refunds/{refund}', [RefundController::class, 'show']);
    
    // Saved Orders
    Route::get('/saved-orders', [SavedOrderController::class, 'index']);
    Route::post('/saved-orders', [SavedOrderController::class, 'store']);
    Route::delete('/saved-orders/{id}', [SavedOrderController::class, 'destroy']);
    
    // Attendance (Presence)
    Route::get('/attendance', [PresenceController::class, 'index']);
    Route::post('/attendance/checkin', [PresenceController::class, 'checkIn']);
    Route::post('/attendance/checkout', [PresenceController::class, 'checkOut']);
    Route::get('/attendance/{attendanceId}', [PresenceController::class, 'show']);
    Route::get('/attendance/history', [PresenceController::class, 'history']);
    Route::get('/shift-stores', [ShiftStoreController::class, 'index']);
    
    // Leave Requests
    Route::get('/leave-requests', [PresenceController::class, 'leaveRequests']);
    Route::post('/leave-requests', [PresenceController::class, 'submitLeaveRequest']);
    Route::get('/leave-requests/{leaveRequestId}', [PresenceController::class, 'showLeaveRequest']);
    
    // Printers
    Route::get('/printers', [PrinterController::class, 'index']);
    Route::get('/printers/{printerId}', [PrinterController::class, 'show']);
    Route::post('/printers', [PrinterController::class, 'store']);
    Route::put('/printers/{printerId}', [PrinterController::class, 'update']);
    Route::delete('/printers/{printerId}', [PrinterController::class, 'destroy']);

    // Shifts
    Route::get('/shifts/current', [\App\Http\Controllers\Api\ShiftController::class, 'current']);
    Route::post('/shifts/open', [\App\Http\Controllers\Api\ShiftController::class, 'open']);
    Route::post('/shifts/close', [\App\Http\Controllers\Api\ShiftController::class, 'close']);
    Route::post('/printer-jobs', [PrinterController::class, 'createJob']);
    Route::get('/printer-jobs/{jobId}', [PrinterController::class, 'getJobStatus']);

    // Customers
    Route::get('/customers', [\App\Http\Controllers\Api\CustomerController::class, 'index']);
    Route::post('/customers', [\App\Http\Controllers\Api\CustomerController::class, 'store']);
    Route::get('/customers/{id}', [\App\Http\Controllers\Api\CustomerController::class, 'show']);
    Route::put('/customers/{id}', [\App\Http\Controllers\Api\CustomerController::class, 'update']);
    Route::delete('/customers/{id}', [\App\Http\Controllers\Api\CustomerController::class, 'destroy']);

    // Tenants - get accessible tenants for login selection
    Route::get('/tenants/accessible', [\App\Http\Controllers\Api\TenantController::class, 'accessible']);
});

// Public Discovery Routes (For web-order/menu)
Route::prefix('public')->group(function () {
    Route::get('/tenants', [\App\Http\Controllers\Api\PublicController::class, 'tenants']);
    Route::get('/stores', [\App\Http\Controllers\Api\PublicController::class, 'stores']);
    Route::get('/products', [\App\Http\Controllers\Api\PublicController::class, 'products']);
});

// Setup routes (development only)
Route::prefix('setup')->group(function () {
    Route::get('/status', [SetupController::class, 'migrationStatus']);
    Route::post('/migrate', [SetupController::class, 'runMigrations']);
    Route::post('/seed', [SetupController::class, 'runSeeders']);
    Route::post('/fresh', [SetupController::class, 'freshMigration']);
    Route::post('/rollback', [SetupController::class, 'rollbackMigration']);
    Route::post('/complete', [SetupController::class, 'completeSetup']);
});
