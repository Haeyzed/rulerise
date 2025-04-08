<?php

namespace App\Providers;

use App\Services\Storage\AwsS3Adapter;
use App\Services\Storage\CloudinaryAdapter;
use App\Services\Storage\DropboxAdapter;
use App\Services\Storage\GoogleCloudAdapter;
use App\Services\Storage\LocalAdapter;
use App\Services\Storage\StorageService;
use Illuminate\Support\ServiceProvider;

class StorageServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(StorageService::class, function ($app) {
            $driver = config('filestorage.default', 'local');

            return match ($driver) {
                'aws' => new StorageService(new AwsS3Adapter()),
                'cloudinary' => new StorageService(new CloudinaryAdapter()),
                'dropbox' => new StorageService(new DropboxAdapter()),
                'google' => new StorageService(new GoogleCloudAdapter()),
                default => new StorageService(new LocalAdapter()),
            };
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
