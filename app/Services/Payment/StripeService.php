<?php

namespace App\Services\Payment;

use App\Models\Subscription;
use Exception;
use Illuminate\Support\Facades\Log;
use Stripe\StripeClient;
use Stripe\Exception\ApiErrorException;

class StripeService
{
    protected StripeClient $stripe;

    public function __construct()
    {
        $this->stripe = new StripeClient(config('services.stripe.secret'));
    }

    public function processPayment(Subscription $subscription, array $paymentData): array
    {
        try {
            $employer = $subscription->employer;

            $customerId = $this->createOrGetCustomer($employer);

            if ($subscription->isRecurring()) {
                return $this->createRecurringSubscription($subscription, $customerId, $paymentData);
            } else {
                return $this->createOneTimePayment($subscription, $customerId, $paymentData);
            }

        } catch (ApiErrorException $e) {
            Log::error('Stripe payment failed', [
                'subscription_id' => $subscription->id,
                'error' => $e->getMessage()
            ]);

            throw new Exception('Stripe payment failed: ' . $e->getMessage());
        }
    }

    protected function createRecurringSubscription(Subscription $subscription, string $customerId, array $paymentData): array
    {
        $plan = $subscription->plan;

        $priceId = $this->createOrGetPrice($plan);

        $subscriptionData = [
            'customer' => $customerId,
            'items' => [
                ['price' => $priceId],
            ],
            'payment_behavior' => 'default_incomplete',
            'payment_settings' => [
                'save_default_payment_method' => 'on_subscription',
            ],
            'expand' => ['latest_invoice.payment_intent'],
        ];

        if ($subscription->used_trial && $plan->hasTrial()) {
            $subscriptionData['trial_period_days'] = $plan->getTrialPeriodDays();
        }

        $stripeSubscription = $this->stripe->subscriptions->create($subscriptionData);

        $subscription->update([
            'subscription_id' => $stripeSubscription->id,
            'external_status' => $stripeSubscription->status,
            'payment_reference' => $stripeSubscription->id,
        ]);

        return [
            'status' => 'created',
            'subscription_id' => $stripeSubscription->id,
            'client_secret' => $stripeSubscription->latest_invoice->payment_intent->client_secret ?? null,
        ];
    }

    protected function createOneTimePayment(Subscription $subscription, string $customerId, array $paymentData): array
    {
        $paymentIntent = $this->stripe->paymentIntents->create([
            'amount' => $subscription->plan->price * 100,
            'currency' => strtolower($subscription->currency),
            'customer' => $customerId,
            'metadata' => [
                'subscription_id' => $subscription->id,
                'plan_name' => $subscription->plan->name,
            ],
            'automatic_payment_methods' => [
                'enabled' => true,
            ],
        ]);

        $subscription->update([
            'transaction_id' => $paymentIntent->id,
            'external_status' => $paymentIntent->status,
            'payment_reference' => $paymentIntent->id,
        ]);

        return [
            'status' => 'created',
            'payment_intent_id' => $paymentIntent->id,
            'client_secret' => $paymentIntent->client_secret,
        ];
    }

    public function cancelSubscription(Subscription $subscription): array
    {
        try {
            $stripeSubscription = $this->stripe->subscriptions->cancel($subscription->subscription_id);

            return [
                'status' => 'cancelled',
                'cancelled_at' => $stripeSubscription->canceled_at,
            ];

        } catch (ApiErrorException $e) {
            Log::error('Stripe cancellation failed', [
                'subscription_id' => $subscription->id,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    public function verifySubscription(array $data): array
    {
        try {
            $subscriptionId = $data['subscription_id'] ?? null;

            if (!$subscriptionId) {
                throw new Exception('Subscription ID is required');
            }

            $stripeSubscription = $this->stripe->subscriptions->retrieve($subscriptionId);

            $subscription = Subscription::where('subscription_id', $subscriptionId)->first();
            if ($subscription) {
                $subscription->update([
                    'external_status' => $stripeSubscription->status,
                    'status_update_time' => now(),
                ]);
            }

            return [
                'success' => true,
                'status' => $stripeSubscription->status,
                'subscription_data' => $stripeSubscription->toArray(),
            ];

        } catch (ApiErrorException $e) {
            Log::error('Stripe verification failed', [
                'data' => $data,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    public function handleWebhook(array $data): array
    {
        try {
            $eventType = $data['type'] ?? null;
            $object = $data['data']['object'] ?? [];

            switch ($eventType) {
                case 'customer.subscription.created':
                case 'customer.subscription.updated':
                    return $this->handleSubscriptionUpdated($object);
                case 'customer.subscription.deleted':
                    return $this->handleSubscriptionDeleted($object);
                case 'invoice.payment_succeeded':
                    return $this->handlePaymentSucceeded($object);
                case 'invoice.payment_failed':
                    return $this->handlePaymentFailed($object);
                default:
                    Log::info('Unhandled Stripe webhook event', ['event_type' => $eventType]);
                    return ['status' => 'ignored'];
            }

        } catch (Exception $e) {
            Log::error('Stripe webhook handling failed', [
                'data' => $data,
                'error' => $e->getMessage()
            ]);

            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    protected function createOrGetCustomer($employer): string
    {
        if ($employer->stripe_customer_id) {
            return $employer->stripe_customer_id;
        }

        $customer = $this->stripe->customers->create([
            'email' => $employer->user->email,
            'name' => $employer->user->first_name . ' ' . $employer->user->last_name,
            'metadata' => [
                'employer_id' => $employer->id,
                'company_name' => $employer->company_name,
            ],
        ]);

        $employer->update(['stripe_customer_id' => $customer->id]);

        return $customer->id;
    }

    protected function createOrGetPrice($plan): string
    {
        if ($plan->external_stripe_id) {
            return $plan->external_stripe_id;
        }

        $priceData = [
            'unit_amount' => $plan->price * 100,
            'currency' => strtolower($plan->currency),
            'product_data' => [
                'name' => $plan->name,
                'description' => $plan->description,
            ],
        ];

        if ($plan->isRecurring()) {
            $priceData['recurring'] = [
                'interval' => strtolower($plan->interval_unit),
                'interval_count' => $plan->interval_count,
            ];
        }

        $price = $this->stripe->prices->create($priceData);

        $plan->update(['external_stripe_id' => $price->id]);

        return $price->id;
    }

    protected function handleSubscriptionUpdated(array $object): array
    {
        $subscriptionId = $object['id'] ?? null;

        if ($subscriptionId) {
            $subscription = Subscription::where('subscription_id', $subscriptionId)->first();
            if ($subscription) {
                $subscription->update([
                    'external_status' => $object['status'],
                    'status_update_time' => now(),
                ]);
            }
        }

        return ['status' => 'processed'];
    }

    protected function handleSubscriptionDeleted(array $object): array
    {
        $subscriptionId = $object['id'] ?? null;

        if ($subscriptionId) {
            $subscription = Subscription::where('subscription_id', $subscriptionId)->first();
            if ($subscription) {
                $subscription->update([
                    'is_active' => false,
                    'external_status' => 'cancelled',
                    'status_update_time' => now(),
                ]);
            }
        }

        return ['status' => 'processed'];
    }

    protected function handlePaymentSucceeded(array $object): array
    {
        Log::info('Stripe payment succeeded', ['object' => $object]);

        return ['status' => 'processed'];
    }

    protected function handlePaymentFailed(array $object): array
    {
        Log::warning('Stripe payment failed', ['object' => $object]);

        return ['status' => 'processed'];
    }
}
