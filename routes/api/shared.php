<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\StoreController;
use App\Http\Controllers\Api\TenantController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// CORS preflight for clients that explicitly probe auth endpoints.
Route::options('/auth/{any}', function (Request $request) {
    $origin = $request->headers->get('Origin', '*');
    $requestHeaders = $request->headers->get('Access-Control-Request-Headers', '*');

    return response()->noContent(204)
        ->header('Access-Control-Allow-Origin', $origin)
        ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS')
        ->header('Access-Control-Allow-Headers', $requestHeaders)
        ->header('Access-Control-Allow-Credentials', 'false');
})->where('any', '.*');

// Shared authentication endpoints for POS and attendance clients.
Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login']);
    Route::post('register', [AuthController::class, 'register']);
    Route::get('invitations/{token}', [AuthController::class, 'showInvitation']);
    Route::post('invitations/{token}', [AuthController::class, 'completeInvitation']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('user', [AuthController::class, 'me']);
        Route::get('validate-token', [AuthController::class, 'validateToken']);
    });
});

// Shared protected data used by both POS and attendance.
Route::middleware(['auth:sanctum', 'active.tenant'])->group(function () {
    Route::get('/stores', [StoreController::class, 'index']);
    Route::get('/stores/{storeId}', [StoreController::class, 'show']);
    Route::get('/tenants/accessible', [TenantController::class, 'accessible']);
});
