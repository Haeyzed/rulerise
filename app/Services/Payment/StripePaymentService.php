<?php

namespace App\Services\Payment;

use App\Models\Employer;
use App\Models\Plan;
use App\Models\Subscription;
use App\Notifications\PaymentFailed;
use App\Notifications\PaymentSuccessful;
use App\Notifications\TrialEnding;
use App\Services\Payment\Contracts\PaymentServiceInterface;
use App\Services\Payment\Exceptions\PaymentException;
use Stripe\StripeClient;
use Stripe\Exception\ApiErrorException;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * Stripe Payment Service
 *
 * Handles all Stripe payment operations including subscriptions,
 * one-time payments, webhooks, and subscription management.
 */
class StripePaymentService implements PaymentServiceInterface
{
    private StripeClient $stripe;

    public function __construct()
    {
        $this->stripe = new StripeClient(config('services.stripe.secret'));
    }

    // ========================================
    // CUSTOMER MANAGEMENT
    // ========================================

    /**
     * Create or retrieve Stripe customer with enhanced error handling
     */
    public function createOrGetCustomer(Employer $employer): string
    {
        // Try to use existing customer ID
        if ($employer->stripe_customer_id) {
            try {
                $customer = $this->stripe->customers->retrieve($employer->stripe_customer_id);

                // Verify customer is not deleted
                if (!$customer->deleted) {
                    return $employer->stripe_customer_id;
                }

                Log::warning('Stripe customer was deleted, creating new one', [
                    'employer_id' => $employer->id,
                    'old_customer_id' => $employer->stripe_customer_id
                ]);

            } catch (ApiErrorException $e) {
                Log::warning('Stripe customer not found, creating new one', [
                    'employer_id' => $employer->id,
                    'old_customer_id' => $employer->stripe_customer_id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        // Create new customer
        try {
            $customerData = [
                'email' => $employer->user->email,
                'name' => $employer->getCompanyDisplayName(),
                'description' => "Customer for {$employer->getCompanyDisplayName()}",
                'metadata' => [
                    'employer_id' => $employer->id,
                    'company_name' => $employer->company_name,
                    'created_via' => 'api',
                    'created_at' => now()->toISOString(),
                ],
            ];

            // Add phone if available
            if ($employer->company_phone_number) {
                $customerData['phone'] = $employer->company_phone_number;
            }

            // Add address if available
            if ($employer->company_address || $employer->company_country) {
                $customerData['address'] = [
                    'line1' => $employer->company_address,
                    'country' => $employer->company_country,
                    'state' => $employer->company_state,
                ];
            }

            $customer = $this->stripe->customers->create($customerData);

            // Update employer with new customer ID
            $employer->update(['stripe_customer_id' => $customer->id]);

            Log::info('Stripe customer created successfully', [
                'employer_id' => $employer->id,
                'customer_id' => $customer->id
            ]);

            return $customer->id;

        } catch (ApiErrorException $e) {
            Log::error('Failed to create Stripe customer', [
                'employer_id' => $employer->id,
                'error' => $e->getMessage(),
                'error_code' => $e->getStripeCode()
            ]);

            throw new PaymentException('Failed to create customer: ' . $e->getMessage());
        }
    }

    // ========================================
    // PRODUCT & PRICE MANAGEMENT
    // ========================================

    /**
     * Create or retrieve Stripe price with comprehensive setup
     */
    public function createOrGetPrice(Plan $plan): array
    {
        // Use existing price if available
        if ($plan->stripe_price_id) {
            try {
                $price = $this->stripe->prices->retrieve($plan->stripe_price_id);
                return [
                    'success' => true,
                    'price' => $price,
                ];
            } catch (ApiErrorException $e) {
                Log::warning('Stripe price not found, creating new one', [
                    'plan_id' => $plan->id,
                    'old_price_id' => $plan->stripe_price_id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        try {
            // Create or get product first
            $product = $this->createOrGetProduct($plan);

            // Create price
            $priceData = [
                'product' => $product->id,
                'unit_amount' => (int)($plan->price * 100), // Convert to cents
                'currency' => strtolower($plan->getCurrencyCode()),
                'nickname' => $plan->name,
                'metadata' => [
                    'plan_id' => $plan->id,
                    'plan_slug' => $plan->slug,
                    'created_via' => 'api',
                ],
            ];

            // Add recurring configuration for subscription plans
            if ($plan->isRecurring()) {
                $interval = $plan->isYearly() ? 'year' : 'month';

                $priceData['recurring'] = [
                    'interval' => $interval,
                    'interval_count' => 1,
                    'usage_type' => 'licensed',
                ];

                // Add trial period if plan has trial
                if ($plan->hasTrial()) {
                    $priceData['recurring']['trial_period_days'] = $plan->getTrialPeriodDays();
                }
            }

            $price = $this->stripe->prices->create($priceData);

            // Update plan with Stripe price ID
            $plan->update(['stripe_price_id' => $price->id]);

            Log::info('Stripe price created successfully', [
                'plan_id' => $plan->id,
                'price_id' => $price->id,
                'amount' => $plan->price,
                'currency' => $plan->getCurrencyCode()
            ]);

            return [
                'success' => true,
                'price' => $price,
            ];

        } catch (ApiErrorException $e) {
            Log::error('Failed to create Stripe price', [
                'plan_id' => $plan->id,
                'error' => $e->getMessage(),
                'error_code' => $e->getStripeCode()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Create or retrieve Stripe product
     */
    private function createOrGetProduct(Plan $plan): object
    {
        try {
            // Try to find existing product by metadata
            $products = $this->stripe->products->all([
                'limit' => 10,
                'metadata' => ['plan_id' => $plan->id],
            ]);

            if ($products->data && count($products->data) > 0) {
                return $products->data[0];
            }

            // Create new product
            $productData = [
                'name' => $plan->name,
                'description' => $plan->description ?? "Access to {$plan->name} features",
                'type' => 'service',
                'metadata' => [
                    'plan_id' => $plan->id,
                    'plan_slug' => $plan->slug,
                    'billing_cycle' => $plan->billing_cycle,
                    'created_via' => 'api',
                ],
            ];

            // Add images if available
            if (config('app.logo_url')) {
                $productData['images'] = [config('app.logo_url')];
            }

            // Add statement descriptor
            $productData['statement_descriptor'] = substr(config('app.name'), 0, 22);

            return $this->stripe->products->create($productData);

        } catch (ApiErrorException $e) {
            Log::error('Failed to create Stripe product', [
                'plan_id' => $plan->id,
                'error' => $e->getMessage()
            ]);

            throw new PaymentException('Failed to create product: ' . $e->getMessage());
        }
    }

    // ========================================
    // PAYMENT CREATION
    // ========================================

    /**
     * Create one-time payment using Checkout Session
     */
    public function createOneTimePayment(Employer $employer, Plan $plan): array
    {
        try {
            $customerId = $this->createOrGetCustomer($employer);
            $priceResult = $this->createOrGetPrice($plan);

            if (!$priceResult['success']) {
                throw new PaymentException('Failed to create Stripe price: ' . $priceResult['error']);
            }

            // Create checkout session for one-time payment
            $sessionData = [
                'customer' => $customerId,
                'line_items' => [
                    [
                        'price' => $plan->stripe_price_id,
                        'quantity' => 1,
                    ],
                ],
                'mode' => 'payment',
                'payment_method_types' => ['card'],
                'success_url' => $this->getSuccessUrl() . '&session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => $this->getCancelUrl() . '&session_id={CHECKOUT_SESSION_ID}',
                'metadata' => [
                    'employer_id' => $employer->id,
                    'plan_id' => $plan->id,
                    'payment_type' => 'one_time',
                    'created_at' => now()->toISOString(),
                ],
                'payment_intent_data' => [
                    'description' => "One-time payment for {$plan->name}",
                    'metadata' => [
                        'employer_id' => $employer->id,
                        'plan_id' => $plan->id,
                        'company_name' => $employer->getCompanyDisplayName(),
                    ],
                ],
            ];

            // Remove this line that was causing the conflict:
            // $sessionData['customer_email'] = $employer->user->email;

            // Add automatic tax calculation if enabled
            if (config('services.stripe.automatic_tax', false)) {
                $sessionData['automatic_tax'] = ['enabled' => true];
            }

            $session = $this->stripe->checkout->sessions->create($sessionData);

            // Create subscription record for tracking
            $subscriptionRecord = $this->createSubscriptionRecord(
                $employer,
                $plan,
                $session->id,
                'one_time'
            );

            Log::info('Stripe one-time payment session created', [
                'employer_id' => $employer->id,
                'plan_id' => $plan->id,
                'session_id' => $session->id,
                'amount' => $plan->price
            ]);

            return [
                'success' => true,
                'checkout_session_id' => $session->id,
                'approval_url' => $session->url,
                'subscription' => $subscriptionRecord,
            ];

        } catch (PaymentException $e) {
            throw $e;
        } catch (ApiErrorException $e) {
            Log::error('Stripe one-time payment creation failed', [
                'employer_id' => $employer->id,
                'plan_id' => $plan->id,
                'error' => $e->getMessage(),
                'error_code' => $e->getStripeCode()
            ]);

            return [
                'success' => false,
                'error' => $this->getHumanReadableError($e),
            ];
        } catch (\Exception $e) {
            Log::error('Unexpected error during Stripe one-time payment creation', [
                'employer_id' => $employer->id,
                'plan_id' => $plan->id,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => 'An unexpected error occurred. Please try again.',
            ];
        }
    }

    /**
     * Create recurring subscription using Checkout Session
     */
    public function createSubscription(Employer $employer, Plan $plan): array
    {
        try {
            $customerId = $this->createOrGetCustomer($employer);
            $priceResult = $this->createOrGetPrice($plan);

            if (!$priceResult['success']) {
                throw new PaymentException('Failed to create Stripe price: ' . $priceResult['error']);
            }

            // Create checkout session for subscription
            $sessionData = [
                'customer' => $customerId,
                'line_items' => [
                    [
                        'price' => $plan->stripe_price_id,
                        'quantity' => 1,
                    ],
                ],
                'mode' => 'subscription',
                'payment_method_types' => ['card'],
                'success_url' => $this->getSuccessUrl() . '&session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => $this->getCancelUrl() . '&session_id={CHECKOUT_SESSION_ID}',
                'metadata' => [
                    'employer_id' => $employer->id,
                    'plan_id' => $plan->id,
                    'created_at' => now()->toISOString(),
                ],
            ];

            // Configure subscription-specific settings
            $subscriptionData = [
                'description' => "Subscription to {$plan->name}",
                'metadata' => [
                    'employer_id' => $employer->id,
                    'plan_id' => $plan->id,
                    'company_name' => $employer->getCompanyDisplayName(),
                    'plan_name' => $plan->name,
                ],
            ];

            // Add trial period if plan has trial
            if ($plan->hasTrial()) {
                $subscriptionData['trial_period_days'] = $plan->getTrialPeriodDays();
                $subscriptionData['metadata']['has_trial'] = 'true';
                $subscriptionData['metadata']['trial_days'] = $plan->getTrialPeriodDays();
            }

            $sessionData['subscription_data'] = $subscriptionData;

            // Remove this line that was causing the conflict:
            // $sessionData['customer_email'] = $employer->user->email;

            // Add automatic tax calculation if enabled
            if (config('services.stripe.automatic_tax', false)) {
                $sessionData['automatic_tax'] = ['enabled' => true];
            }

            $session = $this->stripe->checkout->sessions->create($sessionData);

            // Determine trial status
            $isInTrial = $plan->hasTrial();
            $trialStart = $isInTrial ? now() : null;
            $trialEnd = $isInTrial ? now()->addDays($plan->getTrialPeriodDays()) : null;

            // Create subscription record
            $subscriptionRecord = $this->createSubscriptionRecord(
                $employer,
                $plan,
                $session->id,
                'recurring',
                $isInTrial,
                $trialStart,
                $trialEnd
            );

            Log::info('Stripe subscription session created', [
                'employer_id' => $employer->id,
                'plan_id' => $plan->id,
                'session_id' => $session->id,
                'is_trial' => $isInTrial,
                'trial_days' => $plan->getTrialPeriodDays()
            ]);

            return [
                'success' => true,
                'checkout_session_id' => $session->id,
                'approval_url' => $session->url,
                'subscription' => $subscriptionRecord,
                'is_trial' => $isInTrial,
                'trial_end_date' => $trialEnd,
            ];

        } catch (PaymentException $e) {
            throw $e;
        } catch (ApiErrorException $e) {
            Log::error('Stripe subscription creation failed', [
                'employer_id' => $employer->id,
                'plan_id' => $plan->id,
                'error' => $e->getMessage(),
                'error_code' => $e->getStripeCode()
            ]);

            return [
                'success' => false,
                'error' => $this->getHumanReadableError($e),
            ];
        } catch (\Exception $e) {
            Log::error('Unexpected error during Stripe subscription creation', [
                'employer_id' => $employer->id,
                'plan_id' => $plan->id,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => 'An unexpected error occurred. Please try again.',
            ];
        }
    }

    // ========================================
    // SUBSCRIPTION MANAGEMENT
    // ========================================

    /**
     * Cancel subscription with immediate or end-of-period options
     */
    public function cancelSubscription(Subscription $subscription, bool $immediately = false): bool
    {
        try {
            if (!$subscription->subscription_id) {
                Log::error('Cannot cancel Stripe subscription: missing subscription_id', [
                    'subscription_record_id' => $subscription->id
                ]);
                return false;
            }

            if ($immediately) {
                // Cancel immediately
                $this->stripe->subscriptions->cancel($subscription->subscription_id);
            } else {
                // Cancel at period end
                $this->stripe->subscriptions->update($subscription->subscription_id, [
                    'cancel_at_period_end' => true,
                    'metadata' => [
                        'cancelled_at' => now()->toISOString(),
                        'cancel_reason' => 'user_requested',
                    ],
                ]);
            }

            $subscription->cancel();

            Log::info('Stripe subscription cancelled', [
                'subscription_id' => $subscription->subscription_id,
                'immediately' => $immediately
            ]);

            return true;

        } catch (ApiErrorException $e) {
            Log::error('Failed to cancel Stripe subscription', [
                'subscription_id' => $subscription->subscription_id,
                'error' => $e->getMessage(),
                'error_code' => $e->getStripeCode()
            ]);

            return false;
        }
    }

    /**
     * Suspend subscription using pause collection
     */
    public function suspendSubscription(Subscription $subscription): bool
    {
        try {
            if (!$subscription->subscription_id) {
                Log::error('Cannot suspend Stripe subscription: missing subscription_id', [
                    'subscription_record_id' => $subscription->id
                ]);
                return false;
            }

            // Pause the subscription billing
            $this->stripe->subscriptions->update($subscription->subscription_id, [
                'pause_collection' => [
                    'behavior' => 'void', // Don't collect payments
                ],
                'metadata' => [
                    'suspended_at' => now()->toISOString(),
                    'suspended_reason' => 'user_requested',
                ],
            ]);

            $subscription->suspend();

            Log::info('Stripe subscription suspended', [
                'subscription_id' => $subscription->subscription_id
            ]);

            return true;

        } catch (ApiErrorException $e) {
            Log::error('Failed to suspend Stripe subscription', [
                'subscription_id' => $subscription->subscription_id,
                'error' => $e->getMessage(),
                'error_code' => $e->getStripeCode()
            ]);

            return false;
        }
    }

    /**
     * Resume suspended subscription
     */
    public function resumeSubscription(Subscription $subscription): bool
    {
        try {
            if (!$subscription->subscription_id) {
                Log::error('Cannot resume Stripe subscription: missing subscription_id', [
                    'subscription_record_id' => $subscription->id
                ]);
                return false;
            }

            // Resume the subscription by removing pause collection
            $this->stripe->subscriptions->update($subscription->subscription_id, [
                'pause_collection' => null, // Remove pause
                'metadata' => [
                    'resumed_at' => now()->toISOString(),
                ],
            ]);

            $subscription->resume();

            Log::info('Stripe subscription resumed', [
                'subscription_id' => $subscription->subscription_id
            ]);

            return true;

        } catch (ApiErrorException $e) {
            Log::error('Failed to resume Stripe subscription', [
                'subscription_id' => $subscription->subscription_id,
                'error' => $e->getMessage(),
                'error_code' => $e->getStripeCode()
            ]);

            return false;
        }
    }

    // ========================================
    // CHECKOUT SESSION COMPLETION
    // ========================================

    /**
     * Complete Stripe checkout session
     */
    public function completeCheckoutSession(string $sessionId): array
    {
        try {
            $session = $this->stripe->checkout->sessions->retrieve($sessionId, [
                'expand' => ['subscription', 'payment_intent']
            ]);

            if ($session->payment_status !== 'paid') {
                return [
                    'success' => false,
                    'error' => 'Payment not completed',
                ];
            }

            // Find subscription record
            $subscription = Subscription::where('metadata->checkout_session_id', $sessionId)->first();

            if (!$subscription) {
                Log::warning('Subscription not found for checkout session', [
                    'session_id' => $sessionId
                ]);

                return [
                    'success' => false,
                    'error' => 'Subscription record not found',
                ];
            }

            // Handle completion based on mode
            if ($session->mode === 'subscription') {
                $this->handleSubscriptionCheckout($subscription, $session);
            } else {
                $this->handleOneTimePaymentCheckout($subscription, $session);
            }

            return [
                'success' => true,
                'subscription' => $subscription->fresh(),
                'session' => $session,
            ];

        } catch (ApiErrorException $e) {
            Log::error('Failed to complete Stripe checkout session', [
                'session_id' => $sessionId,
                'error' => $e->getMessage(),
                'error_code' => $e->getStripeCode()
            ]);

            return [
                'success' => false,
                'error' => $this->getHumanReadableError($e),
            ];
        }
    }

    // ========================================
    // WEBHOOK HANDLING
    // ========================================

    /**
     * Handle webhook events with comprehensive processing
     */
    public function handleWebhook(array $event): void
    {
        try {
            $eventType = $event['type'] ?? null;
            $eventData = $event['data']['object'] ?? [];

            Log::info('Processing Stripe webhook', [
                'event_type' => $eventType,
                'event_id' => $event['id'] ?? 'unknown'
            ]);

            match ($eventType) {
                'checkout.session.completed' => $this->handleCheckoutSessionCompleted($eventData),
                'invoice.payment_succeeded' => $this->handleInvoicePaymentSucceeded($eventData),
                'invoice.payment_failed' => $this->handleInvoicePaymentFailed($eventData),
                'customer.subscription.created' => $this->handleSubscriptionCreated($eventData),
                'customer.subscription.updated' => $this->handleSubscriptionUpdated($eventData),
                'customer.subscription.deleted' => $this->handleSubscriptionDeleted($eventData),
                'customer.subscription.trial_will_end' => $this->handleTrialWillEnd($eventData),
                'payment_intent.succeeded' => $this->handlePaymentIntentSucceeded($eventData),
                'payment_intent.payment_failed' => $this->handlePaymentIntentFailed($eventData),
                default => Log::info('Unhandled Stripe webhook event', ['type' => $eventType])
            };

        } catch (\Exception $e) {
            Log::error('Stripe webhook handling failed', [
                'event_type' => $event['type'] ?? 'unknown',
                'event_id' => $event['id'] ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    // ========================================
    // WEBHOOK EVENT HANDLERS
    // ========================================

    private function handleCheckoutSessionCompleted(array $session): void
    {
        $subscription = Subscription::where('metadata->checkout_session_id', $session['id'])->first();

        if (!$subscription) {
            Log::warning('Subscription not found for checkout session webhook', [
                'session_id' => $session['id']
            ]);
            return;
        }

        try {
            if ($session['mode'] === 'subscription' && isset($session['subscription'])) {
                $this->handleSubscriptionCheckout($subscription, $session);
            } elseif ($session['mode'] === 'payment') {
                $this->handleOneTimePaymentCheckout($subscription, $session);
            }

            Log::info('Checkout session completed webhook processed', [
                'session_id' => $session['id'],
                'subscription_id' => $subscription->id,
                'mode' => $session['mode']
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to handle checkout session completion webhook', [
                'session_id' => $session['id'],
                'subscription_id' => $subscription->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    private function handleSubscriptionCheckout(Subscription $subscription, $session): void
    {
        // Convert session to array if it's a Stripe object
        $sessionData = is_array($session) ? $session : $session->toArray();

        // Get subscription ID from session data
        $subscriptionId = $sessionData['subscription'] ?? null;

        if (!$subscriptionId) {
            Log::error('No subscription ID found in session data', [
                'session_id' => $sessionData['id'] ?? 'unknown'
            ]);
            return;
        }

        // Get full subscription details from Stripe
        $stripeSubscription = $this->stripe->subscriptions->retrieve(
            $subscriptionId,
            ['expand' => ['latest_invoice.payment_intent']]
        );

        // Replace the existing timestamp handling with null-safe versions:
        $isInTrial = $stripeSubscription->status === 'trialing';
        $trialStart = $isInTrial && isset($stripeSubscription->trial_start) && $stripeSubscription->trial_start
            ? Carbon::createFromTimestamp($stripeSubscription->trial_start)
            : null;
        $trialEnd = $isInTrial && isset($stripeSubscription->trial_end) && $stripeSubscription->trial_end
            ? Carbon::createFromTimestamp($stripeSubscription->trial_end)
            : null;

        // Update subscription record with null-safe timestamp handling
        $updateData = [
            'subscription_id' => $stripeSubscription->id,
            'status' => $this->mapStripeStatus($stripeSubscription->status),
            'start_date' => isset($stripeSubscription->current_period_start)
                ? Carbon::createFromTimestamp($stripeSubscription->current_period_start)
                : now(),
            'end_date' => isset($stripeSubscription->current_period_end)
                ? Carbon::createFromTimestamp($stripeSubscription->current_period_end)
                : now()->addMonth(),
            'next_billing_date' => isset($stripeSubscription->current_period_end)
                ? Carbon::createFromTimestamp($stripeSubscription->current_period_end)
                : now()->addMonth(),
            'trial_start_date' => $trialStart,
            'trial_end_date' => $trialEnd,
            'is_trial' => $isInTrial,
            'metadata' => array_merge($subscription->metadata ?? [], [
                'stripe_subscription' => $stripeSubscription->toArray(),
                'checkout_completed_at' => now()->toISOString(),
            ]),
            'is_active' => in_array($stripeSubscription->status, ['active', 'trialing']),
        ];

        $subscription->update($updateData);
        $subscription->activate($stripeSubscription->toArray());
    }

    private function handleOneTimePaymentCheckout(Subscription $subscription, $session): void
    {
        // Convert session to array if it's a Stripe object
        $sessionData = is_array($session) ? $session : $session->toArray();

        $subscription->update([
            'subscription_id' => $sessionData['id'],
            'status' => Subscription::STATUS_ACTIVE,
            'is_active' => true,
            'metadata' => array_merge($subscription->metadata ?? [], [
                'checkout_session' => $sessionData,
                'payment_completed_at' => now()->toISOString(),
            ]),
        ]);

        $subscription->activate($sessionData);
    }

    private function handleInvoicePaymentSucceeded(array $invoice): void
    {
        if (!isset($invoice['subscription'])) {
            return;
        }

        $subscription = Subscription::where('subscription_id', $invoice['subscription'])->first();

        if ($subscription) {
            // Send payment successful notification
            $subscription->employer->notify(new PaymentSuccessful($subscription, [
                'amount' => $invoice['amount_paid'] / 100,
                'currency' => strtoupper($invoice['currency']),
                'invoice_id' => $invoice['id'],
                'invoice_url' => $invoice['hosted_invoice_url'] ?? null,
            ]));

            // End trial if this is the first payment after trial
            if ($subscription->isInTrial()) {
                $subscription->endTrial();
            }

            Log::info('Invoice payment succeeded webhook processed', [
                'invoice_id' => $invoice['id'],
                'subscription_id' => $invoice['subscription'],
                'amount' => $invoice['amount_paid'] / 100
            ]);
        }
    }

    private function handleInvoicePaymentFailed(array $invoice): void
    {
        if (!isset($invoice['subscription'])) {
            return;
        }

        $subscription = Subscription::where('subscription_id', $invoice['subscription'])->first();

        if ($subscription) {
            $subscription->markPaymentFailed();

            // Send payment failed notification
            $subscription->employer->notify(new PaymentFailed($subscription, [
                'amount' => $invoice['amount_due'] / 100,
                'currency' => strtoupper($invoice['currency']),
                'invoice_id' => $invoice['id'],
                'failure_reason' => $invoice['last_finalization_error']['message'] ?? 'Payment failed',
                'invoice_url' => $invoice['hosted_invoice_url'] ?? null,
            ]));

            Log::warning('Invoice payment failed webhook processed', [
                'invoice_id' => $invoice['id'],
                'subscription_id' => $invoice['subscription'],
                'amount' => $invoice['amount_due'] / 100
            ]);
        }
    }

    private function handleSubscriptionCreated(array $subscription): void
    {
        $subscriptionRecord = Subscription::where('subscription_id', $subscription['id'])->first();

        if ($subscriptionRecord) {
            $subscriptionRecord->update([
                'status' => $this->mapStripeStatus($subscription['status']),
                'metadata' => array_merge($subscriptionRecord->metadata ?? [], [
                    'stripe_subscription_created' => $subscription,
                ]),
            ]);

            Log::info('Subscription created webhook processed', [
                'subscription_id' => $subscription['id'],
                'status' => $subscription['status']
            ]);
        }
    }

    private function handleSubscriptionUpdated(array $subscription): void
    {
        $subscriptionRecord = Subscription::where('subscription_id', $subscription['id'])->first();

        if ($subscriptionRecord) {
            $updateData = [
                'status' => $this->mapStripeStatus($subscription['status']),
                'is_active' => in_array($subscription['status'], ['active', 'trialing']),
                'metadata' => array_merge($subscriptionRecord->metadata ?? [], [
                    'last_updated_at' => now()->toISOString(),
                    'stripe_status' => $subscription['status'],
                ]),
            ];

            // Only update period dates if they exist (cancelled subscriptions may not have them)
            if (isset($subscription['current_period_end'])) {
                $updateData['end_date'] = Carbon::createFromTimestamp($subscription['current_period_end']);
                $updateData['next_billing_date'] = Carbon::createFromTimestamp($subscription['current_period_end']);
            }

            // Handle trial status changes
            if (isset($subscription['trial_end'])) {
                $isInTrial = $subscription['status'] === 'trialing';
                $trialEnd = Carbon::createFromTimestamp($subscription['trial_end']);

                $updateData['is_trial'] = $isInTrial;
                $updateData['trial_end_date'] = $trialEnd;

                if ($isInTrial && $trialEnd->isPast()) {
                    $updateData['trial_ended'] = true;
                    $updateData['is_trial'] = false;
                }
            }

            $subscriptionRecord->update($updateData);

            Log::info('Subscription updated webhook processed', [
                'subscription_id' => $subscription['id'],
                'status' => $subscription['status'],
                'has_period_end' => isset($subscription['current_period_end'])
            ]);
        }
    }

    private function handleSubscriptionDeleted(array $subscription): void
    {
        $subscriptionRecord = Subscription::where('subscription_id', $subscription['id'])->first();

        if ($subscriptionRecord) {
            $subscriptionRecord->cancel();

            Log::info('Subscription deleted webhook processed', [
                'subscription_id' => $subscription['id']
            ]);
        }
    }

    private function handleTrialWillEnd(array $subscription): void
    {
        $subscriptionRecord = Subscription::where('subscription_id', $subscription['id'])->first();

        if ($subscriptionRecord && $subscriptionRecord->isInTrial()) {
            $subscriptionRecord->employer->notify(new TrialEnding($subscriptionRecord));

            Log::info('Trial will end webhook processed', [
                'subscription_id' => $subscription['id'],
                'trial_end' => Carbon::createFromTimestamp($subscription['trial_end'])->toISOString()
            ]);
        }
    }

    private function handlePaymentIntentSucceeded(array $paymentIntent): void
    {
        // Handle successful one-time payments
        if (isset($paymentIntent['metadata']['employer_id'])) {
            Log::info('Payment intent succeeded', [
                'payment_intent_id' => $paymentIntent['id'],
                'amount' => $paymentIntent['amount'] / 100,
                'employer_id' => $paymentIntent['metadata']['employer_id']
            ]);
        }
    }

    private function handlePaymentIntentFailed(array $paymentIntent): void
    {
        // Handle failed one-time payments
        if (isset($paymentIntent['metadata']['employer_id'])) {
            Log::warning('Payment intent failed', [
                'payment_intent_id' => $paymentIntent['id'],
                'amount' => $paymentIntent['amount'] / 100,
                'employer_id' => $paymentIntent['metadata']['employer_id'],
                'failure_reason' => $paymentIntent['last_payment_error']['message'] ?? 'Unknown error'
            ]);
        }
    }

    // ========================================
    // UTILITY METHODS
    // ========================================

    /**
     * Create subscription record in database
     */
    private function createSubscriptionRecord(
        Employer $employer,
        Plan $plan,
        string $sessionId,
        string $paymentType,
        bool $isInTrial = false,
        ?Carbon $trialStart = null,
        ?Carbon $trialEnd = null
    ): Subscription {
        return Subscription::create([
            'employer_id' => $employer->id,
            'plan_id' => $plan->id,
            'subscription_id' => null, // Will be updated after checkout completion
            'payment_provider' => Subscription::PROVIDER_STRIPE,
            'status' => Subscription::STATUS_PENDING,
            'amount' => $plan->price,
            'currency' => $plan->getCurrencyCode(),
            'start_date' => now(),
            'end_date' => $plan->isOneTime() ? now()->addDays($plan->duration_days ?? 30) : null,
            'next_billing_date' => $this->calculateNextBillingDate($plan, $isInTrial),
            'trial_start_date' => $trialStart,
            'trial_end_date' => $trialEnd,
            'is_trial' => $isInTrial,
            'trial_ended' => false,
            'cv_downloads_left' => $plan->resume_views_limit,
            'metadata' => [
                'checkout_session_id' => $sessionId,
                'payment_type' => $paymentType,
                'created_via' => 'stripe_checkout',
                'created_at' => now()->toISOString(),
            ],
            'is_active' => false, // Will be activated after checkout completion
        ]);
    }

    /**
     * Calculate next billing date
     */
    private function calculateNextBillingDate(Plan $plan, bool $isInTrial = false): ?Carbon
    {
        if ($plan->isOneTime()) {
            return null;
        }

        $startDate = $isInTrial ? now()->addDays($plan->getTrialPeriodDays()) : now();

        return match ($plan->billing_cycle) {
            Plan::BILLING_MONTHLY => $startDate->copy()->addMonth(),
            Plan::BILLING_YEARLY => $startDate->copy()->addYear(),
            default => null,
        };
    }

    /**
     * Map Stripe status to internal status
     */
    private function mapStripeStatus(string $stripeStatus): string
    {
        return match ($stripeStatus) {
            'active', 'trialing' => Subscription::STATUS_ACTIVE,
            'canceled' => Subscription::STATUS_CANCELED,
            'incomplete', 'incomplete_expired' => Subscription::STATUS_PENDING,
            'past_due' => Subscription::STATUS_PAYMENT_FAILED,
            'paused' => Subscription::STATUS_SUSPENDED,
            default => $stripeStatus,
        };
    }

    /**
     * Get human-readable error message
     */
    private function getHumanReadableError(ApiErrorException $e): string
    {
        return match ($e->getStripeCode()) {
            'card_declined' => 'Your card was declined. Please try a different payment method.',
            'insufficient_funds' => 'Insufficient funds. Please check your account balance.',
            'expired_card' => 'Your card has expired. Please update your payment method.',
            'incorrect_cvc' => 'The security code is incorrect. Please check and try again.',
            'processing_error' => 'A processing error occurred. Please try again.',
            'rate_limit' => 'Too many requests. Please wait a moment and try again.',
            default => 'Payment processing failed. Please try again or contact support.',
        };
    }

    /**
     * Get success URL for checkout sessions
     */
    private function getSuccessUrl(): string
    {
        return config('app.frontend_url') . '/employer/dashboard?payment_status=success&payment_provider=stripe';
    }

    /**
     * Get cancel URL for checkout sessions
     */
    private function getCancelUrl(): string
    {
        return config('app.frontend_url') . '/employer/dashboard?payment_status=cancelled&payment_provider=stripe';
    }
}
