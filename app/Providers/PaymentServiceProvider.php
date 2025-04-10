<?php

namespace App\Providers;

use App\Services\Payment\FlutterwaveService;
use App\Services\Payment\PaymentGatewayInterface;
use App\Services\Payment\PaystackService;
use App\Services\Payment\StripeService;
use Illuminate\Support\ServiceProvider;

class PaymentServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register payment gateway services
        $this->app->bind(StripeService::class, function ($app) {
            return new StripeService();
        });

        $this->app->bind(PaystackService::class, function ($app) {
            return new PaystackService();
        });

        $this->app->bind(FlutterwaveService::class, function ($app) {
            return new FlutterwaveService();
        });

        // Bind the default payment gateway based on configuration
        $this->app->bind(PaymentGatewayInterface::class, function ($app) {
            $defaultGateway = config('services.payment.default_gateway', 'stripe');

            return match ($defaultGateway) {
                'paystack' => $app->make(PaystackService::class),
                'flutterwave' => $app->make(FlutterwaveService::class),
                default => $app->make(StripeService::class),
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
