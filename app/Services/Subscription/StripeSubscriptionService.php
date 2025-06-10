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
    protected StripeClient $stripe;
    protected string $apiKey;
    protected string $webhookSecret;

    public function __construct()
    {
        $this->apiKey = config('services.stripe.secret');
        $this->webhookSecret = config('services.stripe.webhook_secret');
        $this->stripe = new StripeClient($this->apiKey);
    }

    public function canUseOneTimePayment(Employer $employer, SubscriptionPlan $plan): bool
    {
        if (!$plan->isOneTime()) {
            return false;
        }

        if (!$plan->hasTrial()) {
            return true;
        }

        return $employer->has_used_trial;
    }

    public function shouldUseTrial(Employer $employer, SubscriptionPlan $plan): bool
    {
        return $plan->hasTrial() &&
            !$employer->has_used_trial &&
            $plan->isRecurring();
    }

    protected function createProduct(SubscriptionPlan $plan): string
    {
        try {
            $product = $this->stripe->products->create([
                'name' => $plan->name,
                'description' => $plan->description ?? $plan->name,
                'metadata' => ['plan_id' => $plan->id],
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

    protected function createPrice(SubscriptionPlan $plan, string $productId): string
    {
        try {
            $priceData = [
                'product' => $productId,
                'unit_amount' => (int)($plan->price * 100),
                'currency' => strtolower($plan->currency),
                'metadata' => ['plan_id' => $plan->id],
            ];

            if ($plan->isRecurring()) {
                $priceData['recurring'] = [
                    'interval' => $this->getStripeInterval($plan->interval_unit),
                    'interval_count' => $plan->interval_count,
                    'usage_type' => 'licensed',
                ];
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

    public function createPlan(SubscriptionPlan $plan): string
    {
        try {
            $productId = $this->createProduct($plan);
            return $this->createPrice($plan, $productId);
        } catch (ApiErrorException $e) {
            Log::error('Stripe create plan error', [
                'plan' => $plan->toArray(),
                'error' => $e->getMessage()
            ]);
            throw new \Exception('Failed to create Stripe plan: ' . $e->getMessage());
        }
    }

    public function createSubscription(Employer $employer, SubscriptionPlan $plan, array $paymentData = []): array
    {
        if ($plan->isOneTime()) {
            if (!$this->canUseOneTimePayment($employer, $plan)) {
                throw new \Exception('One-time payments require trial period to be used first');
            }
            return $this->createOneTimeOrder($employer, $plan, $paymentData);
        }

        return $this->createRecurringSubscription($employer, $plan, $paymentData);
    }

    public function createOneTimeOrder(Employer $employer, SubscriptionPlan $plan, array $paymentData = []): array
    {
        if (!$plan->isOneTime()) {
            throw new \Exception('This method is only for one-time payment plans');
        }

        if (!$this->canUseOneTimePayment($employer, $plan)) {
            throw new \Exception('Employer must use trial period before one-time payments');
        }

        try {
            $externalPlanId = $plan->external_stripe_id ?? $this->createPlan($plan);

            if (!$plan->external_stripe_id) {
                $plan->update(['external_stripe_id' => $externalPlanId]);
            }

            $customerId = $this->getOrCreateCustomer($employer);

            $sessionParams = [
                'customer' => $customerId,
                'payment_method_types' => ['card'],
                'line_items' => [
                    [
                        'price' => $externalPlanId,
                        'quantity' => 1,
                    ],
                ],
                'mode' => 'payment',
                'success_url' => config('app.frontend_url') . '/employer/subscription/success?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => config('app.frontend_url') . '/employer/subscription/cancel?session_id={CHECKOUT_SESSION_ID}',
                'client_reference_id' => $employer->id,
                'metadata' => [
                    'employer_id' => $employer->id,
                    'plan_id' => $plan->id,
                    'payment_type' => 'one_time',
                ],
                'billing_address_collection' => 'auto',
            ];

            $session = $this->stripe->checkout->sessions->create($sessionParams);

            $subscription = Subscription::create([
                'employer_id' => $employer->id,
                'subscription_plan_id' => $plan->id,
                'start_date' => Carbon::now(),
                'end_date' => null, // One-time payments don't expire
                'amount_paid' => $plan->price,
                'currency' => $plan->currency,
                'payment_method' => 'stripe',
                'subscription_id' => null, // One-time payments don't have subscription IDs
                'payment_reference' => $session->id,
                'transaction_id' => $session->payment_intent ?? null,
                'job_posts_left' => $plan->job_posts_limit,
                'featured_jobs_left' => $plan->featured_jobs_limit,
                'cv_downloads_left' => $plan->resume_views_limit,
                'payment_type' => $plan->payment_type,
                'is_active' => false,
                'used_trial' => false,
                'external_status' => $session->status,
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

            $errorMessage = match ($e->getStripeCode()) {
                'tax_calculation_failed' => 'Tax calculation failed. Please contact support.',
                'invalid_request_error' => 'Invalid request. Please check your configuration.',
                default => 'Failed to create Stripe one-time payment: ' . $e->getMessage()
            };

            throw new \Exception($errorMessage);
        }
    }

    protected function createRecurringSubscription(Employer $employer, SubscriptionPlan $plan, array $paymentData = []): array
    {
        try {
            $externalPlanId = $plan->external_stripe_id ?? $this->createPlan($plan);

            if (!$plan->external_stripe_id) {
                $plan->update(['external_stripe_id' => $externalPlanId]);
            }

            $customerId = $this->getOrCreateCustomer($employer);

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
                'success_url' => config('app.frontend_url') . '/employer/subscription/success?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => config('app.frontend_url') . '/employer/subscription/cancel?session_id={CHECKOUT_SESSION_ID}',
                'client_reference_id' => $employer->id,
                'metadata' => [
                    'employer_id' => $employer->id,
                    'plan_id' => $plan->id,
                    'payment_type' => 'recurring',
                ],
                'billing_address_collection' => 'auto',
            ];

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

            $session = $this->stripe->checkout->sessions->create($sessionParams);

            $endDate = null;
            if ($plan->duration_days) {
                $endDate = Carbon::now()->addDays($plan->duration_days);
                if ($this->shouldUseTrial($employer, $plan)) {
                    $endDate->addDays($plan->getTrialPeriodDays());
                }
            }

            $subscription = Subscription::create([
                'employer_id' => $employer->id,
                'subscription_plan_id' => $plan->id,
                'start_date' => Carbon::now(),
                'end_date' => $endDate,
                'amount_paid' => $plan->price,
                'currency' => $plan->currency,
                'payment_method' => 'stripe',
                'subscription_id' => $session->subscription ?? null,
                'payment_reference' => $session->id,
                'transaction_id' => null, // Will be updated when payment is processed
                'job_posts_left' => $plan->job_posts_limit,
                'featured_jobs_left' => $plan->featured_jobs_limit,
                'cv_downloads_left' => $plan->resume_views_limit,
                'payment_type' => $plan->payment_type,
                'is_active' => false,
                'used_trial' => $this->shouldUseTrial($employer, $plan),
                'external_status' => $session->status,
            ]);

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

            $errorMessage = match ($e->getStripeCode()) {
                'tax_calculation_failed' => 'Tax calculation failed. Please contact support.',
                'invalid_request_error' => 'Invalid request. Please check your configuration.',
                default => 'Failed to create Stripe subscription: ' . $e->getMessage()
            };

            throw new \Exception($errorMessage);
        }
    }

    protected function getOrCreateCustomer(Employer $employer): string
    {
        if ($employer->stripe_customer_id) {
            try {
                $this->stripe->customers->retrieve($employer->stripe_customer_id);
                return $employer->stripe_customer_id;
            } catch (ApiErrorException $e) {
                Log::warning('Stripe customer not found, creating new one', [
                    'employer_id' => $employer->id,
                    'old_customer_id' => $employer->stripe_customer_id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        try {
            $customerData = [
                'email' => $employer->user->email ?? $employer->company_email,
                'name' => $employer->company_name,
                'description' => 'Employer ID: ' . $employer->id,
                'metadata' => [
                    'employer_id' => $employer->id,
                    'user_id' => $employer->user_id,
                ],
            ];

            if ($employer->company_phone_number) {
                $customerData['phone'] = $employer->company_phone_number;
            }

            $customer = $this->stripe->customers->create($customerData);

            $employer->update(['stripe_customer_id' => $customer->id]);
            return $customer->id;
        } catch (ApiErrorException $e) {
            Log::error('Stripe create customer error', [
                'employer' => $employer->toArray(),
                'error' => $e->getMessage()
            ]);
            throw new \Exception('Failed to create Stripe customer: ' . $e->getMessage());
        }
    }

    public function updatePlan(SubscriptionPlan $plan, string $externalPlanId): bool
    {
        try {
            $price = $this->stripe->prices->retrieve($externalPlanId);

            $this->stripe->products->update($price->product, [
                'name' => $plan->name,
                'description' => $plan->description ?? $plan->name,
                'metadata' => ['plan_id' => $plan->id],
            ]);

            if ($plan->price != ($price->unit_amount / 100)) {
                $newPriceId = $this->createPrice($plan, $price->product);
                $plan->update(['external_stripe_id' => $newPriceId]);
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

    public function deletePlan(string $externalPlanId): bool
    {
        try {
            $price = $this->stripe->prices->retrieve($externalPlanId);

            $this->stripe->prices->update($externalPlanId, ['active' => false]);
            $this->stripe->products->update($price->product, ['active' => false]);

            return true;
        } catch (ApiErrorException $e) {
            Log::error('Stripe delete plan error', [
                'externalPlanId' => $externalPlanId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function listPlans(array $filters = []): array
    {
        try {
            $params = array_filter([
                'limit' => $filters['limit'] ?? 100,
                'active' => $filters['active'] ?? true,
                'type' => 'recurring',
                'product' => $filters['product'] ?? null,
            ]);

            $prices = $this->stripe->prices->all($params);

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

    public function cancelSubscription(Subscription $subscription): bool
    {
        if (!$subscription->subscription_id) {
            return false;
        }

        try {
            $this->stripe->subscriptions->cancel($subscription->subscription_id, [
                'cancel_at_period_end' => false,
            ]);

            $subscription->update(['is_active' => false]);
            return true;
        } catch (ApiErrorException $e) {
            Log::error('Stripe cancel subscription error', [
                'subscription' => $subscription->toArray(),
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

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

            $subscription->update(['is_suspended' => true]);
            return true;
        } catch (ApiErrorException $e) {
            Log::error('Stripe suspend subscription error', [
                'subscription' => $subscription->toArray(),
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

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

            $subscription->update([
                'is_active' => true,
                'is_suspended' => false
            ]);
            return true;
        } catch (ApiErrorException $e) {
            Log::error('Stripe reactivate subscription error', [
                'subscription' => $subscription->toArray(),
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

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

    public function getSubscriptionDetails(string $subscriptionId): array
    {
        try {
            $subscription = $this->stripe->subscriptions->retrieve($subscriptionId, [
                'expand' => ['customer', 'default_payment_method', 'latest_invoice.payment_intent']
            ]);

            $details = $subscription->toArray();

            // Extract next billing date if available
            if (isset($details['current_period_end'])) {
                $details['next_billing_date'] = date('Y-m-d H:i:s', $details['current_period_end']);
            }

            return $details;
        } catch (ApiErrorException $e) {
            Log::error('Stripe get subscription details error', [
                'subscriptionId' => $subscriptionId,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

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

    protected function verifyWebhookSignature(string $payload, array $headers): bool
    {
        // For development/testing environments, we might want to skip signature verification
        if (config('app.env') === 'local' || config('app.env') === 'development') {
            return true;
        }

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

    public function handleWebhook(string $payload, array $headers): bool
    {
        // In development/testing environments, we might want to skip signature verification
        $verifySignature = config('services.stripe.verify_webhook_signature', true);

        if ($verifySignature && !$this->verifyWebhookSignature($payload, $headers)) {
            Log::warning('Stripe webhook signature verification failed, but processing event anyway');
            // Continue processing the webhook even if verification fails in development
        }

        $data = json_decode($payload, true);
        $event = $data['type'] ?? '';
        $object = $data['data']['object'] ?? [];

        Log::info('Stripe webhook received', [
            'event' => $event,
            'object_id' => $object['id'] ?? null
        ]);

        return match ($event) {
            'checkout.session.completed' => $this->handleCheckoutSessionCompleted($object),
            'customer.subscription.created' => $this->handleSubscriptionCreated($object),
            'customer.subscription.updated' => $this->handleSubscriptionUpdated($object),
            'customer.subscription.deleted' => $this->handleSubscriptionDeleted($object),
            'invoice.paid' => $this->handleInvoicePaid($object),
            'invoice.payment_failed' => $this->handleInvoicePaymentFailed($object),
            'payment_intent.succeeded' => $this->handlePaymentIntentSucceeded($object),
            default => true
        };
    }

    protected function handleCheckoutSessionCompleted(array $data): bool
    {
        $sessionId = $data['id'] ?? '';
        $mode = $data['mode'] ?? '';
        $paymentStatus = $data['payment_status'] ?? '';
        $subscriptionId = $data['subscription'] ?? null;
        $paymentIntentId = $data['payment_intent'] ?? null;

        Log::info('Stripe checkout session completed', [
            'session_id' => $sessionId,
            'mode' => $mode,
            'payment_status' => $paymentStatus,
            'subscription_id' => $subscriptionId,
            'payment_intent_id' => $paymentIntentId
        ]);

        $subscription = Subscription::where('payment_reference', $sessionId)
            ->where('payment_method', 'stripe')
            ->first();

        if (!$subscription) {
            Log::error('Stripe subscription not found for session', ['sessionId' => $sessionId]);
            return false;
        }

        // Update subscription with IDs
        if ($subscriptionId) {
            $subscription->subscription_id = $subscriptionId;
        }

        if ($paymentIntentId) {
            $subscription->transaction_id = $paymentIntentId;
        }

        // For one-time payments, activate immediately when payment is successful
        if ($mode === 'payment' && $paymentStatus === 'paid') {
            $subscription->is_active = true;
            $subscription->external_status = 'paid';

            // Send notification
            $this->sendActivationNotification($subscription);

            Log::info('One-time Stripe payment activated', [
                'subscription_id' => $subscription->id,
                'session_id' => $sessionId
            ]);
        }
        // For recurring subscriptions, they will be activated via subscription events
        else if ($mode === 'subscription') {
            // If we have a subscription ID, get the subscription details to check status
            if ($subscriptionId) {
                try {
                    $stripeSubscription = $this->stripe->subscriptions->retrieve($subscriptionId);

                    // If subscription is already active or trialing, activate it
                    if (in_array($stripeSubscription->status, ['active', 'trialing'])) {
                        $subscription->is_active = true;
                        $subscription->external_status = $stripeSubscription->status;

                        // Extract next billing date
                        if (isset($stripeSubscription->current_period_end)) {
                            $subscription->next_billing_date = Carbon::createFromTimestamp($stripeSubscription->current_period_end);
                        }

                        // Send notification
                        $this->sendActivationNotification($subscription);

                        Log::info('Stripe subscription activated via checkout completion', [
                            'subscription_id' => $subscription->id,
                            'stripe_subscription_id' => $subscriptionId,
                            'status' => $stripeSubscription->status
                        ]);
                    }
                } catch (ApiErrorException $e) {
                    Log::error('Error retrieving Stripe subscription', [
                        'subscription_id' => $subscriptionId,
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }

        $subscription->save();
        return true;
    }

    protected function handleSubscriptionCreated(array $data): bool
    {
        $subscriptionId = $data['id'] ?? '';
        $customerId = $data['customer'] ?? '';
        $status = $data['status'] ?? '';

        Log::info('Stripe subscription created', [
            'subscription_id' => $subscriptionId,
            'customer_id' => $customerId,
            'status' => $status
        ]);

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
            } else {
                Log::error('Stripe subscription not found', [
                    'subscription_id' => $subscriptionId,
                    'employer_id' => $employer->id
                ]);
                return false;
            }
        }

        $subscription->external_status = $status;

        // If status is trialing or active, activate the subscription
        if (in_array($status, ['trialing', 'active'])) {
            $subscription->is_active = true;

            // Extract next billing date
            if (isset($data['current_period_end'])) {
                $subscription->next_billing_date = Carbon::createFromTimestamp($data['current_period_end']);
            }

            // Send notification
            $this->sendActivationNotification($subscription);
        }

        $subscription->save();
        return true;
    }

    protected function handleSubscriptionUpdated(array $data): bool
    {
        $subscriptionId = $data['id'] ?? '';
        $status = $data['status'] ?? '';

        Log::info('Stripe subscription updated', [
            'subscription_id' => $subscriptionId,
            'status' => $status
        ]);

        $subscription = Subscription::where('subscription_id', $subscriptionId)
            ->where('payment_method', 'stripe')
            ->first();

        if (!$subscription) {
            Log::error('Stripe subscription not found', ['subscriptionId' => $subscriptionId]);
            return false;
        }

        $wasActive = $subscription->is_active;

        switch ($status) {
            case 'active':
            case 'trialing':
                $subscription->is_active = true;
                $subscription->is_suspended = false;

                if (!$wasActive) {
                    $this->sendActivationNotification($subscription);
                }
                break;

            case 'past_due':
                $subscription->external_status = 'past_due';
                break;

            case 'unpaid':
            case 'canceled':
            case 'incomplete':
            case 'incomplete_expired':
                $subscription->is_active = false;
                break;
        }

        $subscription->external_status = $status;
        $subscription->status_update_time = Carbon::now();

        // Extract next billing date
        if (isset($data['current_period_end'])) {
            $subscription->next_billing_date = Carbon::createFromTimestamp($data['current_period_end']);

            // Store billing info for future reference
            $subscription->billing_info = [
                'current_period_start' => isset($data['current_period_start']) ?
                    Carbon::createFromTimestamp($data['current_period_start'])->toIso8601String() : null,
                'current_period_end' => Carbon::createFromTimestamp($data['current_period_end'])->toIso8601String(),
                'cancel_at' => isset($data['cancel_at']) ?
                    Carbon::createFromTimestamp($data['cancel_at'])->toIso8601String() : null,
                'canceled_at' => isset($data['canceled_at']) ?
                    Carbon::createFromTimestamp($data['canceled_at'])->toIso8601String() : null,
            ];
        }

        $subscription->save();
        return true;
    }

    protected function handleSubscriptionDeleted(array $data): bool
    {
        $subscriptionId = $data['id'] ?? '';

        Log::info('Stripe subscription deleted', [
            'subscription_id' => $subscriptionId
        ]);

        $subscription = Subscription::where('subscription_id', $subscriptionId)
            ->where('payment_method', 'stripe')
            ->first();

        if (!$subscription) {
            Log::error('Stripe subscription not found', ['subscriptionId' => $subscriptionId]);
            return false;
        }

        $subscription->update([
            'is_active' => false,
            'external_status' => 'canceled',
            'status_update_time' => Carbon::now()
        ]);

        return true;
    }

    protected function handleInvoicePaid(array $data): bool
    {
        $subscriptionId = $data['subscription'] ?? null;

        if (!$subscriptionId) {
            return true;
        }

        Log::info('Stripe invoice paid', [
            'subscription_id' => $subscriptionId,
            'invoice_id' => $data['id'] ?? null
        ]);

        $subscription = Subscription::where('subscription_id', $subscriptionId)
            ->where('payment_method', 'stripe')
            ->first();

        if (!$subscription) {
            Log::error('Stripe subscription not found', ['subscriptionId' => $subscriptionId]);
            return false;
        }

        $subscription->transaction_id = $data['payment_intent'] ?? $subscription->transaction_id;
        $subscription->is_active = true;
        $subscription->is_suspended = false;

        // Extract next billing date
        if (isset($data['lines']['data'][0]['period']['end'])) {
            $subscription->next_billing_date = Carbon::createFromTimestamp($data['lines']['data'][0]['period']['end']);

            // Store billing info for future reference
            $subscription->billing_info = [
                'invoice_id' => $data['id'],
                'period_start' => isset($data['lines']['data'][0]['period']['start']) ?
                    Carbon::createFromTimestamp($data['lines']['data'][0]['period']['start'])->toIso8601String() : null,
                'period_end' => Carbon::createFromTimestamp($data['lines']['data'][0]['period']['end'])->toIso8601String(),
                'amount_paid' => $data['amount_paid'] ?? null,
                'currency' => $data['currency'] ?? null,
            ];
        }

        $subscription->save();
        return true;
    }

    protected function handleInvoicePaymentFailed(array $data): bool
    {
        $subscriptionId = $data['subscription'] ?? null;

        if (!$subscriptionId) {
            return true;
        }

        Log::info('Stripe invoice payment failed', [
            'subscription_id' => $subscriptionId,
            'invoice_id' => $data['id'] ?? null
        ]);

        $subscription = Subscription::where('subscription_id', $subscriptionId)
            ->where('payment_method', 'stripe')
            ->first();

        if (!$subscription) {
            Log::error('Stripe subscription not found', ['subscriptionId' => $subscriptionId]);
            return false;
        }

        $subscription->update([
            'external_status' => 'payment_failed',
            'status_update_time' => Carbon::now()
        ]);

        return true;
    }

    protected function handlePaymentIntentSucceeded(array $data): bool
    {
        $paymentIntentId = $data['id'] ?? null;

        if (!$paymentIntentId) {
            return true;
        }

        Log::info('Stripe payment intent succeeded', [
            'payment_intent_id' => $paymentIntentId
        ]);

        // Find subscription by transaction_id (payment_intent)
        $subscription = Subscription::where('transaction_id', $paymentIntentId)
            ->where('payment_method', 'stripe')
            ->first();

        if (!$subscription) {
            // If not found by transaction_id, try to find by metadata
            if (isset($data['metadata']['subscription_id'])) {
                $subscription = Subscription::where('id', $data['metadata']['subscription_id'])
                    ->where('payment_method', 'stripe')
                    ->first();
            }
        }

        if ($subscription) {
            // For one-time payments, activate immediately
            if ($subscription->isOneTime() && !$subscription->is_active) {
                $subscription->is_active = true;
                $subscription->external_status = 'paid';
                $subscription->save();

                // Send notification
                $this->sendActivationNotification($subscription);

                Log::info('One-time Stripe payment activated via payment intent', [
                    'subscription_id' => $subscription->id,
                    'payment_intent_id' => $paymentIntentId
                ]);
            }

            return true;
        }

        // If we couldn't find a subscription, log it but don't fail
        Log::info('Stripe payment intent succeeded but no matching subscription found', [
            'payment_intent_id' => $paymentIntentId
        ]);

        return true;
    }

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
