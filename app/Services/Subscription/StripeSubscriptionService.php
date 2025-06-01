<?php

namespace App\Services\Subscription;

use App\Models\Employer;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Notifications\SubscriptionActivatedNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;
use Stripe\Webhook;

class StripeSubscriptionService implements SubscriptionServiceInterface
{
    protected $stripe;
    protected $webhookSecret;

    public function __construct()
    {
        $this->stripe = new StripeClient(config('services.stripe.secret'));
        $this->webhookSecret = config('services.stripe.webhook_secret');
    }

    /**
     * Create a subscription plan in Stripe
     *
     * @param SubscriptionPlan $plan
     * @return string External plan ID
     */
    public function createPlan(SubscriptionPlan $plan): string
    {
        try {
            // Create a product
            $product = $this->stripe->products->create([
                'name' => $plan->name,
                'description' => $plan->description ?? $plan->name,
                'metadata' => [
                    'plan_id' => $plan->id,
                ]
            ]);

            // Get price configuration from the plan
            $priceConfig = $plan->getStripePriceConfig();
            $priceConfig['product'] = $product->id;

            // Create a price
            $price = $this->stripe->prices->create($priceConfig);

            return $price->id;
        } catch (ApiErrorException $e) {
            Log::error('Stripe create plan error', [
                'plan' => $plan->toArray(),
                'error' => $e->getMessage()
            ]);

            throw new \Exception('Failed to create Stripe plan: ' . $e->getMessage());
        }
    }

