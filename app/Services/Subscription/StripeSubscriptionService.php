<?php

namespace App\Services\Subscription;

use App\Models\Employer;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Notifications\SubscriptionActivatedNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Stripe\StripeClient;
use Stripe\Exception\ApiErrorException;

class StripeSubscriptionService
{
    protected StripeClient $stripe;
    protected string $webhookSecret;

    public function __construct()
    {
        $this->stripe = new \Stripe\StripeClient(config('services.stripe.secret'));
        $this->webhookSecret = config('services.stripe.webhook_secret');
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
            } else {
                // For one-time payments, create a recurring price that cancels after first payment
                $priceData['recurring'] = [
                    'interval' => 'month',
                    'interval_count' => 1,
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

    public function createSubscription(Employer $employer, SubscriptionPlan $plan): array
    {
        try {
            // Create or get existing price
            $externalPlanId = $plan->external_stripe_id ?? $this->createPlan($plan);

            if (!$plan->external_stripe_id) {
                $plan->update(['external_stripe_id' => $externalPlanId]);
            }

            $customerId = $this->getOrCreateCustomer($employer);

            // Create payment method if needed
            $paymentMethodId = $this->getOrCreatePaymentMethod($customerId);

            // Set default payment method for customer if not already set
            if ($paymentMethodId) {
                $this->setDefaultPaymentMethod($customerId, $paymentMethodId);
            }

            // Create subscription directly
            $subscriptionData = [
                'customer' => $customerId,
                'items' => [
                    [
                        'price' => $externalPlanId,
                        'quantity' => 1,
                    ],
                ],
                'metadata' => [
                    'employer_id' => $employer->id,
                    'plan_id' => $plan->id,
                    'payment_type' => $plan->payment_type,
                ],
            ];

            // Add trial period for recurring subscriptions if applicable
            if ($plan->isRecurring() && $plan->hasTrial() && !$employer->has_used_trial) {
                $subscriptionData['trial_period_days'] = $plan->getTrialPeriodDays();
            }

            // Create the subscription
            $stripeSubscription = $this->stripe->subscriptions->create($subscriptionData);

            // Create subscription record
            $endDate = null;
            if ($plan->duration_days && $plan->isRecurring()) {
                $endDate = Carbon::now()->addDays($plan->duration_days);
                if ($plan->hasTrial() && !$employer->has_used_trial) {
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
                'subscription_id' => $stripeSubscription->id,
                'payment_reference' => $stripeSubscription->id,
                'transaction_id' => $stripeSubscription->latest_invoice ?? null,
                'job_posts_left' => $plan->job_posts_limit,
                'featured_jobs_left' => $plan->featured_jobs_limit,
                'cv_downloads_left' => $plan->resume_views_limit,
                'payment_type' => $plan->payment_type,
                'is_active' => in_array($stripeSubscription->status, ['active', 'trialing']),
                'used_trial' => $plan->hasTrial() && !$employer->has_used_trial,
                'external_status' => $stripeSubscription->status,
                'next_billing_date' => isset($stripeSubscription->current_period_end) ?
                    Carbon::createFromTimestamp($stripeSubscription->current_period_end) : null,
            ]);

            // Mark trial as used if applicable
            if ($plan->hasTrial() && !$employer->has_used_trial) {
                $employer->markTrialAsUsed();
            }

            // For one-time payments, set to cancel after first payment
            if ($plan->isOneTime() && $stripeSubscription->status === 'active') {
                $this->stripe->subscriptions->update($stripeSubscription->id, [
                    'cancel_at_period_end' => true
                ]);
            }

            return [
                'subscription_id' => $subscription->id,
                'external_subscription_id' => $stripeSubscription->id,
                'status' => $stripeSubscription->status,
                'payment_type' => $plan->payment_type,
                'is_active' => in_array($stripeSubscription->status, ['active', 'trialing']),
            ];
        } catch (ApiErrorException $e) {
            Log::error('Stripe create subscription error', [
                'employer' => $employer->id,
                'plan' => $plan->toArray(),
                'error' => $e->getMessage(),
                'error_code' => $e->getStripeCode(),
            ]);

            throw new \Exception('Failed to create Stripe subscription: ' . $e->getMessage());
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

    protected function getOrCreatePaymentMethod(string $customerId): ?string
    {
        try {
            // Check if customer already has payment methods
            $paymentMethods = $this->stripe->paymentMethods->all([
                'customer' => $customerId,
                'type' => 'card',
                'limit' => 1,
            ]);

            if (!empty($paymentMethods->data)) {
                return $paymentMethods->data[0]->id;
            }

            // If no payment methods, we'll need to redirect the user to add one
            // For now, return null and handle this in the controller
            return null;
        } catch (ApiErrorException $e) {
            Log::error('Error retrieving payment methods', [
                'customer_id' => $customerId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    protected function setDefaultPaymentMethod(string $customerId, string $paymentMethodId): bool
    {
        try {
            $this->stripe->customers->update($customerId, [
                'invoice_settings' => [
                    'default_payment_method' => $paymentMethodId
                ]
            ]);
            return true;
        } catch (ApiErrorException $e) {
            Log::error('Error setting default payment method', [
                'customer_id' => $customerId,
                'payment_method_id' => $paymentMethodId,
                'error' => $e->getMessage()
            ]);
            return false;
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

    public function cancelSubscription(string $subscriptionId): bool
    {
        try {
            $this->stripe->subscriptions->cancel($subscriptionId, []);
            return true;
        } catch (ApiErrorException $e) {
            Log::error('Stripe cancel subscription error', [
                'subscriptionId' => $subscriptionId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function suspendSubscription(string $subscriptionId): bool
    {
        try {
            $this->stripe->subscriptions->update($subscriptionId, [
                'pause_collection' => [
                    'behavior' => 'mark_uncollectible',
                ],
            ]);

            return true;
        } catch (ApiErrorException $e) {
            Log::error('Stripe suspend subscription error', [
                'subscriptionId' => $subscriptionId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function resumeSubscription(string $subscriptionId): bool
    {
        try {
            $this->stripe->subscriptions->resume($subscriptionId, [
                'billing_cycle_anchor' => 'now'
            ]);

            return true;
        } catch (ApiErrorException $e) {
            Log::error('Stripe resume subscription error', [
                'subscriptionId' => $subscriptionId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function listSubscriptions(string $customerId, int $limit = 10): array
    {
        try {
            $subscriptions = $this->stripe->subscriptions->all([
                'customer' => $customerId,
                'limit' => $limit,
            ]);

            return $subscriptions->toArray();
        } catch (ApiErrorException $e) {
            Log::error('Stripe list subscriptions error', [
                'customerId' => $customerId,
                'error' => $e->getMessage()
            ]);
            return ['data' => []];
        }
    }

    public function searchSubscriptions(string $query): array
    {
        try {
            $subscriptions = $this->stripe->subscriptions->search([
                'query' => $query,
            ]);

            return $subscriptions->toArray();
        } catch (ApiErrorException $e) {
            Log::error('Stripe search subscriptions error', [
                'query' => $query,
                'error' => $e->getMessage()
            ]);
            return ['data' => []];
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
            'customer.subscription.created' => $this->handleSubscriptionCreated($object),
            'customer.subscription.updated' => $this->handleSubscriptionUpdated($object),
            'customer.subscription.deleted' => $this->handleSubscriptionDeleted($object),
            'invoice.paid' => $this->handleInvoicePaid($object),
            'invoice.payment_failed' => $this->handleInvoicePaymentFailed($object),
            'payment_intent.succeeded' => $this->handlePaymentIntentSucceeded($object),
            default => true
        };
    }

    protected function verifyWebhookSignature(string $payload, array $headers): bool
    {
        if (config('app.env') === 'local') {
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
