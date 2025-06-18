<?php

namespace App\Services\Payment\Contracts;

use App\Models\Employer;
use App\Models\Plan;
use App\Models\Subscription;

/**
 * Payment Service Interface
 * 
 * Defines the contract for payment service implementations
 */
interface PaymentServiceInterface
{
    /**
     * Create a one-time payment
     */
    public function createOneTimePayment(Employer $employer, Plan $plan): array;

    /**
     * Create a recurring subscription
     */
    public function createSubscription(Employer $employer, Plan $plan): array;

    /**
     * Cancel a subscription
     */
    public function cancelSubscription(Subscription $subscription): bool;

    /**
     * Suspend a subscription
     */
    public function suspendSubscription(Subscription $subscription): bool;

    /**
     * Resume a subscription
     */
    public function resumeSubscription(Subscription $subscription): bool;

    /**
     * Handle webhook events
     */
    public function handleWebhook(array $event): void;
}
