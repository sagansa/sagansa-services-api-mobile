<?php
namespace App\Providers;

use App\Models\PersonalAccessToken;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);

        $backendPath = base_path('backend-api/database/migrations');
        if (is_dir($backendPath)) {
            $this->loadMigrationsFrom($backendPath);
        }
    }
}
