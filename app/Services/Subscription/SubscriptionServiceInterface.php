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
     * List all subscription plans from the payment gateway
     *
     * @param array $filters Optional filters
     * @return array List of plans
     */
    public function listPlans(array $filters = []): array;

    /**
     * Get details of a specific subscription plan
     *
     * @param string $externalPlanId
     * @return array Plan details
     */
    public function getPlanDetails(string $externalPlanId): array;

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
     * Create a manual trial subscription (for one-time plans with trial)
     *
     * @param Employer $employer
     * @param SubscriptionPlan $plan
     * @return Subscription
     */
    public function createTrialSubscription(Employer $employer, SubscriptionPlan $plan): Subscription;

    /**
     * Cancel a subscription
     *
     * @param Subscription $subscription
     * @return bool
     */
    public function cancelSubscription(Subscription $subscription): bool;

    /**
     * List all subscriptions for an employer
     *
     * @param Employer $employer
     * @return array List of subscriptions
     */
    public function listSubscriptions(Employer $employer): array;

    /**
     * Get details of a specific subscription
     *
     * @param string $subscriptionId
     * @return array Subscription details
     */
    public function getSubscriptionDetails(string $subscriptionId): array;

    /**
     * Suspend a subscription (temporarily pause)
     *
     * @param Subscription $subscription
     * @return bool
     */
    public function suspendSubscription(Subscription $subscription): bool;

    /**
     * Reactivate a suspended subscription
     *
     * @param Subscription $subscription
     * @return bool
     */
    public function reactivateSubscription(Subscription $subscription): bool;

    /**
     * Handle webhook events from the payment gateway
     *
     * @param string $payload
     * @param array $headers
     * @return bool
     */
    public function handleWebhook(string $payload, array $headers): bool;
}
