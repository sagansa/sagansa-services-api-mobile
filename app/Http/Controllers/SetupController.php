<?php

namespace App\Http\Controllers;

use App\Services\MigrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SetupController extends Controller
{
    protected $migrationService;

    public function __construct(MigrationService $migrationService)
    {
        $this->migrationService = $migrationService;
    }

    /**
     * Get migration status
     */
    public function migrationStatus(): JsonResponse
    {
        $result = $this->migrationService->getMigrationStatus();
        
        return response()->json($result);
    }

    /**
     * Run migrations
     */
    public function runMigrations(Request $request): JsonResponse
    {
        // Only allow in development environment
        if (app()->environment('production')) {
            return response()->json([
                'success' => false,
                'message' => 'Migration not allowed in production environment'
            ], 403);
        }

        $result = $this->migrationService->runMigrations();
        
        return response()->json($result);
    }

    /**
     * Run seeders
     */
    public function runSeeders(Request $request): JsonResponse
    {
        // Only allow in development environment
        if (app()->environment('production')) {
            return response()->json([
                'success' => false,
                'message' => 'Seeding not allowed in production environment'
            ], 403);
        }

        $result = $this->migrationService->runSeeders();
        
        return response()->json($result);
    }

    /**
     * Fresh migration (drop all tables and re-run)
     */
    public function freshMigration(Request $request): JsonResponse
    {
        // Only allow in development environment
        if (app()->environment('production')) {
            return response()->json([
                'success' => false,
                'message' => 'Fresh migration not allowed in production environment'
            ], 403);
        }

        // Require confirmation
        if (!$request->has('confirm') || $request->confirm !== 'yes') {
            return response()->json([
                'success' => false,
                'message' => 'Fresh migration requires confirmation. Add ?confirm=yes to proceed.'
            ], 400);
        }

        $result = $this->migrationService->freshMigration();
        
        return response()->json($result);
    }

    /**
     * Rollback last migration
     */
    public function rollbackMigration(Request $request): JsonResponse
    {
        // Only allow in development environment
        if (app()->environment('production')) {
            return response()->json([
                'success' => false,
                'message' => 'Rollback not allowed in production environment'
            ], 403);
        }

        $result = $this->migrationService->rollbackMigration();
        
        return response()->json($result);
    }

    /**
     * Complete setup (migrate + seed)
     */
    public function completeSetup(Request $request): JsonResponse
    {
        // Only allow in development environment
        if (app()->environment('production')) {
            return response()->json([
                'success' => false,
                'message' => 'Setup not allowed in production environment'
            ], 403);
        }

        // Run migrations
        $migrationResult = $this->migrationService->runMigrations();
        
        if (!$migrationResult['success']) {
            return response()->json($migrationResult);
        }

        // Run seeders
        $seederResult = $this->migrationService->runSeeders();
        
        return response()->json([
            'success' => true,
            'message' => 'Setup completed successfully',
            'migration' => $migrationResult,
            'seeder' => $seederResult
        ]);
    }
}