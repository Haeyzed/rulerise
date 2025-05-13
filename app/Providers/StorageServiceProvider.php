<?php

namespace App\Providers;

use App\Services\Storage\StorageService;
use Illuminate\Support\ServiceProvider;

class StorageServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register(): void
    {
        $this->app->singleton(StorageService::class, function ($app) {
            return new StorageService(config('filestorage.default'));
        });
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot(): void
    {
        // Publish configuration
        $this->publishes([
            __DIR__.'/../../config/filestorage.php' => config_path('filestorage.php'),
        ], 'config');
    }
}
