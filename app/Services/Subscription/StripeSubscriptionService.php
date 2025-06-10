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
            }

            $session = $this->stripe->checkout->sessions->create($sessionParams);

            $subscription = $this->createSubscriptionRecord($employer, $plan, [
                'id' => $session->id,
                'customer' => $customerId,
                'subscription' => $session->subscription ?? null,
                'type' => 'checkout_session'
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
            'is_active' => false,
            'used_trial' => $this->shouldUseTrial($employer, $plan),
        ]);
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

            return $subscription->toArray();
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
        if (!$this->verifyWebhookSignature($payload, $headers)) {
            Log::warning('Stripe webhook signature verification failed');
            return false;
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

        $subscription = Subscription::where('payment_reference', $sessionId)
            ->where('payment_method', 'stripe')
            ->first();

        if (!$subscription) {
            Log::error('Stripe subscription not found for session', ['sessionId' => $sessionId]);
            return false;
        }

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
            $this->sendActivationNotification($subscription);

            Log::info('One-time Stripe payment activated', [
                'subscription_id' => $subscription->id,
                'session_id' => $sessionId
            ]);
        }

        $subscription->save();
        return true;
    }

    protected function handleSubscriptionCreated(array $data): bool
    {
        $subscriptionId = $data['id'] ?? '';
        $customerId = $data['customer'] ?? '';
        $status = $data['status'] ?? '';

        $employer = Employer::where('stripe_customer_id', $customerId)->first();

        if (!$employer) {
            Log::error('Employer not found for Stripe customer', ['customerId' => $customerId]);
            return false;
        }

        $subscription = Subscription::where('subscription_id', $subscriptionId)
            ->where('payment_method', 'stripe')
            ->first();

        if (!$subscription) {
            $subscription = Subscription::where('employer_id', $employer->id)
                ->where('payment_method', 'stripe')
                ->whereNull('subscription_id')
                ->latest()
                ->first();

            if ($subscription) {
                $subscription->subscription_id = $subscriptionId;
            }
        }

        if ($subscription) {
            $subscription->external_status = $status;

            if (in_array($status, ['trialing', 'active'])) {
                $subscription->is_active = true;
                $this->sendActivationNotification($subscription);
            }

            $subscription->save();
        }

        return true;
    }

    protected function handleSubscriptionUpdated(array $data): bool
    {
        $subscriptionId = $data['id'] ?? '';
        $status = $data['status'] ?? '';

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

        if (isset($data['current_period_end'])) {
            $subscription->next_billing_date = Carbon::createFromTimestamp($data['current_period_end']);
        }

        $subscription->save();
        return true;
    }

    protected function handleSubscriptionDeleted(array $data): bool
    {
        $subscriptionId = $data['id'] ?? '';

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

        if (isset($data['lines']['data'][0]['period']['end'])) {
            $subscription->next_billing_date = Carbon::createFromTimestamp($data['lines']['data'][0]['period']['end']);
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
