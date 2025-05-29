<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class MailServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Publish mail views
        $this->publishes([
            __DIR__.'/../../resources/views/vendor/mail' => resource_path('views/vendor/mail'),
            __DIR__.'/../../resources/views/emails' => resource_path('views/emails'),
        ], 'laravel-mail');
    }
}
