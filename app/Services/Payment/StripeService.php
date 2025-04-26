<?php

namespace App\Services\Payment;

use App\Models\Employer;
use App\Models\SubscriptionPlan;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;

/**
 * Service for Stripe payment gateway
 */
class StripeService implements PaymentGatewayInterface
{
    /**
     * Stripe client
     *
     * @var StripeClient
     */
    protected StripeClient $stripe;

    /**
     * StripeService constructor
     */
    public function __construct()
    {
        $this->stripe = new StripeClient(config('services.stripe.secret'));
    }

    /**
     * Process a payment
     *
     * @param Employer $employer
     * @param SubscriptionPlan $plan
     * @param array $paymentData
     * @return array
     */
    public function processPayment(Employer $employer, SubscriptionPlan $plan, array $paymentData): array
    {
        try {
            // For Stripe, we need to verify the session or payment intent
            if (isset($paymentData['session_id'])) {
                $session = $this->stripe->checkout->sessions->retrieve($paymentData['session_id']);
                
                if ($session->payment_status === 'paid') {
                    return [
                        'success' => true,
                        'transaction_id' => $session->payment_intent,
                        'subscription_id' => $session->subscription ?? null,
                        'customer_id' => $session->customer,
                        'message' => 'Payment processed successfully'
                    ];
                }
            } elseif (isset($paymentData['payment_intent'])) {
                $paymentIntent = $this->stripe->paymentIntents->retrieve($paymentData['payment_intent']);
                
                if ($paymentIntent->status === 'succeeded') {
                    return [
                        'success' => true,
                        'transaction_id' => $paymentIntent->id,
                        'customer_id' => $paymentIntent->customer,
                        'message' => 'Payment processed successfully'
                    ];
                }
            }

            return [
                'success' => false,
                'message' => 'Invalid Stripe payment data'
            ];
        } catch (ApiErrorException $e) {
            Log::error('Stripe payment error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Stripe payment failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Generate a payment link
     *
     * @param Employer $employer
     * @param SubscriptionPlan $plan
     * @param string $callbackUrl
     * @return array
     */
    public function generatePaymentLink(Employer $employer, SubscriptionPlan $plan, string $callbackUrl): array
    {
        try {
            // Create or get customer
            $customer = $this->getOrCreateCustomer($employer);
            
            // Create a checkout session
            $session = $this->stripe->checkout->sessions->create([
                'customer' => $customer->id,
                'payment_method_types' => ['card'],
                'line_items' => [
                    [
                        'price_data' => [
                            'currency' => strtolower($plan->currency),
                            'product_data' => [
                                'name' => $plan->name,
                                'description' => $plan->description,
                            ],
                            'unit_amount' => (int)($plan->price * 100), // Convert to cents
                        ],
                        'quantity' => 1,
                    ],
                ],
                'mode' => 'payment', // Use 'subscription' for recurring
                'success_url' => $callbackUrl . '?session_id={CHECKOUT_SESSION_ID}&plan_id=' . $plan->id,
                'cancel_url' => $callbackUrl . '?canceled=true',
                'metadata' => [
                    'employer_id' => $employer->id,
                    'plan_id' => $plan->id
                ]
            ]);

            return [
                'success' => true,
                'payment_link' => $session->url,
                'session_id' => $session->id,
                'message' => 'Stripe payment link generated successfully'
            ];
        } catch (ApiErrorException $e) {
            Log::error('Stripe payment link error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to generate Stripe payment link: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Verify a payment
     *
     * @param string $sessionId
     * @return array
     */
    public function verifyPayment(string $sessionId): array
    {
        try {
            $session = $this->stripe->checkout->sessions->retrieve($sessionId);
            
            if ($session->payment_status === 'paid') {
                return [
                    'success' => true,
                    'transaction_id' => $session->payment_intent,
                    'subscription_id' => $session->subscription ?? null,
                    'customer_id' => $session->customer,
                    'metadata' => $session->metadata->toArray(),
                    'message' => 'Payment verified successfully'
                ];
            }
            
            return [
                'success' => false,
                'message' => 'Payment not completed'
            ];
        } catch (ApiErrorException $e) {
            Log::error('Stripe payment verification error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to verify Stripe payment: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Cancel a subscription
     *
     * @param string $subscriptionId
     * @return bool
     */
    public function cancelSubscription(string $subscriptionId): bool
    {
        try {
            if (!$subscriptionId) {
                return true; // Nothing to cancel
            }
            
            $this->stripe->subscriptions->cancel($subscriptionId);
            return true;
        } catch (ApiErrorException $e) {
            Log::error('Failed to cancel Stripe subscription: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get or create a Stripe customer
     *
     * @param Employer $employer
     * @return \Stripe\Customer
     * @throws ApiErrorException
     */
    protected function getOrCreateCustomer(Employer $employer): \Stripe\Customer
    {
        $user = $employer->user;
        
        // Check if customer already exists
        if ($employer->stripe_customer_id) {
            try {
                return $this->stripe->customers->retrieve($employer->stripe_customer_id);
            } catch (ApiErrorException $e) {
                // Customer not found, create a new one
            }
        }
        
        // Create new customer
        $customer = $this->stripe->customers->create([
            'email' => $employer->company_email ?? $user->email,
            'name' => $employer->company_name,
            'description' => 'Employer ID: ' . $employer->id,
            'metadata' => [
                'employer_id' => $employer->id,
                'user_id' => $user->id
            ]
        ]);
        
        // Save customer ID to employer
        $employer->stripe_customer_id = $customer->id;
        $employer->save();
        
        return $customer;
    }
}
