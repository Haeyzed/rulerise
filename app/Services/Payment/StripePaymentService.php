<?php

namespace App\Services\Payment;

use App\Models\Employer;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use Exception;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\ApiErrorException;
use Stripe\PaymentIntent;
use Stripe\Stripe;
use Stripe\Webhook;

class StripePaymentService implements PaymentServiceInterface
{
    /**
     * Constructor
     */
    public function __construct()
    {
        Stripe::setApiKey(config('services.stripe.secret'));
    }

    /**
     * Create a payment intent
     *
     * @param Employer $employer
     * @param SubscriptionPlan $plan
     * @param array $paymentData
     * @return array
     * @throws ApiErrorException
     */
    public function createPaymentIntent(Employer $employer, SubscriptionPlan $plan, array $paymentData): array
    {
        try {
            $amountInCents = (int)($plan->price * 100); // Convert to cents for Stripe
            
            $paymentIntent = PaymentIntent::create([
                'amount' => $amountInCents,
                'currency' => strtolower($plan->currency),
                'payment_method_types' => ['card'],
                'metadata' => [
                    'employer_id' => $employer->id,
                    'plan_id' => $plan->id,
                    'user_id' => $employer->user_id,
                ],
                'description' => "Subscription to {$plan->name} plan",
            ]);
            
            return [
                'client_secret' => $paymentIntent->client_secret,
                'payment_intent_id' => $paymentIntent->id,
                'provider' => $this->getProviderName(),
            ];
        } catch (ApiErrorException $e) {
            Log::error('Stripe payment intent creation failed: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Process a successful payment
     *
     * @param array $paymentData
     * @return Subscription
     * @throws Exception
     */
    public function processPayment(array $paymentData): Subscription
    {
        try {
            $paymentIntentId = $paymentData['payment_intent_id'];
            $paymentIntent = PaymentIntent::retrieve($paymentIntentId);
            
            if ($paymentIntent->status !== 'succeeded') {
                throw new Exception("Payment has not succeeded. Current status: {$paymentIntent->status}");
            }
            
            $employerId = $paymentIntent->metadata['employer_id'];
            $planId = $paymentIntent->metadata['plan_id'];
            
            $employer = Employer::findOrFail($employerId);
            $plan = SubscriptionPlan::findOrFail($planId);
            
            // Calculate dates
            $startDate = now();
            $endDate = $startDate->copy()->addDays($plan->duration_days);
            
            // Create subscription
            return $employer->subscriptions()->create([
                'subscription_plan_id' => $plan->id,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'amount_paid' => $plan->price,
                'currency' => $plan->currency,
                'payment_method' => 'stripe',
                'transaction_id' => $paymentIntent->id,
                'payment_reference' => $paymentIntent->id,
                'job_posts_left' => $plan->job_posts_limit,
                'featured_jobs_left' => $plan->featured_jobs_limit,
                'cv_downloads_left' => $plan->resume_views_limit,
                'is_active' => true,
            ]);
        } catch (Exception $e) {
            Log::error('Stripe payment processing failed: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Handle webhook events from Stripe
     *
     * @param array $payload
     * @return bool
     */
    public function handleWebhook(array $payload): bool
    {
        try {
            $event = Webhook::constructEvent(
                json_encode($payload),
                request()->header('Stripe-Signature'),
                config('services.stripe.webhook_secret')
            );
            
            switch ($event->type) {
                case 'payment_intent.succeeded':
                    $this->handlePaymentIntentSucceeded($event->data->object);
                    break;
                case 'payment_intent.payment_failed':
                    $this->handlePaymentIntentFailed($event->data->object);
                    break;
                // Handle other event types as needed
            }
            
            return true;
        } catch (Exception $e) {
            Log::error('Stripe webhook handling failed: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Handle successful payment intent
     *
     * @param object $paymentIntent
     * @return void
     */
    protected function handlePaymentIntentSucceeded($paymentIntent): void
    {
        // This could update subscription status or send notifications
        Log::info('Payment succeeded: ' . $paymentIntent->id);
    }
    
    /**
     * Handle failed payment intent
     *
     * @param object $paymentIntent
     * @return void
     */
    protected function handlePaymentIntentFailed($paymentIntent): void
    {
        // This could update subscription status or send notifications
        Log::info('Payment failed: ' . $paymentIntent->id);
    }
    
    /**
     * Cancel a subscription
     *
     * @param Subscription $subscription
     * @return bool
     */
    public function cancelSubscription(Subscription $subscription): bool
    {
        // For one-time payments, just mark as inactive
        $subscription->is_active = false;
        return $subscription->save();
        
        // For recurring subscriptions, you would cancel in Stripe first
        // then update the local record
    }
    
    /**
     * Get payment provider name
     *
     * @return string
     */
    public function getProviderName(): string
    {
        return 'stripe';
    }
}
