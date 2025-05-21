<?php

namespace App\Services\Subscription;

use InvalidArgumentException;

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
        return match (strtolower($provider)) {
            'paypal' => app(PayPalSubscriptionService::class),
            'stripe' => app(StripeSubscriptionService::class),
            default => throw new InvalidArgumentException("Unsupported payment provider: {$provider}"),
        };
    }
}
