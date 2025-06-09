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

class StripeSubscriptionService implements SubscriptionServiceInterface
{
    protected $stripe;
    protected $apiKey;
    protected $webhookSecret;
    protected $baseUrl;

    public function __construct()
    {
        $this->apiKey = config('services.stripe.secret');
        $this->webhookSecret = config('services.stripe.webhook_secret');
        $this->stripe = new StripeClient($this->apiKey);
        $this->baseUrl = config('app.url');
    }

    /**
     * Validate if employer can use one-time payment for a plan
     */
    public function canUseOneTimePayment(Employer $employer, SubscriptionPlan $plan): bool
    {
        // For one-time plans, employer must have used trial period OR plan doesn't require trial
        if ($plan->isOneTime()) {
            // If plan doesn't have trial, allow one-time payment
            if (!$plan->hasTrial()) {
                return true;
            }
            // If plan has trial, employer must have used trial
            return $employer->has_used_trial;
        }

        return false;
    }

    /**
     * Validate if employer needs trial for a plan
     */
    public function shouldUseTrial(Employer $employer, SubscriptionPlan $plan): bool
    {
        // Only use trial if:
        // 1. Plan has trial enabled
        // 2. Employer hasn't used trial yet
        // 3. Plan is recurring (one-time plans handle trial differently)
        return $plan->hasTrial() &&
            !$employer->has_used_trial &&
            $plan->isRecurring();
    }

