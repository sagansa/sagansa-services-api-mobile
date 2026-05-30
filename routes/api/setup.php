<?php

use App\Http\Controllers\SetupController;
use Illuminate\Support\Facades\Route;

// Development-only setup helpers.
Route::prefix('setup')->group(function () {
    Route::get('/status', [SetupController::class, 'migrationStatus']);
    Route::post('/migrate', [SetupController::class, 'runMigrations']);
    Route::post('/seed', [SetupController::class, 'runSeeders']);
    Route::post('/fresh', [SetupController::class, 'freshMigration']);
    Route::post('/rollback', [SetupController::class, 'rollbackMigration']);
    Route::post('/complete', [SetupController::class, 'completeSetup']);
});
