<?php

namespace App\Services\Subscription;

class SubscriptionServiceFactory
{
    /**
     * Create a subscription service instance
     *
     * @param string $provider
     * @return SubscriptionServiceInterface
     */
    public static function create(string $provider): SubscriptionServiceInterface
    {
        switch (strtolower($provider)) {
            case 'paypal':
                return app(PayPalSubscriptionService::class);

            case 'stripe':
                return app(StripeSubscriptionService::class);

            default:
                throw new \InvalidArgumentException("Unsupported payment provider: {$provider}");
        }
    }
}
