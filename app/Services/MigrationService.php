<?php

namespace App\Services;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MigrationService
{
    /**
     * Run database migrations
     */
    public function runMigrations(): array
    {
        try {
            // Check if database exists
            if (!$this->checkDatabaseConnection()) {
                return [
                    'success' => false,
                    'message' => 'Database connection failed. Please check your database configuration.'
                ];
            }

            // Run migrations
            Artisan::call('migrate', [
                '--force' => true,
                '--step' => true,
            ]);

            $output = Artisan::output();

            return [
                'success' => true,
                'message' => 'Migrations completed successfully',
                'output' => $output
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Migration failed: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Run database seeders
     */
    public function runSeeders(): array
    {
        try {
            Artisan::call('db:seed', [
                '--force' => true,
            ]);

            $output = Artisan::output();

            return [
                'success' => true,
                'message' => 'Seeders completed successfully',
                'output' => $output
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Seeding failed: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Reset and re-run all migrations
     */
    public function freshMigration(): array
    {
        try {
            // Drop all tables and re-run migrations
            Artisan::call('migrate:fresh', [
                '--force' => true,
            ]);

            $output = Artisan::output();

            return [
                'success' => true,
                'message' => 'Fresh migration completed successfully',
                'output' => $output
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Fresh migration failed: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Rollback last migration
     */
    public function rollbackMigration(): array
    {
        try {
            Artisan::call('migrate:rollback', [
                '--force' => true,
                '--step' => 1,
            ]);

            $output = Artisan::output();

            return [
                'success' => true,
                'message' => 'Migration rollback completed successfully',
                'output' => $output
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Migration rollback failed: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get migration status
     */
    public function getMigrationStatus(): array
    {
        try {
            $pendingMigrations = $this->getPendingMigrations();
            $ranMigrations = $this->getRanMigrations();

            return [
                'success' => true,
                'data' => [
                    'total_migrations' => count($pendingMigrations) + count($ranMigrations),
                    'ran_migrations' => count($ranMigrations),
                    'pending_migrations' => count($pendingMigrations),
                    'ran' => $ranMigrations,
                    'pending' => $pendingMigrations,
                    'is_up_to_date' => count($pendingMigrations) === 0
                ]
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to get migration status: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Check database connection
     */
    private function checkDatabaseConnection(): bool
    {
        try {
            DB::connection()->getPdo();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get pending migrations
     */
    private function getPendingMigrations(): array
    {
        $files = glob(database_path('migrations/*.php'));
        $ranMigrations = $this->getRanMigrations();
        
        $pending = [];
        foreach ($files as $file) {
            $migration = basename($file, '.php');
            if (!in_array($migration, $ranMigrations)) {
                $pending[] = $migration;
            }
        }
        
        return $pending;
    }

    /**
     * Get ran migrations
     */
    private function getRanMigrations(): array
    {
        if (!Schema::hasTable('migrations')) {
            return [];
        }
        
        return DB::table('migrations')->pluck('migration')->toArray();
    }
}