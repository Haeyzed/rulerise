<?php

namespace App\Services\Subscription;

use App\Models\Employer;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;

interface SubscriptionServiceInterface
{
    /**
     * Create a subscription plan in the payment gateway
     * 
     * @param SubscriptionPlan $plan
     * @return string External plan ID
     */
    public function createPlan(SubscriptionPlan $plan): string;
    
    /**
     * Update a subscription plan in the payment gateway
     * 
     * @param SubscriptionPlan $plan
     * @param string $externalPlanId
     * @return bool
     */
    public function updatePlan(SubscriptionPlan $plan, string $externalPlanId): bool;
    
    /**
     * Delete a subscription plan from the payment gateway
     * 
     * @param string $externalPlanId
     * @return bool
     */
    public function deletePlan(string $externalPlanId): bool;
    
    /**
     * Create a subscription for an employer
     * 
     * @param Employer $employer
     * @param SubscriptionPlan $plan
     * @param array $paymentData
     * @return array Subscription data with redirect URL
     */
    public function createSubscription(Employer $employer, SubscriptionPlan $plan, array $paymentData = []): array;
    
    /**
     * Cancel a subscription
     * 
     * @param Subscription $subscription
     * @return bool
     */
    public function cancelSubscription(Subscription $subscription): bool;
    
    /**
     * Handle webhook events from the payment gateway
     * 
     * @param string $payload
     * @param array $headers
     * @return bool
     */
    public function handleWebhook(string $payload, array $headers): bool;
}