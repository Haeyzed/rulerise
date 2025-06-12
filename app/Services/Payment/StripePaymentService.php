<?php

namespace App\Services\Payment;

use App\Models\Employer;
use App\Models\Plan;
use App\Models\Payment;
use App\Models\Subscription;
use Stripe\StripeClient;
use Stripe\Exception\ApiErrorException;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class StripePaymentService
{
    private StripeClient $stripe;

    public function __construct()
    {
        $this->stripe = new StripeClient(config('services.stripe.secret'));
    }

    /**
     * Create or get Stripe customer
     */
    public function createOrGetCustomer(Employer $employer): string
    {
        if ($employer->stripe_customer_id) {
            try {
                $this->stripe->customers->retrieve($employer->stripe_customer_id);
                return $employer->stripe_customer_id;
            } catch (ApiErrorException $e) {
                Log::warning('Stripe customer not found, creating new one', [
                    'employer_id' => $employer->id,
                    'old_customer_id' => $employer->stripe_customer_id
                ]);
            }
        }

        try {
            $customer = $this->stripe->customers->create([
                'email' => $employer->user->email,
                'name' => $employer->company_name,
                'metadata' => [
                    'employer_id' => $employer->id,
                    'company_name' => $employer->company_name,
                ],
            ]);

            $employer->update(['stripe_customer_id' => $customer->id]);

            return $customer->id;
        } catch (ApiErrorException $e) {
            Log::error('Failed to create Stripe customer', [
                'employer_id' => $employer->id,
                'error' => $e->getMessage()
            ]);
            throw new \Exception('Failed to create customer: ' . $e->getMessage());
        }
    }

    /**
     * Create or get Stripe price
     */
    public function createPrice(Plan $plan): array
    {
        try {
            // Create product if needed
            $productName = $plan->name;
            $productDescription = $plan->description ?? $plan->name;

            $product = $this->stripe->products->create([
                'name' => $productName,
                'description' => $productDescription,
                'metadata' => [
                    'plan_id' => $plan->id,
                ],
            ]);

            // Create price
            $priceData = [
                'product' => $product->id,
                'unit_amount' => (int)($plan->price * 100), // Convert to cents
                'currency' => strtolower($plan->getCurrencyCode()),
                'metadata' => [
                    'plan_id' => $plan->id,
                ],
            ];

            // Add recurring data if it's a subscription
            if ($plan->isRecurring()) {
                $priceData['recurring'] = [
                    'interval' => $plan->billing_cycle === 'yearly' ? 'year' : 'month',
                    'interval_count' => 1,
                ];
            }

            $price = $this->stripe->prices->create($priceData);

            // Update plan with Stripe price ID
            $plan->update(['stripe_price_id' => $price->id]);

            return [
                'success' => true,
                'price' => $price,
            ];
        } catch (ApiErrorException $e) {
            Log::error('Failed to create Stripe price', [
                'plan_id' => $plan->id,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Create one-time payment
     */
    public function createOneTimePayment(Employer $employer, Plan $plan): array
    {
        try {
            $customerId = $this->createOrGetCustomer($employer);

            $paymentIntent = $this->stripe->paymentIntents->create([
                'amount' => (int)($plan->price * 100), // Convert to cents
                'currency' => strtolower($plan->getCurrencyCode()),
                'customer' => $customerId,
                'metadata' => [
                    'employer_id' => $employer->id,
                    'plan_id' => $plan->id,
                    'payment_type' => 'one_time',
                ],
                'description' => "One-time payment for {$plan->name} plan",
            ]);

            // Create payment record
            $payment = Payment::create([
                'employer_id' => $employer->id,
                'plan_id' => $plan->id,
                'payment_id' => $paymentIntent->id,
                'payment_provider' => 'stripe',
                'payment_type' => 'one_time',
                'status' => 'pending',
                'amount' => $plan->price,
                'currency' => $plan->getCurrencyCode(),
                'provider_response' => $paymentIntent->toArray(),
            ]);

            return [
                'success' => true,
                'client_secret' => $paymentIntent->client_secret,
                'payment_intent_id' => $paymentIntent->id,
                'payment' => $payment,
            ];
        } catch (ApiErrorException $e) {
            Log::error('Stripe one-time payment creation failed', [
                'employer_id' => $employer->id,
                'plan_id' => $plan->id,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Create recurring subscription with trial support
     */
    public function createSubscription(Employer $employer, Plan $plan): array
    {
        try {
            $customerId = $this->createOrGetCustomer($employer);

            if (!$plan->stripe_price_id) {
                $priceResult = $this->createPrice($plan);
                if (!$priceResult['success']) {
                    throw new \Exception('Failed to create Stripe price: ' . $priceResult['error']);
                }
            }

            $subscriptionData = [
                'customer' => $customerId,
                'items' => [
                    ['price' => $plan->stripe_price_id],
                ],
                'payment_behavior' => 'default_incomplete',
                'payment_settings' => ['save_default_payment_method' => 'on_subscription'],
                'expand' => ['latest_invoice.payment_intent'],
                'metadata' => [
                    'employer_id' => $employer->id,
                    'plan_id' => $plan->id,
                ],
            ];

            // Add trial period if plan has trial
            if ($plan->hasTrial()) {
                $subscriptionData['trial_period_days'] = $plan->getTrialPeriodDays();
            }

            $subscription = $this->stripe->subscriptions->create($subscriptionData);

            // Determine if subscription is in trial
            $isInTrial = $subscription->status === 'trialing';
            $trialStart = $isInTrial ? Carbon::createFromTimestamp($subscription->trial_start) : null;
            $trialEnd = $isInTrial ? Carbon::createFromTimestamp($subscription->trial_end) : null;

            // Create subscription record
            $subscriptionRecord = Subscription::create([
                'employer_id' => $employer->id,
                'plan_id' => $plan->id,
                'subscription_id' => $subscription->id,
                'payment_provider' => 'stripe',
                'status' => $subscription->status,
                'amount' => $plan->price,
                'currency' => $plan->getCurrencyCode(),
                'start_date' => Carbon::createFromTimestamp($subscription->current_period_start),
                'end_date' => Carbon::createFromTimestamp($subscription->current_period_end),
                'next_billing_date' => Carbon::createFromTimestamp($subscription->current_period_end),
                'trial_start_date' => $trialStart,
                'trial_end_date' => $trialEnd,
                'is_trial' => $isInTrial,
                'trial_ended' => false,
                'cv_downloads_left' => $plan->resume_views_limit,
                'metadata' => $subscription->toArray(),
                'is_active' => in_array($subscription->status, ['active', 'trialing']),
            ]);

            // Create initial payment record if not in trial
            if (!$isInTrial && $subscription->latest_invoice->payment_intent) {
                Payment::create([
                    'employer_id' => $employer->id,
                    'plan_id' => $plan->id,
                    'subscription_id' => $subscriptionRecord->id,
                    'payment_id' => $subscription->latest_invoice->payment_intent->id,
                    'payment_provider' => 'stripe',
                    'payment_type' => 'recurring',
                    'status' => $subscription->latest_invoice->payment_intent->status === 'succeeded' ? 'completed' : 'pending',
                    'amount' => $plan->price,
                    'currency' => $plan->getCurrencyCode(),
                    'provider_response' => $subscription->latest_invoice->payment_intent->toArray(),
                ]);
            }

            return [
                'success' => true,
                'subscription_id' => $subscription->id,
                'client_secret' => $subscription->latest_invoice->payment_intent->client_secret ?? null,
                'subscription' => $subscriptionRecord,
                'is_trial' => $isInTrial,
                'trial_end_date' => $trialEnd,
            ];
        } catch (ApiErrorException $e) {
            Log::error('Stripe subscription creation failed', [
                'employer_id' => $employer->id,
                'plan_id' => $plan->id,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Cancel subscription
     */
    public function cancelSubscription(Subscription $subscription): bool
    {
        try {
            $this->stripe->subscriptions->cancel($subscription->subscription_id);

            $subscription->cancel();

            return true;
        } catch (ApiErrorException $e) {
            Log::error('Failed to cancel Stripe subscription', [
                'subscription_id' => $subscription->subscription_id,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Suspend subscription
     */
    public function suspendSubscription(Subscription $subscription): bool
    {
        try {
            // Stripe doesn't have a direct "suspend" action, so we pause the billing
            $this->stripe->subscriptions->update($subscription->subscription_id, [
                'pause_collection' => [
                    'behavior' => 'void',
                ],
                'metadata' => array_merge($subscription->metadata ?? [], [
                    'suspended_at' => now()->toIso8601String(),
                    'suspended_reason' => 'User requested suspension',
                ]),
            ]);

            $subscription->suspend();
            return true;
        } catch (ApiErrorException $e) {
            Log::error('Failed to suspend Stripe subscription', [
                'subscription_id' => $subscription->subscription_id,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Resume subscription
     */
    public function resumeSubscription(Subscription $subscription): bool
    {
        try {
            // Resume the subscription by removing the pause_collection
            $this->stripe->subscriptions->update($subscription->subscription_id, [
                'pause_collection' => '',
                'metadata' => array_merge($subscription->metadata ?? [], [
                    'resumed_at' => now()->toIso8601String(),
                ]),
            ]);

            $subscription->resume();
            return true;
        } catch (ApiErrorException $e) {
            Log::error('Failed to resume Stripe subscription', [
                'subscription_id' => $subscription->subscription_id,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Handle webhook events
     */
    public function handleWebhook(array $event): void
    {
        try {
            switch ($event['type']) {
                case 'payment_intent.succeeded':
                    $this->handlePaymentIntentSucceeded($event['data']['object']);
                    break;

                case 'payment_intent.payment_failed':
                    $this->handlePaymentIntentFailed($event['data']['object']);
                    break;

                case 'invoice.payment_succeeded':
                    $this->handleInvoicePaymentSucceeded($event['data']['object']);
                    break;

                case 'customer.subscription.updated':
                    $this->handleSubscriptionUpdated($event['data']['object']);
                    break;

                case 'customer.subscription.deleted':
                    $this->handleSubscriptionDeleted($event['data']['object']);
                    break;

                case 'customer.subscription.trial_will_end':
                    $this->handleTrialWillEnd($event['data']['object']);
                    break;
            }
        } catch (\Exception $e) {
            Log::error('Stripe webhook handling failed', [
                'event_type' => $event['type'],
                'error' => $e->getMessage()
            ]);
        }
    }

    private function handlePaymentIntentSucceeded(array $paymentIntent): void
    {
        $payment = Payment::where('payment_id', $paymentIntent['id'])->first();

        if ($payment) {
            $payment->update([
                'status' => 'completed',
                'paid_at' => now(),
                'provider_response' => $paymentIntent,
            ]);
        }
    }

    private function handlePaymentIntentFailed(array $paymentIntent): void
    {
        $payment = Payment::where('payment_id', $paymentIntent['id'])->first();

        if ($payment) {
            $payment->update([
                'status' => 'failed',
                'provider_response' => $paymentIntent,
            ]);
        }
    }

    private function handleInvoicePaymentSucceeded(array $invoice): void
    {
        if ($invoice['subscription']) {
            $subscription = Subscription::where('subscription_id', $invoice['subscription'])->first();

            if ($subscription) {
                // Create payment record for recurring payment
                Payment::create([
                    'employer_id' => $subscription->employer_id,
                    'plan_id' => $subscription->plan_id,
                    'subscription_id' => $subscription->id,
                    'payment_id' => $invoice['payment_intent'],
                    'payment_provider' => 'stripe',
                    'payment_type' => 'recurring',
                    'status' => 'completed',
                    'amount' => $invoice['amount_paid'] / 100,
                    'currency' => strtoupper($invoice['currency']),
                    'paid_at' => Carbon::createFromTimestamp($invoice['status_transitions']['paid_at']),
                    'provider_response' => $invoice,
                ]);

                // If this is the first payment after trial, end the trial
                if ($subscription->isInTrial()) {
                    $subscription->endTrial();
                }
            }
        }
    }

    private function handleSubscriptionUpdated(array $subscription): void
    {
        $subscriptionRecord = Subscription::where('subscription_id', $subscription['id'])->first();

        if ($subscriptionRecord) {
            $updateData = [
                'status' => $subscription['status'],
                'end_date' => Carbon::createFromTimestamp($subscription['current_period_end']),
                'next_billing_date' => Carbon::createFromTimestamp($subscription['current_period_end']),
                'is_active' => in_array($subscription['status'], ['active', 'trialing']),
                'metadata' => $subscription,
            ];

            // Handle trial status changes
            if (isset($subscription['trial_end'])) {
                $isInTrial = $subscription['status'] === 'trialing';
                $trialEnd = Carbon::createFromTimestamp($subscription['trial_end']);

                $updateData['is_trial'] = $isInTrial;
                $updateData['trial_end_date'] = $trialEnd;

                // If trial has ended
                if ($isInTrial && $trialEnd->isPast()) {
                    $updateData['trial_ended'] = true;
                    $updateData['is_trial'] = false;
                }
            }

            $subscriptionRecord->update($updateData);
        }
    }

    private function handleSubscriptionDeleted(array $subscription): void
    {
        $subscriptionRecord = Subscription::where('subscription_id', $subscription['id'])->first();

        if ($subscriptionRecord) {
            $subscriptionRecord->cancel();
        }
    }

    private function handleTrialWillEnd(array $subscription): void
    {
        $subscriptionRecord = Subscription::where('subscription_id', $subscription['id'])->first();

        if ($subscriptionRecord && $subscriptionRecord->isInTrial()) {
            // You could send a notification to the user here
            Log::info('Trial will end soon', [
                'subscription_id' => $subscription['id'],
                'trial_end' => Carbon::createFromTimestamp($subscription['trial_end'])->format('Y-m-d H:i:s')
            ]);
        }
    }
}
