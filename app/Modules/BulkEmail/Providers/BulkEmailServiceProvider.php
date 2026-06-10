<?php

namespace App\Modules\BulkEmail\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;

class BulkEmailServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        // Register module specific services/repositories here if needed
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        $this->loadRoutesFrom(base_path('routes/bulk-email.php'));
        $this->loadViewsFrom(resource_path('views/bulk-email'), 'bulk-email');
        
        // We'll keep migrations in the standard path but with prefix
        // Alternatively: $this->loadMigrationsFrom(__DIR__ . '/../Migrations');
    }
}
