<?php

namespace App\Services\Subscription;

use App\Models\Employer;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;

interface SubscriptionServiceInterface
{
    /**
     * Create a subscription plan in the payment gateway
     */
    public function createPlan(SubscriptionPlan $plan): string;

    /**
     * Update a subscription plan in the payment gateway
     */
    public function updatePlan(SubscriptionPlan $plan, string $externalPlanId): bool;

    /**
     * Delete a subscription plan from the payment gateway
     */
    public function deletePlan(string $externalPlanId): bool;

    /**
     * List all subscription plans from the payment gateway
     */
    public function listPlans(array $filters = []): array;

    /**
     * Get details of a specific subscription plan
     */
    public function getPlanDetails(string $externalPlanId): array;

    /**
     * Create a subscription for an employer
     */
    public function createSubscription(Employer $employer, SubscriptionPlan $plan, array $paymentData = []): array;

    /**
     * Create a one-time payment order
     */
    public function createOneTimeOrder(Employer $employer, SubscriptionPlan $plan, array $paymentData = []): array;

    /**
     * Cancel a subscription
     */
    public function cancelSubscription(Subscription $subscription): bool;

    /**
     * Suspend a subscription (pause billing)
     */
    public function suspendSubscription(Subscription $subscription): bool;

    /**
     * Reactivate a suspended subscription
     */
    public function reactivateSubscription(Subscription $subscription): bool;

    /**
     * List all subscriptions for an employer
     */
    public function listSubscriptions(Employer $employer): array;

    /**
     * Get details of a specific subscription
     */
    public function getSubscriptionDetails(string $subscriptionId): array;

    /**
     * Handle webhook events from the payment gateway
     */
    public function handleWebhook(string $payload, array $headers): bool;

    /**
     * Validate if employer can use one-time payment for a plan
     */
    public function canUseOneTimePayment(Employer $employer, SubscriptionPlan $plan): bool;

    /**
     * Validate if employer needs trial for a plan
     */
    public function shouldUseTrial(Employer $employer, SubscriptionPlan $plan): bool;
}