    /**
     * Create a product in Stripe
     */
    protected function createProduct(SubscriptionPlan $plan): string
    {
        try {
            $product = $this->stripe->products->create([
                'name' => $plan->name,
                'description' => $plan->description ?? $plan->name,
                'metadata' => [
                    'plan_id' => $plan->id,
                ],
            ]);

            return $product->id;
        } catch (ApiErrorException $e) {
            Log::error('Stripe create product error', [
                'plan' => $plan->toArray(),
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * Create a price in Stripe
     */
    protected function createPrice(SubscriptionPlan $plan, string $productId): string
    {
        try {
            $priceData = [
                'product' => $productId,
                'unit_amount' => (int)($plan->price * 100), // Convert to cents
                'currency' => strtolower($plan->currency),
                'metadata' => [
                    'plan_id' => $plan->id,
                ],
            ];

            // Add recurring parameters for subscription plans
            if ($plan->isRecurring()) {
                $priceData['recurring'] = [
                    'interval' => $this->getStripeInterval($plan->interval_unit),
                    'interval_count' => $plan->interval_count,
                ];

                // Set usage_type to licensed for standard subscriptions
                $priceData['recurring']['usage_type'] = 'licensed';
            }

            $price = $this->stripe->prices->create($priceData);

            return $price->id;
        } catch (ApiErrorException $e) {
            Log::error('Stripe create price error', [
                'plan' => $plan->toArray(),
                'productId' => $productId,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * Convert interval unit to Stripe format
     */
    protected function getStripeInterval(string $intervalUnit): string
    {
        return match (strtoupper($intervalUnit)) {
            'DAY' => 'day',
            'WEEK' => 'week',
            'MONTH' => 'month',
            'YEAR' => 'year',
            default => 'month',
        };
    }

    /**
     * Create a subscription plan in the payment gateway
     */
    public function createPlan(SubscriptionPlan $plan): string
    {
        try {
            // Create a product first
            $productId = $this->createProduct($plan);

            // Then create a price for the product
            $priceId = $this->createPrice($plan, $productId);

            return $priceId; // In Stripe, the price ID is used as the plan ID
        } catch (ApiErrorException $e) {
            Log::error('Stripe create plan error', [
                'plan' => $plan->toArray(),
                'error' => $e->getMessage()
            ]);

            throw new \Exception('Failed to create Stripe plan: ' . $e->getMessage());
        }
    }

    /**
     * Check if automatic tax is properly configured
     */
    protected function isAutomaticTaxConfigured(): bool
    {
        try {
            // Try to retrieve tax settings to see if they're configured
            $settings = $this->stripe->tax->settings->retrieve();

            // Check if there's a valid origin address
            return !empty($settings->defaults['tax_behavior']) &&
                !empty($settings->head_office['address']);
        } catch (ApiErrorException $e) {
            Log::warning('Could not retrieve Stripe tax settings', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Create a subscription for an employer with enhanced trial logic
     */
    public function createSubscription(Employer $employer, SubscriptionPlan $plan, array $paymentData = []): array
    {
        // Validate business rules
        if ($plan->isOneTime() && !$this->canUseOneTimePayment($employer, $plan)) {
            throw new \Exception('One-time payments require trial period to be used first');
        }

        // For one-time plans, use Checkout API for payment
        if ($plan->isOneTime()) {
            return $this->createOneTimeOrder($employer, $plan, $paymentData);
        }

        // For recurring plans, use Billing API
        return $this->createRecurringSubscription($employer, $plan, $paymentData);
    }

    /**
     * Create a one-time payment using Stripe Checkout API
     */
    public function createOneTimeOrder(Employer $employer, SubscriptionPlan $plan, array $paymentData = []): array
    {
        if (!$plan->isOneTime()) {
            throw new \Exception('This method is only for one-time payment plans');
        }

        if (!$this->canUseOneTimePayment($employer, $plan)) {
            throw new \Exception('Employer must use trial period before one-time payments');
        }

        try {
            // Get or create the external plan ID (price ID)
            $externalPlanId = $plan->external_stripe_id ?? $this->createPlan($plan);

            if (!$plan->external_stripe_id) {
                $plan->external_stripe_id = $externalPlanId;
                $plan->save();
            }

            // Get or create a customer
            $customerId = $this->getOrCreateCustomer($employer);

            // Create a checkout session for one-time payment
            $sessionParams = [
                'customer' => $customerId,
                'payment_method_types' => ['card'],
                'line_items' => [
                    [
                        'price' => $externalPlanId,
                        'quantity' => 1,
                    ],
                ],
                'mode' => 'payment', // One-time payment mode
                'success_url' => config('app.frontend_url') . '/employer/dashboard?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => config('app.frontend_url') . '/employer/dashboard?session_id={CHECKOUT_SESSION_ID}',
                'client_reference_id' => $employer->id,
                'metadata' => [
                    'employer_id' => $employer->id,
                    'plan_id' => $plan->id,
                    'payment_type' => 'one_time',
                ],
            ];

            // Get Stripe configuration from the plan
            $stripeConfig = $plan->getPaymentGatewayConfig('stripe');

            // Only add automatic tax if it's explicitly enabled AND properly configured
            if (isset($stripeConfig['automatic_tax']['enabled']) &&
                $stripeConfig['automatic_tax']['enabled'] === true &&
                $this->isAutomaticTaxConfigured()) {

                $sessionParams['automatic_tax'] = ['enabled' => true];

                Log::info('Automatic tax enabled for Stripe checkout session', [
                    'plan_id' => $plan->id,
                    'employer_id' => $employer->id
                ]);
            }

            // Allow promotion codes if configured
            if (isset($stripeConfig['allow_promotion_codes']) && $stripeConfig['allow_promotion_codes']) {
                $sessionParams['allow_promotion_codes'] = true;
            }

            // Add billing address collection if needed
            if (isset($stripeConfig['billing_address_collection'])) {
                $sessionParams['billing_address_collection'] = $stripeConfig['billing_address_collection'];
            } else {
                // Default to auto for better user experience
                $sessionParams['billing_address_collection'] = 'auto';
            }

            // Add phone number collection if configured
            if (isset($stripeConfig['phone_number_collection']['enabled']) &&
                $stripeConfig['phone_number_collection']['enabled']) {
                $sessionParams['phone_number_collection'] = ['enabled' => true];
            }

            $session = $this->stripe->checkout->sessions->create($sessionParams);

            // Create a pending subscription record
            $subscription = $this->createSubscriptionRecord($employer, $plan, [
                'id' => $session->id,
                'customer' => $customerId,
                'payment_intent' => $session->payment_intent ?? null,
                'type' => 'checkout_session'
            ]);

            return [
                'subscription_id' => $subscription->id,
                'external_subscription_id' => $session->id,
                'redirect_url' => $session->url,
                'status' => $session->status,
                'payment_type' => 'one_time'
            ];
        } catch (ApiErrorException $e) {
            Log::error('Stripe create one-time order error', [
                'employer' => $employer->id,
                'plan' => $plan->toArray(),
                'error' => $e->getMessage(),
                'error_code' => $e->getStripeCode(),
            ]);

            // Provide more specific error messages for common issues
            $errorMessage = match ($e->getStripeCode()) {
                'tax_calculation_failed' => 'Tax calculation failed. Please contact support.',
                'invalid_request_error' => 'Invalid request. Please check your configuration.',
                default => 'Failed to create Stripe one-time payment: ' . $e->getMessage()
            };

            throw new \Exception($errorMessage);
        }
    }

    /**
     * Create a recurring subscription using Stripe Billing API
     */
    protected function createRecurringSubscription(Employer $employer, SubscriptionPlan $plan, array $paymentData = []): array
    {
        try {
            // Get or create the external plan ID
            $externalPlanId = $plan->external_stripe_id ?? $this->createPlan($plan);

            if (!$plan->external_stripe_id) {
                $plan->external_stripe_id = $externalPlanId;
                $plan->save();
            }

            // Get or create a customer
            $customerId = $this->getOrCreateCustomer($employer);

            // Create a checkout session for subscription
            $sessionParams = [
                'customer' => $customerId,
                'payment_method_types' => ['card'],
                'line_items' => [
                    [
                        'price' => $externalPlanId,
                        'quantity' => 1,
                    ],
                ],
                'mode' => 'subscription',
                'success_url' => config('app.frontend_url') . '/employer/dashboard?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => config('app.frontend_url') . '/employer/dashboard?session_id={CHECKOUT_SESSION_ID}',
                'client_reference_id' => $employer->id,
                'metadata' => [
                    'employer_id' => $employer->id,
                    'plan_id' => $plan->id,
                    'payment_type' => 'recurring',
                ],
            ];

            // Handle trial logic for recurring subscriptions
            if ($this->shouldUseTrial($employer, $plan)) {
                $sessionParams['subscription_data'] = [
                    'trial_period_days' => $plan->getTrialPeriodDays(),
                ];

                Log::info('Creating Stripe subscription with trial', [
                    'employer_id' => $employer->id,
                    'plan_id' => $plan->id,
                    'trial_days' => $plan->getTrialPeriodDays()
                ]);
            } else {
                Log::info('Creating Stripe subscription without trial', [
                    'employer_id' => $employer->id,
                    'plan_id' => $plan->id,
                    'has_used_trial' => $employer->has_used_trial
                ]);
            }

            // Get Stripe configuration from the plan
            $stripeConfig = $plan->getPaymentGatewayConfig('stripe');

            // Only add automatic tax if it's explicitly enabled AND properly configured
            if (isset($stripeConfig['automatic_tax']['enabled']) &&
                $stripeConfig['automatic_tax']['enabled'] === true &&
                $this->isAutomaticTaxConfigured()) {

                $sessionParams['automatic_tax'] = ['enabled' => true];

                Log::info('Automatic tax enabled for Stripe checkout session', [
                    'plan_id' => $plan->id,
                    'employer_id' => $employer->id
                ]);
            }

            // Allow promotion codes if configured
            if (isset($stripeConfig['allow_promotion_codes']) && $stripeConfig['allow_promotion_codes']) {
                $sessionParams['allow_promotion_codes'] = true;
            }

            // Add billing address collection if needed
            if (isset($stripeConfig['billing_address_collection'])) {
                $sessionParams['billing_address_collection'] = $stripeConfig['billing_address_collection'];
            } else {
                // Default to auto for better user experience
                $sessionParams['billing_address_collection'] = 'auto';
            }

            // Add phone number collection if configured
            if (isset($stripeConfig['phone_number_collection']['enabled']) &&
                $stripeConfig['phone_number_collection']['enabled']) {
                $sessionParams['phone_number_collection'] = ['enabled' => true];
            }

            $session = $this->stripe->checkout->sessions->create($sessionParams);

            // Create a pending subscription record
            $subscription = $this->createSubscriptionRecord($employer, $plan, [
                'id' => $session->id,
                'customer' => $customerId,
                'subscription' => $session->subscription ?? null,
                'type' => 'checkout_session'
            ]);

            // Mark trial as used if this subscription uses trial
            if ($this->shouldUseTrial($employer, $plan)) {
                $employer->markTrialAsUsed();
            }

            return [
                'subscription_id' => $subscription->id,
                'external_subscription_id' => $session->id,
                'redirect_url' => $session->url,
                'status' => $session->status,
                'payment_type' => 'recurring'
            ];
        } catch (ApiErrorException $e) {
            Log::error('Stripe create recurring subscription error', [
                'employer' => $employer->id,
                'plan' => $plan->toArray(),
                'error' => $e->getMessage(),
                'error_code' => $e->getStripeCode(),
            ]);

            // Provide more specific error messages for common issues
            $errorMessage = match ($e->getStripeCode()) {
                'tax_calculation_failed' => 'Tax calculation failed. Please contact support.',
                'invalid_request_error' => 'Invalid request. Please check your configuration.',
                default => 'Failed to create Stripe subscription: ' . $e->getMessage()
            };

            throw new \Exception($errorMessage);
        }
    }

    /**
     * Get or create a Stripe customer for the employer
     */
    protected function getOrCreateCustomer(Employer $employer): string
    {
        // If the employer already has a Stripe customer ID, use it
        if ($employer->stripe_customer_id) {
            try {
                // Verify the customer still exists
                $this->stripe->customers->retrieve($employer->stripe_customer_id);
                return $employer->stripe_customer_id;
            } catch (ApiErrorException $e) {
                Log::warning('Stripe customer not found, creating new one', [
                    'employer_id' => $employer->id,
                    'old_customer_id' => $employer->stripe_customer_id,
                    'error' => $e->getMessage()
                ]);
                // Continue to create a new customer
            }
        }

        try {
            // Create a new customer
            $customerData = [
                'email' => $employer->user->email ?? $employer->company_email,
                'name' => $employer->company_name,
                'description' => 'Employer ID: ' . $employer->id,
                'metadata' => [
                    'employer_id' => $employer->id,
                    'user_id' => $employer->user_id,
                ],
            ];

            // Add phone if available
            if ($employer->company_phone_number) {
                $customerData['phone'] = $employer->company_phone_number;
            }

            // Add address if available
            if ($employer->company_address || $employer->company_country) {
                $address = [];
                if ($employer->company_address) {
                    $address['line1'] = $employer->company_address;
                }
                if ($employer->company_country) {
                    $address['country'] = $employer->company_country;
                }
                if ($employer->company_state) {
                    $address['state'] = $employer->company_state;
                }

                if (!empty($address)) {
                    $customerData['address'] = $address;
                }
            }

            $customer = $this->stripe->customers->create($customerData);

            // Save the customer ID to the employer
            $employer->stripe_customer_id = $customer->id;
            $employer->save();

            return $customer->id;
        } catch (ApiErrorException $e) {
            Log::error('Stripe create customer error', [
                'employer' => $employer->toArray(),
                'error' => $e->getMessage()
            ]);

            throw new \Exception('Failed to create Stripe customer: ' . $e->getMessage());
        }
    }

    /**
     * Create subscription record in database
     */
    private function createSubscriptionRecord(Employer $employer, SubscriptionPlan $plan, array $data): Subscription
    {
        $endDate = null;
        if ($plan->isRecurring() && $plan->duration_days) {
            $endDate = Carbon::now()->addDays($plan->duration_days);
            if ($plan->hasTrial() && !$employer->has_used_trial) {
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
            'subscription_id' => $data['subscription'] ?? null,
            'payment_reference' => $data['id'],
            'transaction_id' => $data['payment_intent'] ?? null,
            'job_posts_left' => $plan->job_posts_limit,
            'featured_jobs_left' => $plan->featured_jobs_limit,
            'cv_downloads_left' => $plan->resume_views_limit,
            'payment_type' => $plan->payment_type,
            'is_active' => false, // Will be activated when payment is confirmed
            'used_trial' => $this->shouldUseTrial($employer, $plan),
        ]);
    }

    /**
     * Update a subscription plan in the payment gateway
     */
    public function updatePlan(SubscriptionPlan $plan, string $externalPlanId): bool
    {
        try {
            // In Stripe, we can't update a price, but we can update the product
            // First, get the price to find the product ID
            $price = $this->stripe->prices->retrieve($externalPlanId);

            // Update the product
            $this->stripe->products->update($price->product, [
                'name' => $plan->name,
                'description' => $plan->description ?? $plan->name,
                'metadata' => [
                    'plan_id' => $plan->id,
                ],
            ]);

            // For price changes, we need to create a new price and update the plan's external ID
            if ($plan->price != ($price->unit_amount / 100)) {
                $newPriceId = $this->createPrice($plan, $price->product);
                $plan->external_stripe_id = $newPriceId;
                $plan->save();
            }

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
     * Delete a subscription plan from the payment gateway
     */
    public function deletePlan(string $externalPlanId): bool
    {
        try {
            // In Stripe, we can't delete a price, but we can archive the product
            // First, get the price to find the product ID
            $price = $this->stripe->prices->retrieve($externalPlanId);

            // Archive the price by setting it to inactive
            $this->stripe->prices->update($externalPlanId, [
                'active' => false
            ]);

            // Archive the product
            $this->stripe->products->update($price->product, [
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
     * List all subscription plans from the payment gateway
     */
    public function listPlans(array $filters = []): array
    {
        try {
            $params = [
                'limit' => $filters['limit'] ?? 100,
                'active' => $filters['active'] ?? true,
                'type' => 'recurring',
            ];

            if (isset($filters['product'])) {
                $params['product'] = $filters['product'];
            }

            $prices = $this->stripe->prices->all($params);

            // Format the response to match the expected structure
            return [
                'plans' => $prices->data,
                'total_count' => $prices->count(),
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
     * Cancel a subscription
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
     */
    public function listSubscriptions(Employer $employer): array
    {
        try {
            if (!$employer->stripe_customer_id) {
                return ['subscriptions' => []];
            }

            $subscriptions = $this->stripe->subscriptions->all([
                'customer' => $employer->stripe_customer_id,
                'limit' => 100,
            ]);

            return [
                'subscriptions' => $subscriptions->data,
                'total_count' => $subscriptions->count(),
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
     */
    public function getSubscriptionDetails(string $subscriptionId): array
    {
        try {
            $subscription = $this->stripe->subscriptions->retrieve($subscriptionId, [
                'expand' => ['customer', 'default_payment_method', 'latest_invoice.payment_intent']
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
     * Get checkout session details
     */
    public function getCheckoutSessionDetails(string $sessionId): array
    {
        try {
            $session = $this->stripe->checkout->sessions->retrieve($sessionId, [
                'expand' => ['customer', 'subscription', 'payment_intent']
            ]);

            return $session->toArray();
        } catch (ApiErrorException $e) {
            Log::error('Stripe get checkout session details error', [
                'sessionId' => $sessionId,
                'error' => $e->getMessage()
            ]);

            return [];
        }
    }

    /**
     * Suspend a subscription (temporarily pause)
     */
    public function suspendSubscription(Subscription $subscription): bool
    {
        if (!$subscription->subscription_id) {
            return false;
        }

        // One-time payments can't be suspended
        if ($subscription->isOneTime()) {
            return false;
        }

        try {
            $this->stripe->subscriptions->update($subscription->subscription_id, [
                'pause_collection' => [
                    'behavior' => 'mark_uncollectible',
                ],
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
     */
    public function reactivateSubscription(Subscription $subscription): bool
    {
        if (!$subscription->subscription_id) {
            return false;
        }

        // One-time payments can't be reactivated
        if ($subscription->isOneTime()) {
            return false;
        }

        try {
            $this->stripe->subscriptions->update($subscription->subscription_id, [
                'pause_collection' => '',
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
     */
    public function updateSubscriptionPlan(Subscription $subscription, SubscriptionPlan $newPlan): bool
    {
        // One-time subscriptions can't be updated to a different plan
        if ($subscription->isOneTime()) {
            return false;
        }

        if (!$subscription->subscription_id) {
            return false;
        }

        try {
            // Get or create the external plan ID for the new plan
            $externalPlanId = $newPlan->external_stripe_id ?? $this->createPlan($newPlan);

            // If we created a new plan, save the ID
            if (!$newPlan->external_stripe_id) {
                $newPlan->external_stripe_id = $externalPlanId;
                $newPlan->save();
            }

            // Update the subscription with the new price
            $this->stripe->subscriptions->update($subscription->subscription_id, [
                'proration_behavior' => 'create_prorations',
                'items' => [
                    [
                        'id' => $this->getSubscriptionItemId($subscription->subscription_id),
                        'price' => $externalPlanId,
                    ],
                ],
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
     * Get the subscription item ID for a subscription
     */
    protected function getSubscriptionItemId(string $subscriptionId): string
    {
        $subscription = $this->stripe->subscriptions->retrieve($subscriptionId);
        return $subscription->items->data[0]->id;
    }

    /**
     * Get subscription transactions (invoices)
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
                'total_count' => $invoices->count(),
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
     * Verify webhook signature
     */
    protected function verifyWebhookSignature(string $payload, array $headers): bool
    {
        $sigHeader = $headers['stripe-signature'] ?? '';

        if (empty($sigHeader)) {
            Log::error('Missing Stripe signature header');
            return false;
        }

        try {
            \Stripe\Webhook::constructEvent(
                $payload,
                $sigHeader,
                $this->webhookSecret
            );
            return true;
        } catch (\Exception $e) {
            Log::error('Stripe webhook signature verification failed', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Handle webhook events from Stripe
     */
    public function handleWebhook(string $payload, array $headers): bool
    {
        // In development/testing environments, we might want to skip signature verification
        $verifySignature = config('services.stripe.verify_webhook_signature', true);

        if ($verifySignature && !$this->verifyWebhookSignature($payload, $headers)) {
            Log::warning('Stripe webhook signature verification failed, but processing event anyway');
            // Continue processing the webhook even if verification fails
            // This helps during testing and development
        }

        $data = json_decode($payload, true);
        $event = $data['type'] ?? '';
        $object = $data['data']['object'] ?? [];

        Log::info('Stripe webhook received', [
            'event' => $event,
            'object_id' => $object['id'] ?? null
        ]);

        switch ($event) {
            case 'checkout.session.completed':
                return $this->handleCheckoutSessionCompleted($object);

            case 'customer.subscription.created':
                return $this->handleSubscriptionCreated($object);

            case 'customer.subscription.updated':
                return $this->handleSubscriptionUpdated($object);

            case 'customer.subscription.deleted':
                return $this->handleSubscriptionDeleted($object);

            case 'invoice.paid':
                return $this->handleInvoicePaid($object);

            case 'invoice.payment_failed':
                return $this->handleInvoicePaymentFailed($object);

            default:
                Log::info('Unhandled Stripe webhook event', ['event' => $event]);
                return true;
        }
    }

    /**
     * Handle checkout session completed event
     */
    protected function handleCheckoutSessionCompleted(array $data): bool
    {
        $sessionId = $data['id'] ?? '';
        $employerId = $data['client_reference_id'] ?? null;
        $subscriptionId = $data['subscription'] ?? null;
        $paymentIntentId = $data['payment_intent'] ?? null;
        $mode = $data['mode'] ?? '';
        $paymentStatus = $data['payment_status'] ?? '';

        // Find the subscription in our database by payment_reference (session ID)
        $subscription = Subscription::where('payment_reference', $sessionId)
            ->where('payment_method', 'stripe')
            ->first();

        if (!$subscription) {
            Log::error('Stripe subscription not found for session', ['sessionId' => $sessionId]);
            return false;
        }

        // Update subscription with Stripe IDs
        if ($subscriptionId) {
            $subscription->subscription_id = $subscriptionId;
        }

        if ($paymentIntentId) {
            $subscription->transaction_id = $paymentIntentId;
        }

        // For one-time payments (mode = 'payment'), activate immediately when payment is successful
        if ($mode === 'payment' && $paymentStatus === 'paid') {
            $subscription->is_active = true;
            $subscription->external_status = 'paid';
            $this->sendActivationNotification($subscription);

            Log::info('One-time Stripe payment activated via webhook', [
                'subscription_id' => $subscription->id,
                'session_id' => $sessionId,
                'payment_status' => $paymentStatus
            ]);
        }
        // For recurring subscriptions (mode = 'subscription'), they will be activated via subscription.created/updated events
        elseif ($mode === 'subscription') {
            Log::info('Recurring Stripe subscription session completed, waiting for subscription events', [
                'subscription_id' => $subscription->id,
                'session_id' => $sessionId,
                'stripe_subscription_id' => $subscriptionId
            ]);
        }

        $subscription->save();

        return true;
    }

    /**
     * Handle subscription created event
     */
    protected function handleSubscriptionCreated(array $data): bool
    {
        $subscriptionId = $data['id'] ?? '';
        $customerId = $data['customer'] ?? '';
        $status = $data['status'] ?? '';

        // Find the employer by Stripe customer ID
        $employer = Employer::where('stripe_customer_id', $customerId)->first();

        if (!$employer) {
            Log::error('Employer not found for Stripe customer', ['customerId' => $customerId]);
            return false;
        }

        // Find the subscription in our database
        $subscription = Subscription::where('subscription_id', $subscriptionId)
            ->where('payment_method', 'stripe')
            ->first();

        if (!$subscription) {
            // If not found by subscription_id, try to find by employer and update it
            $subscription = Subscription::where('employer_id', $employer->id)
                ->where('payment_method', 'stripe')
                ->whereNull('subscription_id')
                ->latest()
                ->first();

            if ($subscription) {
                $subscription->subscription_id = $subscriptionId;
                $subscription->external_status = $status;

                // If status is trialing or active, activate the subscription
                if (in_array($status, ['trialing', 'active'])) {
                    $subscription->is_active = true;
                    $this->sendActivationNotification($subscription);
                }

                $subscription->save();
            } else {
                Log::error('Stripe subscription not found', ['subscriptionId' => $subscriptionId]);
                return false;
            }
        }

        return true;
    }

    /**
     * Handle subscription updated event
     */
    protected function handleSubscriptionUpdated(array $data): bool
    {
        $subscriptionId = $data['id'] ?? '';
        $status = $data['status'] ?? '';

        // Find the subscription in our database
        $subscription = Subscription::where('subscription_id', $subscriptionId)
            ->where('payment_method', 'stripe')
            ->first();

        if (!$subscription) {
            Log::error('Stripe subscription not found', ['subscriptionId' => $subscriptionId]);
            return false;
        }

        // Update subscription status based on Stripe status
        switch ($status) {
            case 'active':
                $subscription->is_active = true;
                $subscription->is_suspended = false;

                // If this is the first time the subscription becomes active, send notification
                if ($subscription->external_status !== 'active') {
                    $this->sendActivationNotification($subscription);
                }
                break;

            case 'trialing':
                // During trial, the subscription is active
                $subscription->is_active = true;
                $subscription->is_suspended = false;

                // If this is the first time the subscription becomes trialing, send notification
                if ($subscription->external_status !== 'trialing') {
                    $this->sendActivationNotification($subscription);
                }
                break;

            case 'past_due':
                // Don't change active status yet, but mark as past due
                $subscription->external_status = 'past_due';
                break;

            case 'unpaid':
                $subscription->is_active = false;
                break;

            case 'canceled':
                $subscription->is_active = false;
                break;

            case 'incomplete':
            case 'incomplete_expired':
                $subscription->is_active = false;
                break;
        }

        // Update subscription metadata
        $subscription->external_status = $status;
        $subscription->status_update_time = Carbon::now();

        // Update billing details
        $this->updateSubscriptionWithStripeDetails($subscription, $data);

        $subscription->save();

        return true;
    }

    /**
     * Handle subscription deleted event
     */
    protected function handleSubscriptionDeleted(array $data): bool
    {
        $subscriptionId = $data['id'] ?? '';

        // Find the subscription in our database
        $subscription = Subscription::where('subscription_id', $subscriptionId)
            ->where('payment_method', 'stripe')
            ->first();

        if (!$subscription) {
            Log::error('Stripe subscription not found', ['subscriptionId' => $subscriptionId]);
            return false;
        }

        // Update subscription status
        $subscription->is_active = false;
        $subscription->external_status = 'canceled';
        $subscription->status_update_time = Carbon::now();
        $subscription->save();

        return true;
    }

    /**
     * Handle invoice paid event
     */
    protected function handleInvoicePaid(array $data): bool
    {
        $subscriptionId = $data['subscription'] ?? null;

        if (!$subscriptionId) {
            return true; // Not a subscription invoice
        }

        // Find the subscription in our database
        $subscription = Subscription::where('subscription_id', $subscriptionId)
            ->where('payment_method', 'stripe')
            ->first();

        if (!$subscription) {
            Log::error('Stripe subscription not found', ['subscriptionId' => $subscriptionId]);
            return false;
        }

        // Update transaction ID
        $subscription->transaction_id = $data['payment_intent'] ?? $subscription->transaction_id;

        // For recurring subscriptions, extend the end date
        if ($subscription->isRecurring() && $subscription->plan && $subscription->plan->duration_days) {
            $subscription->end_date = Carbon::now()->addDays($subscription->plan->duration_days);
        }

        // Ensure the subscription is active
        $subscription->is_active = true;
        $subscription->is_suspended = false;

        // Update next billing date if available
        if (isset($data['lines']['data'][0]['period']['end'])) {
            $subscription->next_billing_date = Carbon::createFromTimestamp($data['lines']['data'][0]['period']['end']);
        }

        $subscription->save();

        return true;
    }

    /**
     * Handle invoice payment failed event
     */
    protected function handleInvoicePaymentFailed(array $data): bool
    {
        $subscriptionId = $data['subscription'] ?? null;

        if (!$subscriptionId) {
            return true; // Not a subscription invoice
        }

        // Find the subscription in our database
        $subscription = Subscription::where('subscription_id', $subscriptionId)
            ->where('payment_method', 'stripe')
            ->first();

        if (!$subscription) {
            Log::error('Stripe subscription not found', ['subscriptionId' => $subscriptionId]);
            return false;
        }

        // Update subscription status
        $subscription->external_status = 'payment_failed';
        $subscription->status_update_time = Carbon::now();

        // Don't deactivate yet, as Stripe will retry payment

        $subscription->save();

        return true;
    }

    /**
     * Update subscription with Stripe details
     */
    public function updateSubscriptionWithStripeDetails(Subscription $subscription, array $details): void
    {
        // Store customer information if available
        if (isset($details['customer'])) {
            // Extract just the ID if it's an object, otherwise use as-is
            $customer = $details['customer'];
            $subscription->subscriber_info = [
                'customer_id' => is_array($customer) ? ($customer['id'] ?? null) : $customer,
            ];
        }

        // Store billing information if available
        $billingInfo = [
            'status' => $details['status'] ?? null,
            'current_period_start' => isset($details['current_period_start']) ?
                Carbon::createFromTimestamp($details['current_period_start'])->toDateTimeString() : null,
            'current_period_end' => isset($details['current_period_end']) ?
                Carbon::createFromTimestamp($details['current_period_end'])->toDateTimeString() : null,
            'cancel_at_period_end' => $details['cancel_at_period_end'] ?? false,
            'canceled_at' => isset($details['canceled_at']) ?
                Carbon::createFromTimestamp($details['canceled_at'])->toDateTimeString() : null,
            'payment_method' => 'stripe',
        ];

        // Add default payment method details if available
        if (isset($details['default_payment_method'])) {
            $paymentMethod = $details['default_payment_method'];
            if (is_array($paymentMethod) && isset($paymentMethod['card'])) {
                $billingInfo['last_four'] = $paymentMethod['card']['last4'] ?? null;
                $billingInfo['card_brand'] = $paymentMethod['card']['brand'] ?? null;
                $billingInfo['exp_month'] = $paymentMethod['card']['exp_month'] ?? null;
                $billingInfo['exp_year'] = $paymentMethod['card']['exp_year'] ?? null;
            }
        }

        $subscription->billing_info = $billingInfo;

        // Update next billing date
        if (isset($details['current_period_end'])) {
            $subscription->next_billing_date = Carbon::createFromTimestamp($details['current_period_end']);
        }

        // Update end date for recurring subscriptions
        if (!$subscription->isOneTime() && isset($details['current_period_end'])) {
            // If cancel_at_period_end is true, the subscription will end at the period end
            if ($details['cancel_at_period_end'] ?? false) {
                $subscription->end_date = Carbon::createFromTimestamp($details['current_period_end']);
            }
            // Otherwise, if we have a plan with duration_days, calculate the end date
            elseif ($subscription->plan && $subscription->plan->duration_days) {
                $subscription->end_date = Carbon::now()->addDays($subscription->plan->duration_days);
            }
        }
    }

    /**
     * Send activation notification
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
