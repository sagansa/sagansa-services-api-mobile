<?php

namespace App\Console\Commands;

use App\Services\MigrationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CheckDatabase extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'check:database';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check database connection and migration status';

    /**
     * Execute the console command.
     */
    public function handle(MigrationService $migrationService)
    {
        $this->info('Checking database status...');
        $this->newLine();
        
        // Check database connection
        try {
            DB::connection()->getPdo();
            $this->info('✅ Database connection: OK');
            $this->line('Connection: ' . config('database.default'));
            $this->line('Database: ' . DB::connection()->getDatabaseName());
            $this->line('Host: ' . config('database.connections.' . config('database.default') . '.host'));
        } catch (\Exception $e) {
            $this->error('❌ Database connection failed: ' . $e->getMessage());
            return Command::FAILURE;
        }
        
        $this->newLine();
        
        // Check migration status
        $this->info('Checking migration status...');
        $status = $migrationService->getMigrationStatus();
        
        if (!$status['success']) {
            $this->error('Failed to check migration status: ' . $status['message']);
            return Command::FAILURE;
        }
        
        $this->line('Total migrations: ' . $status['total']);
        $this->line('Applied migrations: ' . $status['applied']);
        $this->line('Pending migrations: ' . $status['pending']);
        
        if ($status['pending'] > 0) {
            $this->warn('⚠️  ' . $status['pending'] . ' migrations pending');
            
            if ($this->confirm('Do you want to run pending migrations?')) {
                $this->call('migrate');
            }
        } else {
            $this->info('✅ All migrations are up to date');
        }
        
        $this->newLine();
        
        // Check if tables exist
        $this->info('Checking tables...');
        $tables = [
            'users', 'tenants', 'stores', 'products', 'orders', 
            'attendances', 'printers', 'categories', 'payments'
        ];
        
        $existingTables = [];
        $missingTables = [];
        
        foreach ($tables as $table) {
            if (DB::getSchemaBuilder()->hasTable($table)) {
                $existingTables[] = $table;
            } else {
                $missingTables[] = $table;
            }
        }
        
        if (count($existingTables) > 0) {
            $this->info('✅ Existing tables: ' . implode(', ', $existingTables));
        }
        
        if (count($missingTables) > 0) {
            $this->warn('⚠️  Missing tables: ' . implode(', ', $missingTables));
        }
        
        $this->newLine();
        
        // Check user count
        try {
            $userCount = DB::table('users')->count();
            $this->info('✅ Total users: ' . $userCount);
            
            if ($userCount > 0) {
                $tenantCount = DB::table('tenants')->count();
                $storeCount = DB::table('stores')->count();
                $productCount = DB::table('products')->count();
                
                $this->line('Total tenants: ' . $tenantCount);
                $this->line('Total stores: ' . $storeCount);
                $this->line('Total products: ' . $productCount);
            }
        } catch (\Exception $e) {
            $this->warn('Could not count records: ' . $e->getMessage());
        }
        
        $this->newLine();
        $this->info('Database check completed!');
        
        return Command::SUCCESS;
    }
}