<?php

namespace App\Services\Subscription;

use App\Models\Employer;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
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
            ]);

            // Convert price to cents/smallest currency unit
            $unitAmount = (int) ($plan->price * 100);

            // Create a price with trial period
            $price = $this->stripe->prices->create([
                'product' => $product->id,
                'unit_amount' => $unitAmount,
                'currency' => strtolower($plan->currency),
                'recurring' => [
                    'interval' => 'day',
                    'interval_count' => $plan->duration_days,
                    'trial_period_days' => 7, // 7-day free trial
                ],
                'metadata' => [
                    'plan_id' => $plan->id,
                ]
            ]);

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
            ]);

            // Note: Stripe doesn't allow updating prices, so we'll need to create a new one
            // if the price changes and update the external_stripe_id in the database

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

            // Create a customer if not exists
            $customerId = $employer->stripe_customer_id;
            if (!$customerId) {
                $customer = $this->stripe->customers->create([
                    'email' => $employer->user->email ?? $employer->company_email,
                    'name' => $employer->company_name,
                    'metadata' => [
                        'employer_id' => $employer->id,
                        'user_id' => $employer->user_id
                    ]
                ]);
                $customerId = $customer->id;
                
                // Save customer ID to employer
                $employer->stripe_customer_id = $customerId;
                $employer->save();
            }

            // Create a checkout session
            $session = $this->stripe->checkout->sessions->create([
                'customer' => $customerId,
                'payment_method_types' => ['card'],
                'line_items' => [
                    [
                        'price' => $externalPlanId,
                        'quantity' => 1,
                    ],
                ],
                'mode' => 'subscription',
                'success_url' => url('/api/subscription/stripe/success?session_id={CHECKOUT_SESSION_ID}'),
                'cancel_url' => url('/api/subscription/stripe/cancel'),
                'metadata' => [
                    'employer_id' => $employer->id,
                    'plan_id' => $plan->id
                ]
            ]);

            // Create a pending subscription record
            $subscription = new Subscription([
                'employer_id' => $employer->id,
                'subscription_plan_id' => $plan->id,
                'start_date' => Carbon::now(),
                'end_date' => Carbon::now()->addDays($plan->duration_days + 7), // Including trial
                'amount_paid' => $plan->price,
                'currency' => $plan->currency,
                'payment_method' => 'stripe',
                'payment_reference' => $session->id,
                'job_posts_left' => $plan->job_posts_limit,
                'featured_jobs_left' => $plan->featured_jobs_limit,
                'cv_downloads_left' => $plan->resume_views_limit,
                'is_active' => false // Will be activated when payment is confirmed
            ]);
            $subscription->save();

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
     * Handle webhook events from Stripe
     * 
     * @param string $payload
     * @param array $headers
     * @return bool
     */
    public function handleWebhook(string $payload, array $headers): bool
    {
        $sigHeader = $headers['Stripe-Signature'] ?? '';
        
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
        
        // Update subscription with Stripe subscription ID
        $subscription->subscription_id = $session->subscription;
        $subscription->save();
        
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
            // Try to find by customer ID and plan ID
            $employer = Employer::where('stripe_customer_id', $stripeSubscription->customer)->first();
            
            if (!$employer) {
                Log::error('Stripe employer not found for customer', ['customerId' => $stripeSubscription->customer]);
                return false;
            }
            
            // Find the subscription by employer and payment method
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
            
            // Update subscription with Stripe subscription ID
            $subscription->subscription_id = $stripeSubscription->id;
        }
        
        // Update subscription status based on Stripe status
        if ($stripeSubscription->status === 'active' || $stripeSubscription->status === 'trialing') {
            $subscription->is_active = true;
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
        // Find the subscription by Stripe subscription ID
        $subscription = Subscription::where('subscription_id', $stripeSubscription->id)
            ->where('payment_method', 'stripe')
            ->first();
            
        if (!$subscription) {
            Log::error('Stripe subscription not found', ['subscriptionId' => $stripeSubscription->id]);
            return false;
        }
        
        // Update subscription status based on Stripe status
        if ($stripeSubscription->status === 'active' || $stripeSubscription->status === 'trialing') {
            $subscription->is_active = true;
        } else {
            $subscription->is_active = false;
        }
        
        $subscription->save();
        
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
        // Find the subscription by Stripe subscription ID
        $subscription = Subscription::where('subscription_id', $stripeSubscription->id)
            ->where('payment_method', 'stripe')
            ->first();
            
        if (!$subscription) {
            Log::error('Stripe subscription not found', ['subscriptionId' => $stripeSubscription->id]);
            return false;
        }
        
        // Update subscription status
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
        
        // Find the subscription by Stripe subscription ID
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
        
        // Update transaction ID
        $subscription->transaction_id = $invoice->payment_intent;
        $subscription->save();
        
        return true;
    }
}