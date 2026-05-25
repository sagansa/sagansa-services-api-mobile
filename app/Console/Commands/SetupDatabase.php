<?php

namespace App\Console\Commands;

use App\Services\MigrationService;
use Illuminate\Console\Command;

class SetupDatabase extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'setup:database 
                            {--fresh : Drop all tables and re-run migrations}
                            {--seed : Run seeders after migration}
                            {--demo : Use demo data for seeding}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Setup database with migrations and seeders';

    /**
     * Execute the console command.
     */
    public function handle(MigrationService $migrationService)
    {
        $this->info('Starting database setup...');
        
        // Check if fresh migration is requested
        if ($this->option('fresh')) {
            $this->warn('⚠️  This will drop all tables and re-run migrations!');
            
            if (!$this->confirm('Are you sure you want to proceed?')) {
                $this->info('Setup cancelled.');
                return Command::SUCCESS;
            }
            
            $this->info('Running fresh migration...');
            $result = $migrationService->freshMigration();
            
            if (!$result['success']) {
                $this->error('Fresh migration failed: ' . $result['message']);
                return Command::FAILURE;
            }
            
            $this->info('Fresh migration completed successfully!');
            $this->line($result['output']);
        } else {
            // Run regular migration
            $this->info('Running migrations...');
            $result = $migrationService->runMigrations();
            
            if (!$result['success']) {
                $this->error('Migration failed: ' . $result['message']);
                return Command::FAILURE;
            }
            
            $this->info('Migrations completed successfully!');
            $this->line($result['output']);
        }
        
        // Run seeders if requested
        if ($this->option('seed') || $this->option('demo')) {
            $this->info('Running seeders...');
            
            if ($this->option('demo')) {
                $this->call('db:seed', ['--class' => 'CompleteSeeder']);
            } else {
                $result = $migrationService->runSeeders();
                
                if (!$result['success']) {
                    $this->error('Seeding failed: ' . $result['message']);
                    return Command::FAILURE;
                }
                
                $this->info('Seeding completed successfully!');
                $this->line($result['output']);
            }
        }
        
        $this->info('✅ Database setup completed successfully!');
        
        // Show login credentials if demo data was seeded
        if ($this->option('demo')) {
            $this->newLine();
            $this->info('=== DEMO LOGIN CREDENTIALS ===');
            $this->line('Owner: owner@sagansa.demo / password');
            $this->line('Manager: manager@sagansa.demo / password');
            $this->line('Cashier: cashier@sagansa.demo / password');
            $this->line('Staff: staff@sagansa.demo / password');
            $this->newLine();
            $this->info('API Base URL: ' . config('app.url'));
            $this->info('Login endpoint: POST /api/auth/login');
        }
        
        return Command::SUCCESS;
    }
}