    /**
     * Update a subscription plan in Stripe
     *
     * @param SubscriptionPlan $plan
     * @param string $externalPlanId
     * @return bool
     */
    public function updatePlan(SubscriptionPlan $plan, string $externalPlanId): bool
    {
        try {
            // Get the price to find the product
            $price = $this->stripe->prices->retrieve($externalPlanId);

            // Update the product
            $this->stripe->products->update($price->product, [
                'name' => $plan->name,
                'description' => $plan->description ?? $plan->name,
                'metadata' => [
                    'plan_id' => $plan->id,
                ]
            ]);

            return true;
        } catch (ApiErrorException $e) {
            Log::error('Stripe update plan error', [
                'plan' => $plan->toArray(),
                'externalPlanId' => $externalPlanId,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Delete a subscription plan from Stripe
     *
     * @param string $externalPlanId
     * @return bool
     */
    public function deletePlan(string $externalPlanId): bool
    {
        try {
            // Get the price to find the product
            $price = $this->stripe->prices->retrieve($externalPlanId);

            // Archive the product
            $this->stripe->products->update($price->product, [
                'active' => false
            ]);

            // Archive the price
            $this->stripe->prices->update($externalPlanId, [
                'active' => false
            ]);

            return true;
        } catch (ApiErrorException $e) {
            Log::error('Stripe delete plan error', [
                'externalPlanId' => $externalPlanId,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * List all subscription plans from Stripe
     *
     * @param array $filters Optional filters
     * @return array List of plans
     */
    public function listPlans(array $filters = []): array
    {
        try {
            $params = [
                'limit' => $filters['limit'] ?? 20,
                'active' => $filters['active'] ?? true,
            ];

            if (isset($filters['product'])) {
                $params['product'] = $filters['product'];
            }

            $prices = $this->stripe->prices->all($params);

            return [
                'plans' => $prices->data,
                'has_more' => $prices->has_more,
            ];
        } catch (ApiErrorException $e) {
            Log::error('Stripe list plans error', [
                'filters' => $filters,
                'error' => $e->getMessage()
            ]);

            return ['plans' => []];
        }
    }

    /**
     * Get details of a specific subscription plan
     *
     * @param string $externalPlanId
     * @return array Plan details
     */
    public function getPlanDetails(string $externalPlanId): array
    {
        try {
            $price = $this->stripe->prices->retrieve($externalPlanId, [
                'expand' => ['product']
            ]);

            return $price->toArray();
        } catch (ApiErrorException $e) {
            Log::error('Stripe get plan details error', [
                'externalPlanId' => $externalPlanId,
                'error' => $e->getMessage()
            ]);

            return [];
        }
    }

    /**
     * Create a subscription for an employer
     *
     * @param Employer $employer
     * @param SubscriptionPlan $plan
     * @param array $paymentData
     * @return array Subscription data with redirect URL
     */
    public function createSubscription(Employer $employer, SubscriptionPlan $plan, array $paymentData = []): array
    {
        try {
            // Get or create the external plan ID
            $externalPlanId = $plan->external_stripe_id ?? $this->createPlan($plan);

            // If we created a new plan, save the ID
            if (!$plan->external_stripe_id) {
                $plan->external_stripe_id = $externalPlanId;
                $plan->save();
            }

            // Create or get customer
            $customerId = $this->getOrCreateCustomer($employer);

            // Create checkout session
            $sessionConfig = [
                'customer' => $customerId,
                'payment_method_types' => ['card'],
                'line_items' => [
                    [
                        'price' => $externalPlanId,
                        'quantity' => 1,
                    ],
                ],
                'success_url' => url('/api/subscription/stripe/success?session_id={CHECKOUT_SESSION_ID}'),
                'cancel_url' => url('/api/subscription/stripe/cancel'),
                'metadata' => [
                    'employer_id' => $employer->id,
                    'plan_id' => $plan->id
                ]
            ];

            // Set mode based on plan type
            if ($plan->isOneTime()) {
                $sessionConfig['mode'] = 'payment';
            } else {
                $sessionConfig['mode'] = 'subscription';

                // Add trial period if configured
                if ($plan->hasTrial()) {
                    $sessionConfig['subscription_data'] = [
                        'trial_period_days' => $plan->getTrialPeriodDays()
                    ];
                }
            }

            $session = $this->stripe->checkout->sessions->create($sessionConfig);

            // Create a pending subscription record
            $subscription = $this->createSubscriptionRecord($employer, $plan, $session);

            return [
                'subscription_id' => $subscription->id,
                'session_id' => $session->id,
                'redirect_url' => $session->url,
                'status' => 'pending'
            ];
        } catch (ApiErrorException $e) {
            Log::error('Stripe create subscription error', [
                'employer' => $employer->id,
                'plan' => $plan->toArray(),
                'error' => $e->getMessage()
            ]);

            throw new \Exception('Failed to create Stripe subscription: ' . $e->getMessage());
        }
    }

    /**
     * Get or create Stripe customer
     *
     * @param Employer $employer
     * @return string Customer ID
     */
    private function getOrCreateCustomer(Employer $employer): string
    {
        if ($employer->stripe_customer_id) {
            return $employer->stripe_customer_id;
        }

        try {
            $customer = $this->stripe->customers->create([
                'email' => $employer->user->email ?? $employer->company_email,
                'name' => $employer->company_name,
                'metadata' => [
                    'employer_id' => $employer->id,
                    'user_id' => $employer->user_id
                ]
            ]);

            $employer->stripe_customer_id = $customer->id;
            $employer->save();

            return $customer->id;
        } catch (ApiErrorException $e) {
            Log::error('Stripe create customer error', [
                'employer' => $employer->id,
                'error' => $e->getMessage()
            ]);

            throw new \Exception('Failed to create Stripe customer: ' . $e->getMessage());
        }
    }

    /**
     * Create subscription record in database
     *
     * @param Employer $employer
     * @param SubscriptionPlan $plan
     * @param \Stripe\Checkout\Session $session
     * @return Subscription
     */
    private function createSubscriptionRecord(Employer $employer, SubscriptionPlan $plan, $session): Subscription
    {
        $endDate = null;
        if ($plan->isRecurring() && $plan->duration_days) {
            $endDate = Carbon::now()->addDays($plan->duration_days);
            if ($plan->hasTrial()) {
                $endDate->addDays($plan->getTrialPeriodDays());
            }
        }

        return Subscription::create([
            'employer_id' => $employer->id,
            'subscription_plan_id' => $plan->id,
            'start_date' => Carbon::now(),
            'end_date' => $endDate,
            'amount_paid' => $plan->price,
            'currency' => $plan->currency,
            'payment_method' => 'stripe',
            'payment_reference' => $session->id,
            'job_posts_left' => $plan->job_posts_limit,
            'featured_jobs_left' => $plan->featured_jobs_limit,
            'cv_downloads_left' => $plan->resume_views_limit,
            'payment_type' => $plan->payment_type,
            'is_active' => false // Will be activated when payment is confirmed
        ]);
    }

    /**
     * Cancel a subscription
     *
     * @param Subscription $subscription
     * @return bool
     */
    public function cancelSubscription(Subscription $subscription): bool
    {
        if (!$subscription->subscription_id) {
            return false;
        }

        try {
            $this->stripe->subscriptions->cancel($subscription->subscription_id, [
                'cancel_at_period_end' => false,
            ]);

            $subscription->is_active = false;
            $subscription->save();

            return true;
        } catch (ApiErrorException $e) {
            Log::error('Stripe cancel subscription error', [
                'subscription' => $subscription->toArray(),
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * List all subscriptions for an employer
     *
     * @param Employer $employer
     * @return array List of subscriptions
     */
    public function listSubscriptions(Employer $employer): array
    {
        if (!$employer->stripe_customer_id) {
            return ['subscriptions' => []];
        }

        try {
            $subscriptions = $this->stripe->subscriptions->all([
                'customer' => $employer->stripe_customer_id,
                'limit' => 100,
            ]);

            return [
                'subscriptions' => $subscriptions->data,
                'has_more' => $subscriptions->has_more,
            ];
        } catch (ApiErrorException $e) {
            Log::error('Stripe list subscriptions error', [
                'employer' => $employer->id,
                'error' => $e->getMessage()
            ]);

            return ['subscriptions' => []];
        }
    }

    /**
     * Get details of a specific subscription
     *
     * @param string $subscriptionId
     * @return array Subscription details
     */
    public function getSubscriptionDetails(string $subscriptionId): array
    {
        try {
            $subscription = $this->stripe->subscriptions->retrieve($subscriptionId, [
                'expand' => ['latest_invoice', 'customer']
            ]);

            return $subscription->toArray();
        } catch (ApiErrorException $e) {
            Log::error('Stripe get subscription details error', [
                'subscriptionId' => $subscriptionId,
                'error' => $e->getMessage()
            ]);

            return [];
        }
    }

    /**
     * Suspend a subscription (temporarily pause)
     *
     * @param Subscription $subscription
     * @return bool
     */
    public function suspendSubscription(Subscription $subscription): bool
    {
        if (!$subscription->subscription_id || $subscription->isOneTime()) {
            return false;
        }

        try {
            $this->stripe->subscriptions->update($subscription->subscription_id, [
                'pause_collection' => [
                    'behavior' => 'void'
                ]
            ]);

            $subscription->is_suspended = true;
            $subscription->save();

            return true;
        } catch (ApiErrorException $e) {
            Log::error('Stripe suspend subscription error', [
                'subscription' => $subscription->toArray(),
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Reactivate a suspended subscription
     *
     * @param Subscription $subscription
     * @return bool
     */
    public function reactivateSubscription(Subscription $subscription): bool
    {
        if (!$subscription->subscription_id || $subscription->isOneTime()) {
            return false;
        }

        try {
            $this->stripe->subscriptions->update($subscription->subscription_id, [
                'pause_collection' => null
            ]);

            $subscription->is_active = true;
            $subscription->is_suspended = false;
            $subscription->save();

            return true;
        } catch (ApiErrorException $e) {
            Log::error('Stripe reactivate subscription error', [
                'subscription' => $subscription->toArray(),
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Update subscription plan
     *
     * @param Subscription $subscription
     * @param SubscriptionPlan $newPlan
     * @return bool
     */
    public function updateSubscriptionPlan(Subscription $subscription, SubscriptionPlan $newPlan): bool
    {
        if ($subscription->isOneTime() || !$subscription->subscription_id) {
            return false;
        }

        try {
            // Get or create the external plan ID for the new plan
            $externalPlanId = $newPlan->external_stripe_id ?? $this->createPlan($newPlan);

            if (!$newPlan->external_stripe_id) {
                $newPlan->external_stripe_id = $externalPlanId;
                $newPlan->save();
            }

            // Get current subscription
            $stripeSubscription = $this->stripe->subscriptions->retrieve($subscription->subscription_id);

            // Update the subscription
            $this->stripe->subscriptions->update($subscription->subscription_id, [
                'items' => [
                    [
                        'id' => $stripeSubscription->items->data[0]->id,
                        'price' => $externalPlanId,
                    ]
                ],
                'proration_behavior' => 'create_prorations',
            ]);

            // Update the subscription in our database
            $subscription->subscription_plan_id = $newPlan->id;
            $subscription->end_date = $newPlan->isRecurring() && $newPlan->duration_days
                ? Carbon::now()->addDays($newPlan->duration_days)
                : null;
            $subscription->job_posts_left = $newPlan->job_posts_limit;
            $subscription->featured_jobs_left = $newPlan->featured_jobs_limit;
            $subscription->cv_downloads_left = $newPlan->resume_views_limit;
            $subscription->payment_type = $newPlan->payment_type;
            $subscription->save();

            return true;
        } catch (ApiErrorException $e) {
            Log::error('Stripe update subscription plan error', [
                'subscription' => $subscription->toArray(),
                'newPlan' => $newPlan->toArray(),
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Get subscription transactions
     *
     * @param string $subscriptionId
     * @return array List of transactions
     */
    public function getSubscriptionTransactions(string $subscriptionId): array
    {
        try {
            $invoices = $this->stripe->invoices->all([
                'subscription' => $subscriptionId,
                'limit' => 100,
            ]);

            return [
                'transactions' => $invoices->data,
                'has_more' => $invoices->has_more,
            ];
        } catch (ApiErrorException $e) {
            Log::error('Stripe get subscription transactions error', [
                'subscriptionId' => $subscriptionId,
                'error' => $e->getMessage()
            ]);

            return ['transactions' => []];
        }
    }

    /**
     * Handle webhook events from Stripe
     *
     * @param string $payload
     * @param array $headers
     * @return bool
     */
    public function handleWebhook(string $payload, array $headers): bool
    {
        $sigHeader = $headers['stripe-signature'] ?? $headers['Stripe-Signature'] ?? '';

        try {
            // Verify the event
            $event = Webhook::constructEvent(
                $payload, $sigHeader, $this->webhookSecret
            );

            Log::info('Stripe webhook received', [
                'type' => $event->type,
                'id' => $event->id
            ]);

            switch ($event->type) {
                case 'checkout.session.completed':
                    return $this->handleCheckoutSessionCompleted($event->data->object);

                case 'customer.subscription.created':
                    return $this->handleSubscriptionCreated($event->data->object);

                case 'customer.subscription.updated':
                    return $this->handleSubscriptionUpdated($event->data->object);

                case 'customer.subscription.deleted':
                    return $this->handleSubscriptionDeleted($event->data->object);

                case 'invoice.paid':
                    return $this->handleInvoicePaid($event->data->object);

                case 'invoice.payment_succeeded':
                    return $this->handlePaymentSucceeded($event->data->object);

                default:
                    Log::info('Unhandled Stripe webhook event', ['type' => $event->type]);
                    return true;
            }
        } catch (\Exception $e) {
            Log::error('Stripe webhook error', [
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Handle checkout session completed event
     *
     * @param \Stripe\Checkout\Session $session
     * @return bool
     */
    protected function handleCheckoutSessionCompleted($session): bool
    {
        // Find the subscription by payment reference (session ID)
        $subscription = Subscription::where('payment_reference', $session->id)
            ->where('payment_method', 'stripe')
            ->first();

        if (!$subscription) {
            Log::error('Stripe subscription not found for session', ['sessionId' => $session->id]);
            return false;
        }

        // For one-time payments, activate immediately
        if ($session->mode === 'payment') {
            $subscription->is_active = true;
            $subscription->transaction_id = $session->payment_intent;
            $subscription->save();

            // Send notification
            $this->sendActivationNotification($subscription);
        } else {
            // For subscriptions, update with Stripe subscription ID
            $subscription->subscription_id = $session->subscription;
            $subscription->save();
        }

        return true;
    }

    /**
     * Handle subscription created event
     *
     * @param \Stripe\Subscription $stripeSubscription
     * @return bool
     */
    protected function handleSubscriptionCreated($stripeSubscription): bool
    {
        // Find the subscription by Stripe subscription ID
        $subscription = Subscription::where('subscription_id', $stripeSubscription->id)
            ->where('payment_method', 'stripe')
            ->first();

        if (!$subscription) {
            // Try to find by customer ID and latest pending subscription
            $employer = Employer::where('stripe_customer_id', $stripeSubscription->customer)->first();

            if (!$employer) {
                Log::error('Stripe employer not found for customer', ['customerId' => $stripeSubscription->customer]);
                return false;
            }

            $subscription = Subscription::where('employer_id', $employer->id)
                ->where('payment_method', 'stripe')
                ->whereNull('subscription_id')
                ->latest()
                ->first();

            if (!$subscription) {
                Log::error('Stripe subscription not found for customer', [
                    'customerId' => $stripeSubscription->customer,
                    'employerId' => $employer->id
                ]);
                return false;
            }

            $subscription->subscription_id = $stripeSubscription->id;
        }

        // Update subscription status based on Stripe status
        if (in_array($stripeSubscription->status, ['active', 'trialing'])) {
            $subscription->is_active = true;
            $this->sendActivationNotification($subscription);
        }

        $subscription->save();

        return true;
    }

    /**
     * Handle subscription updated event
     *
     * @param \Stripe\Subscription $stripeSubscription
     * @return bool
     */
    protected function handleSubscriptionUpdated($stripeSubscription): bool
    {
        $subscription = Subscription::where('subscription_id', $stripeSubscription->id)
            ->where('payment_method', 'stripe')
            ->first();

        if (!$subscription) {
            Log::error('Stripe subscription not found', ['subscriptionId' => $stripeSubscription->id]);
            return false;
        }

        // Update subscription status based on Stripe status
        $wasActive = $subscription->is_active;

        if (in_array($stripeSubscription->status, ['active', 'trialing'])) {
            $subscription->is_active = true;
            $subscription->is_suspended = false;
        } elseif ($stripeSubscription->status === 'paused') {
            $subscription->is_suspended = true;
        } else {
            $subscription->is_active = false;
        }

        $subscription->save();

        // Send activation notification if subscription became active
        if (!$wasActive && $subscription->is_active) {
            $this->sendActivationNotification($subscription);
        }

        return true;
    }

    /**
     * Handle subscription deleted event
     *
     * @param \Stripe\Subscription $stripeSubscription
     * @return bool
     */
    protected function handleSubscriptionDeleted($stripeSubscription): bool
    {
        $subscription = Subscription::where('subscription_id', $stripeSubscription->id)
            ->where('payment_method', 'stripe')
            ->first();

        if (!$subscription) {
            Log::error('Stripe subscription not found', ['subscriptionId' => $stripeSubscription->id]);
            return false;
        }

        $subscription->is_active = false;
        $subscription->save();

        return true;
    }

    /**
     * Handle invoice paid event
     *
     * @param \Stripe\Invoice $invoice
     * @return bool
     */
    protected function handleInvoicePaid($invoice): bool
    {
        if (!$invoice->subscription) {
            return true; // Not a subscription invoice
        }

        $subscription = Subscription::where('subscription_id', $invoice->subscription)
            ->where('payment_method', 'stripe')
            ->first();

        if (!$subscription) {
            Log::error('Stripe subscription not found for invoice', [
                'subscriptionId' => $invoice->subscription,
                'invoiceId' => $invoice->id
            ]);
            return false;
        }

        $subscription->transaction_id = $invoice->payment_intent;
        $subscription->save();

        return true;
    }

    /**
     * Handle payment succeeded event
     *
     * @param \Stripe\Invoice $invoice
     * @return bool
     */
    protected function handlePaymentSucceeded($invoice): bool
    {
        if (!$invoice->subscription) {
            return true; // Not a subscription invoice
        }

        $subscription = Subscription::where('subscription_id', $invoice->subscription)
            ->where('payment_method', 'stripe')
            ->first();

        if (!$subscription) {
            Log::error('Stripe subscription not found for payment', [
                'subscriptionId' => $invoice->subscription,
                'invoiceId' => $invoice->id
            ]);
            return false;
        }

        // Activate subscription if not already active
        if (!$subscription->is_active) {
            $subscription->is_active = true;
            $subscription->save();
            $this->sendActivationNotification($subscription);
        }

        return true;
    }

    /**
     * Send activation notification
     *
     * @param Subscription $subscription
     * @return void
     */
    private function sendActivationNotification(Subscription $subscription): void
    {
        try {
            $employer = $subscription->employer;
            if ($employer && $employer->user) {
                $employer->user->notify(new SubscriptionActivatedNotification($subscription));

                Log::info('Stripe subscription activation notification sent', [
                    'employer_id' => $employer->id,
                    'subscription_id' => $subscription->id
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to send Stripe subscription activation notification', [
                'subscription_id' => $subscription->id,
                'error' => $e->getMessage()
            ]);
        }
    }
}
