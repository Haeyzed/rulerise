<?php

namespace App\Services\Payment;

use App\Models\Employer;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;

interface PaymentServiceInterface
{
    /**
     * Create a payment intent/order
     *
     * @param Employer $employer
     * @param SubscriptionPlan $plan
     * @param array $paymentData
     * @return array
     */
    public function createPaymentIntent(Employer $employer, SubscriptionPlan $plan, array $paymentData): array;
    
    /**
     * Process a successful payment
     *
     * @param array $paymentData
     * @return Subscription
     */
    public function processPayment(array $paymentData): Subscription;
    
    /**
     * Handle webhook events from payment provider
     *
     * @param array $payload
     * @return bool
     */
    public function handleWebhook(array $payload): bool;
    
    /**
     * Cancel a subscription
     *
     * @param Subscription $subscription
     * @return bool
     */
    public function cancelSubscription(Subscription $subscription): bool;
    
    /**
     * Get payment provider name
     *
     * @return string
     */
    public function getProviderName(): string;
}
