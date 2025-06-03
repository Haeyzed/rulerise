<?php

namespace App\Services\Subscription;

use App\Models\Employer;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Notifications\SubscriptionActivatedNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Stripe\Checkout\Session;
use Stripe\Exception\ApiErrorException;
use Stripe\PaymentIntent;
use Stripe\StripeClient;
use Stripe\Webhook;

class StripeSubscriptionService implements SubscriptionServiceInterface
{
    public $stripe;
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
     * @return string External plan ID (Price ID)
     */
    public function createPlan(SubscriptionPlan $plan): string
    {
        try {
            // Create a product first
            $product = $this->stripe->products->create([
                'name' => $plan->name,
                'description' => $plan->description ?? $plan->name,
                'metadata' => [
                    'plan_id' => $plan->id,
                    'job_posts_limit' => $plan->job_posts_limit,
                    'featured_jobs_limit' => $plan->featured_jobs_limit,
                    'resume_views_limit' => $plan->resume_views_limit,
                ]
            ]);

            // Get price configuration from the plan
            $priceConfig = $plan->getStripePriceConfig();
            $priceConfig['product'] = $product->id;
            $priceConfig['nickname'] = $plan->name;

            // Create a price
            $price = $this->stripe->prices->create($priceConfig);

            Log::info('Stripe plan created successfully', [
                'plan_id' => $plan->id,
                'product_id' => $product->id,
                'price_id' => $price->id
            ]);

            return $price->id;
        } catch (ApiErrorException $e) {
            Log::error('Stripe create plan error', [
                'plan' => $plan->toArray(),
                'error' => $e->getMessage(),
                'code' => $e->getStripeCode()
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
                    'job_posts_limit' => $plan->job_posts_limit,
                    'featured_jobs_limit' => $plan->featured_jobs_limit,
                    'resume_views_limit' => $plan->resume_views_limit,
                ]
            ]);

            Log::info('Stripe plan updated successfully', [
                'plan_id' => $plan->id,
                'price_id' => $externalPlanId
            ]);

            return true;
        } catch (ApiErrorException $e) {
            Log::error('Stripe update plan error', [
                'plan' => $plan->toArray(),
                'externalPlanId' => $externalPlanId,
                'error' => $e->getMessage(),
                'code' => $e->getStripeCode()
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

            Log::info('Stripe plan deleted successfully', [
                'price_id' => $externalPlanId
            ]);

            return true;
        } catch (ApiErrorException $e) {
            Log::error('Stripe delete plan error', [
                'externalPlanId' => $externalPlanId,
                'error' => $e->getMessage(),
                'code' => $e->getStripeCode()
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
                'expand' => ['data.product']
            ];

            if (isset($filters['product'])) {
                $params['product'] = $filters['product'];
            }

            if (isset($filters['type'])) {
                $params['type'] = $filters['type'];
            }

            $prices = $this->stripe->prices->all($params);

            return [
                'plans' => $prices->data,
                'has_more' => $prices->has_more,
                'total_count' => count($prices->data)
            ];
        } catch (ApiErrorException $e) {
            Log::error('Stripe list plans error', [
                'filters' => $filters,
                'error' => $e->getMessage(),
                'code' => $e->getStripeCode()
            ]);

            return ['plans' => [], 'has_more' => false, 'total_count' => 0];
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
                'error' => $e->getMessage(),
                'code' => $e->getStripeCode()
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
                'success_url' => config('app.frontend_url') . '/employer/dashboard?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => config('app.frontend_url') . '/employer/dashboard?session_id={CHECKOUT_SESSION_ID}',
//                'success_url' => config('app.frontend_url') . '/employer/subscription/stripe/success?session_id={CHECKOUT_SESSION_ID}',
//                'cancel_url' => config('app.frontend_url') . '/employer/subscription/stripe/cancel?session_id={CHECKOUT_SESSION_ID}',
                'metadata' => [
                    'employer_id' => $employer->id,
                    'plan_id' => $plan->id,
                    'user_id' => $employer->user_id
                ],
                'customer_update' => [
                    'address' => 'auto',
                    'name' => 'auto'
                ],
                'billing_address_collection' => 'auto'
            ];

            // Set mode based on plan type
            if ($plan->isOneTime()) {
                $sessionConfig['mode'] = 'payment';
                $sessionConfig['payment_intent_data'] = [
                    'metadata' => [
                        'employer_id' => $employer->id,
                        'plan_id' => $plan->id,
                        'payment_type' => 'one_time'
                    ]
                ];
            } else {
                $sessionConfig['mode'] = 'subscription';
                $sessionConfig['subscription_data'] = [
                    'metadata' => [
                        'employer_id' => $employer->id,
                        'plan_id' => $plan->id,
                        'payment_type' => 'recurring'
                    ]
                ];

                // Add trial period if configured
                if ($plan->hasTrial()) {
                    $sessionConfig['subscription_data']['trial_period_days'] = $plan->getTrialPeriodDays();
                }
            }

            $session = $this->stripe->checkout->sessions->create($sessionConfig);

            // Create a pending subscription record
            $subscription = $this->createSubscriptionRecord($employer, $plan, $session);

            Log::info('Stripe subscription created successfully', [
                'employer_id' => $employer->id,
                'plan_id' => $plan->id,
                'session_id' => $session->id,
                'subscription_id' => $subscription->id
            ]);

            return [
                'subscription_id' => $subscription->id,
                'session_id' => $session->id,
                'external_subscription_id' => $session->subscription ?? $session->id,
                'redirect_url' => $session->url,
                'status' => 'pending'
            ];
        } catch (ApiErrorException $e) {
            Log::error('Stripe create subscription error', [
                'employer' => $employer->id,
                'plan' => $plan->toArray(),
                'error' => $e->getMessage(),
                'code' => $e->getStripeCode()
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
            try {
                // Verify the customer still exists
                $this->stripe->customers->retrieve($employer->stripe_customer_id);
                return $employer->stripe_customer_id;
            } catch (ApiErrorException $e) {
                Log::warning('Stripe customer not found, creating new one', [
                    'employer_id' => $employer->id,
                    'old_customer_id' => $employer->stripe_customer_id
                ]);
                // Customer doesn't exist, create a new one
                $employer->stripe_customer_id = null;
            }
        }

        try {
            $customerData = [
                'email' => $employer->user->email ?? $employer->company_email,
                'name' => $employer->company_name,
                'metadata' => [
                    'employer_id' => $employer->id,
                    'user_id' => $employer->user_id,
                    'company_name' => $employer->company_name
                ]
            ];

            // Add address if available
            if ($employer->company_country || $employer->company_state || $employer->company_address) {
                $customerData['address'] = [
                    'country' => $employer->company_country,
                    'state' => $employer->company_state,
                    'line1' => $employer->company_address,
                ];
            }

            // Add phone if available
            if ($employer->company_phone_number) {
                $customerData['phone'] = $employer->company_phone_number;
            }

            $customer = $this->stripe->customers->create($customerData);

            $employer->stripe_customer_id = $customer->id;
            $employer->save();

            Log::info('Stripe customer created successfully', [
                'employer_id' => $employer->id,
                'customer_id' => $customer->id
            ]);

            return $customer->id;
        } catch (ApiErrorException $e) {
            Log::error('Stripe create customer error', [
                'employer' => $employer->id,
                'error' => $e->getMessage(),
                'code' => $e->getStripeCode()
            ]);

            throw new \Exception('Failed to create Stripe customer: ' . $e->getMessage());
        }
    }

    /**
     * Create subscription record in database
     *
     * @param Employer $employer
     * @param SubscriptionPlan $plan
     * @param Session $session
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
            'subscription_id' => $session->subscription ?? $session->id,
            'job_posts_left' => $plan->job_posts_limit,
            'featured_jobs_left' => $plan->featured_jobs_limit,
            'cv_downloads_left' => $plan->resume_views_limit,
            'payment_type' => $plan->payment_type,
            'is_active' => false, // Will be activated when payment is confirmed
            'subscriber_info' => [
                'email' => $employer->user->email ?? $employer->company_email,
                'name' => $employer->company_name,
                'customer_id' => $employer->stripe_customer_id
            ],
            'billing_info' => [
                'payment_method' => 'Stripe',
                'status' => 'pending'
            ]
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
            Log::warning('Attempting to cancel subscription without Stripe subscription ID', [
                'subscription_id' => $subscription->id
            ]);
            return false;
        }

        try {
            // For one-time payments, just mark as cancelled in our system
            if ($subscription->isOneTime()) {
                $subscription->is_active = false;
                $subscription->save();
                return true;
            }

            $this->stripe->subscriptions->cancel($subscription->subscription_id, [
                'cancel_at_period_end' => false,
            ]);

            $subscription->is_active = false;
            $subscription->save();

            Log::info('Stripe subscription cancelled successfully', [
                'subscription_id' => $subscription->id,
                'stripe_subscription_id' => $subscription->subscription_id
            ]);

            return true;
        } catch (ApiErrorException $e) {
            Log::error('Stripe cancel subscription error', [
                'subscription' => $subscription->toArray(),
                'error' => $e->getMessage(),
                'code' => $e->getStripeCode()
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
            return ['subscriptions' => [], 'has_more' => false];
        }

        try {
            $subscriptions = $this->stripe->subscriptions->all([
                'customer' => $employer->stripe_customer_id,
                'limit' => 100,
                'expand' => ['data.latest_invoice', 'data.items.data.price.product']
            ]);

            return [
                'subscriptions' => $subscriptions->data,
                'has_more' => $subscriptions->has_more,
            ];
        } catch (ApiErrorException $e) {
            Log::error('Stripe list subscriptions error', [
                'employer' => $employer->id,
                'customer_id' => $employer->stripe_customer_id,
                'error' => $e->getMessage(),
                'code' => $e->getStripeCode()
            ]);

            return ['subscriptions' => [], 'has_more' => false];
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
                'expand' => ['latest_invoice', 'customer', 'items.data.price.product']
            ]);

            return $subscription->toArray();
        } catch (ApiErrorException $e) {
            Log::error('Stripe get subscription details error', [
                'subscriptionId' => $subscriptionId,
                'error' => $e->getMessage(),
                'code' => $e->getStripeCode()
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

            Log::info('Stripe subscription suspended successfully', [
                'subscription_id' => $subscription->id,
                'stripe_subscription_id' => $subscription->subscription_id
            ]);

            return true;
        } catch (ApiErrorException $e) {
            Log::error('Stripe suspend subscription error', [
                'subscription' => $subscription->toArray(),
                'error' => $e->getMessage(),
                'code' => $e->getStripeCode()
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

            Log::info('Stripe subscription reactivated successfully', [
                'subscription_id' => $subscription->id,
                'stripe_subscription_id' => $subscription->subscription_id
            ]);

            return true;
        } catch (ApiErrorException $e) {
            Log::error('Stripe reactivate subscription error', [
                'subscription' => $subscription->toArray(),
                'error' => $e->getMessage(),
                'code' => $e->getStripeCode()
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
                'metadata' => [
                    'employer_id' => $subscription->employer_id,
                    'plan_id' => $newPlan->id,
                    'updated_at' => now()->toISOString()
                ]
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

            Log::info('Stripe subscription plan updated successfully', [
                'subscription_id' => $subscription->id,
                'old_plan_id' => $subscription->subscription_plan_id,
                'new_plan_id' => $newPlan->id
            ]);

            return true;
        } catch (ApiErrorException $e) {
            Log::error('Stripe update subscription plan error', [
                'subscription' => $subscription->toArray(),
                'newPlan' => $newPlan->toArray(),
                'error' => $e->getMessage(),
                'code' => $e->getStripeCode()
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
                'expand' => ['data.payment_intent', 'data.charge']
            ]);

            return [
                'transactions' => $invoices->data,
                'has_more' => $invoices->has_more,
            ];
        } catch (ApiErrorException $e) {
            Log::error('Stripe get subscription transactions error', [
                'subscriptionId' => $subscriptionId,
                'error' => $e->getMessage(),
                'code' => $e->getStripeCode()
            ]);

            return ['transactions' => [], 'has_more' => false];
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

                case 'checkout.session.async_payment_succeeded':
                    return $this->handleCheckoutSessionAsyncPaymentSucceeded($event->data->object);

                case 'checkout.session.async_payment_failed':
                    return $this->handleCheckoutSessionAsyncPaymentFailed($event->data->object);

                case 'customer.subscription.created':
                    return $this->handleSubscriptionCreated($event->data->object);

                case 'customer.subscription.updated':
                    return $this->handleSubscriptionUpdated($event->data->object);

                case 'customer.subscription.deleted':
                    return $this->handleSubscriptionDeleted($event->data->object);

                case 'customer.subscription.paused':
                    return $this->handleSubscriptionPaused($event->data->object);

                case 'customer.subscription.resumed':
                    return $this->handleSubscriptionResumed($event->data->object);

                case 'invoice.paid':
                    return $this->handleInvoicePaid($event->data->object);

                case 'invoice.payment_succeeded':
                    return $this->handlePaymentSucceeded($event->data->object);

                case 'invoice.payment_failed':
                    return $this->handlePaymentFailed($event->data->object);

                case 'payment_intent.succeeded':
                    return $this->handlePaymentIntentSucceeded($event->data->object);

                case 'payment_intent.payment_failed':
                    return $this->handlePaymentIntentFailed($event->data->object);

                default:
                    Log::info('Unhandled Stripe webhook event', ['type' => $event->type]);
                    return true;
            }
        } catch (\Exception $e) {
            Log::error('Stripe webhook error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return false;
        }
    }

    /**
     * Handle checkout session completed event
     *
     * @param Session $session
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

            // Update billing info
            $billingInfo = $subscription->billing_info ?? [];
            $billingInfo['status'] = 'completed';
            $billingInfo['payment_intent'] = $session->payment_intent;
            $subscription->billing_info = $billingInfo;

            $subscription->save();

            // Send notification
            $this->sendActivationNotification($subscription);
        } else {
            // For subscriptions, update with Stripe subscription ID
            if ($session->subscription) {
                $subscription->subscription_id = $session->subscription;

                // Update billing info
                $billingInfo = $subscription->billing_info ?? [];
                $billingInfo['status'] = 'active';
                $billingInfo['stripe_subscription_id'] = $session->subscription;
                $subscription->billing_info = $billingInfo;

                $subscription->save();
            }
        }

        Log::info('Stripe checkout session completed', [
            'session_id' => $session->id,
            'subscription_id' => $subscription->id,
            'mode' => $session->mode
        ]);

        return true;
    }

    /**
     * Handle checkout session async payment succeeded event
     *
     * @param Session $session
     * @return bool
     */
    protected function handleCheckoutSessionAsyncPaymentSucceeded($session): bool
    {
        $subscription = Subscription::where('payment_reference', $session->id)
            ->where('payment_method', 'stripe')
            ->first();

        if (!$subscription) {
            Log::error('Stripe subscription not found for async payment', ['sessionId' => $session->id]);
            return false;
        }

        $subscription->is_active = true;
        $subscription->transaction_id = $session->payment_intent;

        // Update billing info
        $billingInfo = $subscription->billing_info ?? [];
        $billingInfo['status'] = 'completed';
        $billingInfo['payment_intent'] = $session->payment_intent;
        $subscription->billing_info = $billingInfo;

        $subscription->save();

        $this->sendActivationNotification($subscription);

        return true;
    }

    /**
     * Handle checkout session async payment failed event
     *
     * @param Session $session
     * @return bool
     */
    protected function handleCheckoutSessionAsyncPaymentFailed($session): bool
    {
        $subscription = Subscription::where('payment_reference', $session->id)
            ->where('payment_method', 'stripe')
            ->first();

        if (!$subscription) {
            Log::error('Stripe subscription not found for failed payment', ['sessionId' => $session->id]);
            return false;
        }

        // Update billing info
        $billingInfo = $subscription->billing_info ?? [];
        $billingInfo['status'] = 'failed';
        $billingInfo['failure_reason'] = 'async_payment_failed';
        $subscription->billing_info = $billingInfo;

        $subscription->save();

        Log::warning('Stripe async payment failed', [
            'session_id' => $session->id,
            'subscription_id' => $subscription->id
        ]);

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
                ->where('is_active', false)
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

        // Update subscription with Stripe details
        $this->updateSubscriptionWithStripeDetails($subscription, $stripeSubscription->toArray());

        // Update subscription status based on Stripe status
        if (in_array($stripeSubscription->status, ['active', 'trialing'])) {
            $subscription->is_active = true;
            $this->sendActivationNotification($subscription);
        }

        $subscription->save();

        Log::info('Stripe subscription created', [
            'stripe_subscription_id' => $stripeSubscription->id,
            'subscription_id' => $subscription->id,
            'status' => $stripeSubscription->status
        ]);

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

        // Update subscription with Stripe details
        $this->updateSubscriptionWithStripeDetails($subscription, $stripeSubscription->toArray());

        $subscription->save();

        // Send activation notification if subscription became active
        if (!$wasActive && $subscription->is_active) {
            $this->sendActivationNotification($subscription);
        }

        Log::info('Stripe subscription updated', [
            'stripe_subscription_id' => $stripeSubscription->id,
            'subscription_id' => $subscription->id,
            'status' => $stripeSubscription->status
        ]);

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

        Log::info('Stripe subscription deleted', [
            'stripe_subscription_id' => $stripeSubscription->id,
            'subscription_id' => $subscription->id
        ]);

        return true;
    }

    /**
     * Handle subscription paused event
     *
     * @param \Stripe\Subscription $stripeSubscription
     * @return bool
     */
    protected function handleSubscriptionPaused($stripeSubscription): bool
    {
        $subscription = Subscription::where('subscription_id', $stripeSubscription->id)
            ->where('payment_method', 'stripe')
            ->first();

        if (!$subscription) {
            Log::error('Stripe subscription not found', ['subscriptionId' => $stripeSubscription->id]);
            return false;
        }

        $subscription->is_suspended = true;
        $subscription->save();

        Log::info('Stripe subscription paused', [
            'stripe_subscription_id' => $stripeSubscription->id,
            'subscription_id' => $subscription->id
        ]);

        return true;
    }

    /**
     * Handle subscription resumed event
     *
     * @param \Stripe\Subscription $stripeSubscription
     * @return bool
     */
    protected function handleSubscriptionResumed($stripeSubscription): bool
    {
        $subscription = Subscription::where('subscription_id', $stripeSubscription->id)
            ->where('payment_method', 'stripe')
            ->first();

        if (!$subscription) {
            Log::error('Stripe subscription not found', ['subscriptionId' => $stripeSubscription->id]);
            return false;
        }

        $subscription->is_active = true;
        $subscription->is_suspended = false;
        $subscription->save();

        Log::info('Stripe subscription resumed', [
            'stripe_subscription_id' => $stripeSubscription->id,
            'subscription_id' => $subscription->id
        ]);

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

        // Update billing info
        $billingInfo = $subscription->billing_info ?? [];
        $billingInfo['last_invoice_id'] = $invoice->id;
        $billingInfo['last_payment_intent'] = $invoice->payment_intent;
        $billingInfo['last_payment_date'] = date('Y-m-d H:i:s', $invoice->status_transitions->paid_at);
        $subscription->billing_info = $billingInfo;

        $subscription->save();

        Log::info('Stripe invoice paid', [
            'invoice_id' => $invoice->id,
            'subscription_id' => $subscription->id
        ]);

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

        Log::info('Stripe payment succeeded', [
            'invoice_id' => $invoice->id,
            'subscription_id' => $subscription->id
        ]);

        return true;
    }

    /**
     * Handle payment failed event
     *
     * @param \Stripe\Invoice $invoice
     * @return bool
     */
    protected function handlePaymentFailed($invoice): bool
    {
        if (!$invoice->subscription) {
            return true; // Not a subscription invoice
        }

        $subscription = Subscription::where('subscription_id', $invoice->subscription)
            ->where('payment_method', 'stripe')
            ->first();

        if (!$subscription) {
            Log::error('Stripe subscription not found for failed payment', [
                'subscriptionId' => $invoice->subscription,
                'invoiceId' => $invoice->id
            ]);
            return false;
        }

        // Update billing info with failure details
        $billingInfo = $subscription->billing_info ?? [];
        $billingInfo['last_failed_invoice_id'] = $invoice->id;
        $billingInfo['last_failure_date'] = date('Y-m-d H:i:s');
        $billingInfo['failure_reason'] = 'payment_failed';
        $subscription->billing_info = $billingInfo;

        $subscription->save();

        Log::warning('Stripe payment failed', [
            'invoice_id' => $invoice->id,
            'subscription_id' => $subscription->id
        ]);

        return true;
    }

    /**
     * Handle payment intent succeeded event
     *
     * @param PaymentIntent $paymentIntent
     * @return bool
     */
    protected function handlePaymentIntentSucceeded($paymentIntent): bool
    {
        // For one-time payments, find by transaction_id or payment_reference
        $subscription = Subscription::where('transaction_id', $paymentIntent->id)
            ->orWhere('payment_reference', $paymentIntent->id)
            ->where('payment_method', 'stripe')
            ->first();

        if (!$subscription) {
            // Try to find by metadata
            if (isset($paymentIntent->metadata['employer_id'])) {
                $subscription = Subscription::where('employer_id', $paymentIntent->metadata['employer_id'])
                    ->where('payment_method', 'stripe')
                    ->where('is_active', false)
                    ->latest()
                    ->first();
            }
        }

        if (!$subscription) {
            Log::info('Stripe payment intent succeeded but no matching subscription found', [
                'payment_intent_id' => $paymentIntent->id
            ]);
            return true;
        }

        $subscription->transaction_id = $paymentIntent->id;

        if (!$subscription->is_active) {
            $subscription->is_active = true;
            $this->sendActivationNotification($subscription);
        }

        $subscription->save();

        Log::info('Stripe payment intent succeeded', [
            'payment_intent_id' => $paymentIntent->id,
            'subscription_id' => $subscription->id
        ]);

        return true;
    }

    /**
     * Handle payment intent failed event
     *
     * @param PaymentIntent $paymentIntent
     * @return bool
     */
    protected function handlePaymentIntentFailed($paymentIntent): bool
    {
        // Find subscription by payment intent
        $subscription = Subscription::where('transaction_id', $paymentIntent->id)
            ->orWhere('payment_reference', $paymentIntent->id)
            ->where('payment_method', 'stripe')
            ->first();

        if (!$subscription) {
            Log::info('Stripe payment intent failed but no matching subscription found', [
                'payment_intent_id' => $paymentIntent->id
            ]);
            return true;
        }

        // Update billing info with failure details
        $billingInfo = $subscription->billing_info ?? [];
        $billingInfo['last_failed_payment_intent'] = $paymentIntent->id;
        $billingInfo['last_failure_date'] = date('Y-m-d H:i:s');
        $billingInfo['failure_reason'] = $paymentIntent->last_payment_error->message ?? 'payment_intent_failed';
        $subscription->billing_info = $billingInfo;

        $subscription->save();

        Log::warning('Stripe payment intent failed', [
            'payment_intent_id' => $paymentIntent->id,
            'subscription_id' => $subscription->id,
            'error' => $paymentIntent->last_payment_error->message ?? 'Unknown error'
        ]);

        return true;
    }

    /**
     * Update subscription with Stripe details
     *
     * @param Subscription $subscription
     * @param array $details
     * @return void
     */
    public function updateSubscriptionWithStripeDetails(Subscription $subscription, array $details): void
    {
        // Store customer information if available
        if (isset($details['customer'])) {
            $customerInfo = is_string($details['customer'])
                ? ['customer_id' => $details['customer']]
                : $details['customer'];

            $subscription->subscriber_info = [
                'email' => $customerInfo['email'] ?? null,
                'name' => $customerInfo['name'] ?? null,
                'customer_id' => is_string($details['customer']) ? $details['customer'] : ($customerInfo['id'] ?? null)
            ];
        }

        // Store billing information if available
        $billingInfo = $subscription->billing_info ?? [];
        $billingInfo['status'] = $details['status'] ?? null;
        $billingInfo['current_period_start'] = isset($details['current_period_start'])
            ? date('Y-m-d H:i:s', $details['current_period_start']) : null;
        $billingInfo['current_period_end'] = isset($details['current_period_end'])
            ? date('Y-m-d H:i:s', $details['current_period_end']) : null;
        $billingInfo['cancel_at'] = isset($details['cancel_at'])
            ? date('Y-m-d H:i:s', $details['cancel_at']) : null;
        $billingInfo['canceled_at'] = isset($details['canceled_at'])
            ? date('Y-m-d H:i:s', $details['canceled_at']) : null;
        $billingInfo['trial_start'] = isset($details['trial_start'])
            ? date('Y-m-d H:i:s', $details['trial_start']) : null;
        $billingInfo['trial_end'] = isset($details['trial_end'])
            ? date('Y-m-d H:i:s', $details['trial_end']) : null;
        $billingInfo['payment_method'] = 'Stripe';

        $subscription->billing_info = $billingInfo;

        // Update subscription end date based on current_period_end if available
        // Only for recurring subscriptions
        if (!$subscription->isOneTime() && isset($details['current_period_end'])) {
            $subscription->end_date = date('Y-m-d H:i:s', $details['current_period_end']);
        }

        // Update next billing date if available
        if (isset($details['current_period_end'])) {
            $subscription->next_billing_date = date('Y-m-d H:i:s', $details['current_period_end']);
        }

        // Store status if available
        if (isset($details['status'])) {
            $subscription->external_status = $details['status'];
        }

        // Update status update time
        $subscription->status_update_time = now();

        $subscription->save();
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
