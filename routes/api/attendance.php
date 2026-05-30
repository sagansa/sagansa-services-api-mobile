<?php

use App\Http\Controllers\Api\PresenceController;
use App\Http\Controllers\Api\ShiftStoreController;
use Illuminate\Support\Facades\Route;

// APIs owned by mobiles/attendance.
Route::middleware(['auth:sanctum', 'active.tenant'])->group(function () {
    Route::get('/attendance', [PresenceController::class, 'index']);
    Route::get('/attendance/history', [PresenceController::class, 'history']);
    Route::post('/attendance/checkin', [PresenceController::class, 'checkIn']);
    Route::post('/attendance/checkout', [PresenceController::class, 'checkOut']);
    Route::get('/attendance/{attendanceId}', [PresenceController::class, 'show']);

    Route::get('/shift-stores', [ShiftStoreController::class, 'index']);

    Route::get('/leave-requests', [PresenceController::class, 'leaveRequests']);
    Route::post('/leave-requests', [PresenceController::class, 'submitLeaveRequest']);
    Route::get('/leave-requests/{leaveRequestId}', [PresenceController::class, 'showLeaveRequest']);
});
