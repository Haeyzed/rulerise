<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Mail\Markdown;

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
        ], 'laravel-mail');

        // Configure markdown theme
        Markdown::theme('talent-beyond-borders');
    }
}
