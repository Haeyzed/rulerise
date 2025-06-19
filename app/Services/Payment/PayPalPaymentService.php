<?php

namespace App\Services\Payment;

use App\Models\Employer;
use App\Models\Plan;
use App\Models\Subscription;
use App\Notifications\PaymentFailed;
use App\Notifications\PaymentSuccessful;
use App\Services\Payment\Contracts\PaymentServiceInterface;
use App\Services\Payment\Exceptions\PaymentException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Illuminate\Support\Str;

/**
 * PayPal Payment Service
 *
 * Handles all PayPal payment operations including subscriptions,
 * one-time payments, webhooks, and subscription management.
 */
class PayPalPaymentService implements PaymentServiceInterface
{
    private string $baseUrl;
    private string $clientId;
    private string $clientSecret;
    private string $webhookId;
    private ?string $accessToken = null;

    public function __construct()
    {
        $this->baseUrl = config('services.paypal.mode') === 'live'
            ? 'https://api.paypal.com'
            : 'https://api.sandbox.paypal.com';

        $this->clientId = config('services.paypal.client_id');
        $this->clientSecret = config('services.paypal.client_secret');
        $this->webhookId = config('services.paypal.webhook_id');
    }

    // ========================================
    // AUTHENTICATION
    // ========================================

    /**
     * Get PayPal access token with caching
     */
    private function getAccessToken(): string
    {
        if ($this->accessToken) {
            return $this->accessToken;
        }

        try {
            $response = Http::withBasicAuth($this->clientId, $this->clientSecret)
                ->asForm()
                ->timeout(30)
                ->post($this->baseUrl . '/v1/oauth2/token', [
                    'grant_type' => 'client_credentials'
                ]);

            if (!$response->successful()) {
                Log::error('PayPal access token error', [
                    'status' => $response->status(),
                    'response' => $response->json()
                ]);
                throw new PaymentException('Failed to get PayPal access token');
            }

            $this->accessToken = $response->json('access_token');
            return $this->accessToken;

        } catch (\Exception $e) {
            Log::error('PayPal authentication failed', [
                'error' => $e->getMessage()
            ]);
            throw new PaymentException('PayPal authentication failed: ' . $e->getMessage());
        }
    }

    // ========================================
    // PRODUCT & PLAN MANAGEMENT
    // ========================================

    /**
     * Create PayPal product (required before creating plan)
     */
    public function createProduct(Plan $plan): string
    {
        try {
            $response = Http::withToken($this->getAccessToken())
                ->withHeaders(['PayPal-Request-Id' => Str::uuid()->toString()])
                ->timeout(30)
                ->post($this->baseUrl . '/v1/catalogs/products', [
                    'name' => $plan->name,
                    'description' => $plan->description ?? $plan->name,
                    'type' => 'SERVICE',
                    'category' => 'SOFTWARE',
                    'image_url' => config('app.url') . '/images/logo.png',
                    'home_url' => config('app.url'),
                ]);

            if (!$response->successful()) {
                Log::error('PayPal product creation failed', [
                    'plan_id' => $plan->id,
                    'status' => $response->status(),
                    'response' => $response->json()
                ]);
                throw new PaymentException('Failed to create PayPal product: ' . $response->body());
            }

            $productId = $response->json('id');
            $plan->update(['paypal_product_id' => $productId]);

            Log::info('PayPal product created successfully', [
                'plan_id' => $plan->id,
                'product_id' => $productId
            ]);

            return $productId;

        } catch (PaymentException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('PayPal product creation exception', [
                'plan_id' => $plan->id,
                'error' => $e->getMessage()
            ]);
            throw new PaymentException('Failed to create PayPal product: ' . $e->getMessage());
        }
    }

    /**
     * Create PayPal billing plan with comprehensive trial support
     */
    public function createPlan(Plan $plan): array
    {
        try {
            // Ensure product exists
            $productId = $plan->paypal_product_id ?: $this->createProduct($plan);
            $billingCycles = $this->buildBillingCycles($plan);

            $planData = [
                'product_id' => $productId,
                'name' => $plan->name,
                'description' => $plan->description ?? $plan->name,
                'status' => 'ACTIVE',
                'billing_cycles' => $billingCycles,
                'payment_preferences' => [
                    'auto_bill_outstanding' => true,
                    'setup_fee_failure_action' => 'CONTINUE',
                    'payment_failure_threshold' => 3
                ],
            ];

            $response = Http::withToken($this->getAccessToken())
                ->withHeaders([
                    'PayPal-Request-Id' => 'PLAN-' . Str::uuid()->toString(),
                    'Prefer' => 'return=representation'
                ])
                ->timeout(30)
                ->post($this->baseUrl . '/v1/billing/plans', $planData);

            if (!$response->successful()) {
                Log::error('PayPal plan creation failed', [
                    'plan_id' => $plan->id,
                    'status' => $response->status(),
                    'response' => $response->json()
                ]);
                throw new PaymentException('Failed to create PayPal plan: ' . $response->body());
            }

            $paypalPlanId = $response->json('id');
            $plan->update(['paypal_plan_id' => $paypalPlanId]);

            Log::info('PayPal plan created successfully', [
                'plan_id' => $plan->id,
                'paypal_plan_id' => $paypalPlanId
            ]);

            return [
                'success' => true,
                'plan' => $response->json()
            ];

        } catch (PaymentException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('PayPal plan creation exception', [
                'plan_id' => $plan->id,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Build optimized billing cycles for PayPal plan
     */
    private function buildBillingCycles(Plan $plan): array
    {
        $cycles = [];
        $sequence = 1;

        // Add trial cycle if plan has trial
        if ($plan->hasTrial()) {
            $cycles[] = [
                'frequency' => [
                    'interval_unit' => 'DAY',
                    'interval_count' => $plan->getTrialPeriodDays()
                ],
                'tenure_type' => 'TRIAL',
                'sequence' => $sequence++,
                'total_cycles' => 1,
                'pricing_scheme' => [
                    'fixed_price' => [
                        'value' => '0.00',
                        'currency_code' => $plan->getCurrencyCode()
                    ]
                ]
            ];
        }

        // Add regular billing cycle
        $intervalUnit = $plan->isYearly() ? 'YEAR' : 'MONTH';
        $totalCycles = $plan->isYearly() ? 50 : 120; // Reasonable limits

        $cycles[] = [
            'frequency' => [
                'interval_unit' => $intervalUnit,
                'interval_count' => 1
            ],
            'tenure_type' => 'REGULAR',
            'sequence' => $sequence,
            'total_cycles' => $totalCycles,
            'pricing_scheme' => [
                'fixed_price' => [
                    'value' => number_format($plan->price, 2, '.', ''),
                    'currency_code' => $plan->getCurrencyCode()
                ]
            ]
        ];

        return $cycles;
    }

    // ========================================
    // PAYMENT CREATION
    // ========================================

    /**
     * Create one-time payment with enhanced error handling
     */
    public function createOneTimePayment(Employer $employer, Plan $plan): array
    {
        try {
            $orderData = [
                'intent' => 'CAPTURE',
                'purchase_units' => [
                    [
                        'amount' => [
                            'currency_code' => $plan->getCurrencyCode(),
                            'value' => number_format($plan->price, 2, '.', '')
                        ],
                        'description' => "One-time payment for {$plan->name} plan",
                        'custom_id' => "employer_{$employer->id}_plan_{$plan->id}",
                    ]
                ],
                'payment_source' => [
                    'paypal' => [
                        'experience_context' => [
                            'payment_method_preference' => 'IMMEDIATE_PAYMENT_REQUIRED',
                            'brand_name' => config('app.name'),
                            'locale' => 'en-US',
                            'landing_page' => 'LOGIN',
                            'user_action' => 'PAY_NOW',
                            'return_url' => $this->getReturnUrl('success'),
                            'cancel_url' => $this->getReturnUrl('cancelled'),
                        ]
                    ]
                ]
            ];

            $response = Http::withToken($this->getAccessToken())
                ->withHeaders(['PayPal-Request-Id' => Str::uuid()->toString()])
                ->timeout(30)
                ->post($this->baseUrl . '/v2/checkout/orders', $orderData);

            if (!$response->successful()) {
                throw new PaymentException('PayPal order creation failed: ' . $response->body());
            }

            $order = $response->json();
            $subscription = $this->createSubscriptionRecord($employer, $plan, $order, 'one_time');
            $approvalUrl = $this->extractApprovalUrl($order['links']);

            Log::info('PayPal one-time payment created', [
                'employer_id' => $employer->id,
                'plan_id' => $plan->id,
                'order_id' => $order['id']
            ]);

            return [
                'success' => true,
                'order_id' => $order['id'],
                'approval_url' => $approvalUrl,
                'subscription' => $subscription,
            ];

        } catch (PaymentException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('PayPal one-time payment creation failed', [
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
            // Ensure PayPal plan exists
            if (!$plan->paypal_plan_id) {
                $result = $this->createPlan($plan);
                if (!$result['success']) {
                    throw new PaymentException('Failed to create PayPal plan: ' . $result['error']);
                }
            }

            $subscriptionData = [
                'plan_id' => $plan->paypal_plan_id,
                'subscriber' => [
                    'name' => [
                        'given_name' => $employer->user->first_name ?? 'User',
                        'surname' => $employer->user->last_name ?? 'User',
                    ],
                    'email_address' => $employer->user->email,
                ],
                'application_context' => [
                    'brand_name' => config('app.name'),
                    'locale' => 'en-US',
                    'shipping_preference' => 'NO_SHIPPING',
                    'user_action' => 'SUBSCRIBE_NOW',
                    'payment_method' => [
                        'payer_selected' => 'PAYPAL',
                        'payee_preferred' => 'IMMEDIATE_PAYMENT_REQUIRED',
                    ],
                    'return_url' => $this->getReturnUrl('success'),
                    'cancel_url' => $this->getReturnUrl('cancelled'),
                ],
                'custom_id' => "employer_{$employer->id}_plan_{$plan->id}",
            ];

            $response = Http::withToken($this->getAccessToken())
                ->withHeaders([
                    'PayPal-Request-Id' => 'SUBSCRIPTION-' . Str::uuid()->toString(),
                    'Prefer' => 'return=representation'
                ])
                ->timeout(30)
                ->post($this->baseUrl . '/v1/billing/subscriptions', $subscriptionData);

            if (!$response->successful()) {
                throw new PaymentException('PayPal subscription creation failed: ' . $response->body());
            }

            $subscription = $response->json();
            $isInTrial = $plan->hasTrial();

            $subscriptionRecord = $this->createSubscriptionRecord(
                $employer,
                $plan,
                $subscription,
                'recurring',
                $isInTrial
            );

            $approvalUrl = $this->extractApprovalUrl($subscription['links']);

            Log::info('PayPal subscription created', [
                'employer_id' => $employer->id,
                'plan_id' => $plan->id,
                'subscription_id' => $subscription['id'],
                'is_trial' => $isInTrial
            ]);

            return [
                'success' => true,
                'subscription_id' => $subscription['id'],
                'approval_url' => $approvalUrl,
                'subscription' => $subscriptionRecord,
                'is_trial' => $isInTrial,
                'trial_end_date' => $isInTrial ? now()->addDays($plan->getTrialPeriodDays()) : null,
            ];

        } catch (PaymentException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('PayPal subscription creation failed', [
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

    // ========================================
    // SUBSCRIPTION MANAGEMENT
    // ========================================

    public function cancelSubscription(Subscription $subscription): bool
    {
        return $this->updateSubscriptionStatus($subscription, 'cancel', [
            'reason' => 'User requested cancellation'
        ]);
    }

    public function suspendSubscription(Subscription $subscription): bool
    {
        return $this->updateSubscriptionStatus($subscription, 'suspend', [
            'reason' => 'Suspended by user'
        ]);
    }

    public function resumeSubscription(Subscription $subscription): bool
    {
        return $this->updateSubscriptionStatus($subscription, 'activate', [
            'reason' => 'Reactivating the subscription'
        ]);
    }

    /**
     * Generic method to update subscription status
     */
    private function updateSubscriptionStatus(Subscription $subscription, string $action, array $data = []): bool
    {
        try {
            if (!$subscription->subscription_id) {
                Log::error("Cannot {$action} PayPal subscription: missing subscription_id", [
                    'subscription_record_id' => $subscription->id
                ]);
                return false;
            }

            $response = Http::withToken($this->getAccessToken())
                ->timeout(30)
                ->post($this->baseUrl . "/v1/billing/subscriptions/{$subscription->subscription_id}/{$action}", $data);

            if ($response->successful()) {
                $this->handleSubscriptionStatusChange($subscription, $action);

                Log::info("PayPal subscription {$action} successful", [
                    'subscription_id' => $subscription->subscription_id,
                    'action' => $action
                ]);

                return true;
            }

            Log::error("PayPal subscription {$action} failed", [
                'subscription_id' => $subscription->subscription_id,
                'status' => $response->status(),
                'response' => $response->json()
            ]);

            return false;

        } catch (\Exception $e) {
            Log::error("Failed to {$action} PayPal subscription", [
                'subscription_id' => $subscription->subscription_id,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Handle subscription status changes
     */
    private function handleSubscriptionStatusChange(Subscription $subscription, string $action): void
    {
        match ($action) {
            'cancel' => $subscription->cancel(),
            'suspend' => $subscription->suspend(),
            'activate' => $subscription->resume(),
            default => null,
        };
    }

    // ========================================
    // PAYMENT CAPTURE
    // ========================================

    /**
     * Capture one-time payment with comprehensive error handling
     */
    public function capturePayment(string $orderId): array
    {
        try {
            $response = Http::withToken($this->getAccessToken())
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'PayPal-Request-Id' => Str::uuid()->toString(),
                ])
                ->timeout(30)
                ->post($this->baseUrl . "/v2/checkout/orders/{$orderId}/capture", (object)[]);

            if (!$response->successful()) {
                throw new PaymentException('PayPal payment capture failed: ' . $response->body());
            }

            $capturedOrder = $response->json();
            $subscription = Subscription::where('subscription_id', $orderId)->first();

            if ($subscription) {
                $subscription->update([
                    'status' => Subscription::STATUS_ACTIVE,
                    'is_active' => true,
                    'metadata' => array_merge($subscription->metadata ?? [], $capturedOrder),
                ]);

                $subscription->activate($capturedOrder);

                Log::info('PayPal payment captured and subscription activated', [
                    'order_id' => $orderId,
                    'subscription_id' => $subscription->id
                ]);
            }

            return [
                'success' => true,
                'order' => $capturedOrder,
                'subscription' => $subscription,
            ];

        } catch (PaymentException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('PayPal payment capture failed', [
                'order_id' => $orderId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    // ========================================
    // WEBHOOK HANDLING
    // ========================================

    /**
     * Handle webhook events with comprehensive error handling
     */
    public function handleWebhook(array $event): void
    {
        try {
            $eventType = $event['event_type'] ?? null;
            $resource = $event['resource'] ?? [];

            Log::info('Processing PayPal webhook', [
                'event_type' => $eventType,
                'resource_id' => $resource['id'] ?? 'unknown'
            ]);

            match ($eventType) {
                'BILLING.SUBSCRIPTION.CREATED' => $this->handleSubscriptionCreated($resource),
                'BILLING.SUBSCRIPTION.ACTIVATED' => $this->handleSubscriptionActivated($resource),
                'BILLING.SUBSCRIPTION.CANCELLED' => $this->handleSubscriptionCancelled($resource),
                'BILLING.SUBSCRIPTION.SUSPENDED' => $this->handleSubscriptionSuspended($resource),
                'PAYMENT.SALE.COMPLETED' => $this->handlePaymentCompleted($resource),
                'BILLING.SUBSCRIPTION.PAYMENT.FAILED' => $this->handlePaymentFailed($resource),
                default => Log::info('Unhandled PayPal webhook event', ['type' => $eventType])
            };

        } catch (\Exception $e) {
            Log::error('PayPal webhook handling failed', [
                'event_type' => $event['event_type'] ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    // ========================================
    // WEBHOOK EVENT HANDLERS
    // ========================================

    private function handleSubscriptionCreated(array $subscription): void
    {
        $subscriptionRecord = Subscription::where('subscription_id', $subscription['id'])->first();

        if ($subscriptionRecord) {
            $subscriptionRecord->update([
                'status' => strtolower($subscription['status'] ?? $subscriptionRecord->status),
                'metadata' => array_merge($subscriptionRecord->metadata ?? [], $subscription),
            ]);

            Log::info('PayPal subscription created webhook processed', [
                'subscription_id' => $subscription['id']
            ]);
        }
    }

    private function handleSubscriptionActivated(array $subscription): void
    {
        $subscriptionRecord = Subscription::where('subscription_id', $subscription['id'])->first();

        if (!$subscriptionRecord) {
            Log::warning('Subscription not found for PayPal webhook', [
                'subscription_id' => $subscription['id'],
                'event_type' => 'BILLING.SUBSCRIPTION.ACTIVATED'
            ]);
            return;
        }

        $updateData = [
            'status' => Subscription::STATUS_ACTIVE,
            'is_active' => true,
            'metadata' => array_merge($subscriptionRecord->metadata ?? [], $subscription),
        ];

        // Extract next billing date if available
        if (isset($subscription['billing_info']['next_billing_time'])) {
            $updateData['next_billing_date'] = Carbon::parse($subscription['billing_info']['next_billing_time']);
        }

        $subscriptionRecord->update($updateData);
        $subscriptionRecord->activate($subscription);

        Log::info('PayPal subscription activated webhook processed', [
            'subscription_id' => $subscription['id']
        ]);
    }

    private function handleSubscriptionCancelled(array $subscription): void
    {
        $subscriptionRecord = Subscription::where('subscription_id', $subscription['id'])->first();

        if (!$subscriptionRecord) {
            Log::warning('Subscription not found for PayPal webhook', [
                'subscription_id' => $subscription['id'],
                'event_type' => 'BILLING.SUBSCRIPTION.CANCELLED'
            ]);
            return;
        }

        $subscriptionRecord->update([
            'status' => Subscription::STATUS_CANCELED,
            'is_active' => false,
            'metadata' => array_merge($subscriptionRecord->metadata ?? [], $subscription),
        ]);

        $subscriptionRecord->cancel();

        Log::info('PayPal subscription cancelled webhook processed', [
            'subscription_id' => $subscription['id']
        ]);
    }

    private function handleSubscriptionSuspended(array $subscription): void
    {
        $subscriptionRecord = Subscription::where('subscription_id', $subscription['id'])->first();

        if (!$subscriptionRecord) {
            Log::warning('Subscription not found for PayPal webhook', [
                'subscription_id' => $subscription['id'],
                'event_type' => 'BILLING.SUBSCRIPTION.SUSPENDED'
            ]);
            return;
        }

        $subscriptionRecord->update([
            'status' => Subscription::STATUS_SUSPENDED,
            'is_active' => true,
            'is_suspended' => true,
            'metadata' => array_merge($subscriptionRecord->metadata ?? [], $subscription),
        ]);

        $subscriptionRecord->suspend();

        Log::info('PayPal subscription suspended webhook processed', [
            'subscription_id' => $subscription['id']
        ]);
    }

    private function handlePaymentCompleted(array $payment): void
    {
        if (isset($payment['billing_agreement_id'])) {
            $subscription = Subscription::where('subscription_id', $payment['billing_agreement_id'])->first();

            if ($subscription) {
                $subscription->employer->notify(new PaymentSuccessful($subscription, [
                    'amount' => $payment['amount']['total'],
                    'currency' => strtoupper($payment['amount']['currency']),
                    'payment_id' => $payment['id'],
                ]));

                if ($subscription->isInTrial()) {
                    $subscription->endTrial();
                }

                Log::info('PayPal payment completed webhook processed', [
                    'payment_id' => $payment['id'],
                    'subscription_id' => $payment['billing_agreement_id']
                ]);
            }
        }
    }

    private function handlePaymentFailed(array $payment): void
    {
        if (isset($payment['billing_agreement_id'])) {
            $subscription = Subscription::where('subscription_id', $payment['billing_agreement_id'])->first();

            if ($subscription) {
                $subscription->markPaymentFailed();

                $subscription->employer->notify(new PaymentFailed($subscription, [
                    'amount' => $payment['amount']['total'] ?? 0,
                    'currency' => strtoupper($payment['amount']['currency'] ?? 'USD'),
                    'payment_id' => $payment['id'],
                    'failure_reason' => 'Payment failed',
                ]));

                Log::warning('PayPal payment failed webhook processed', [
                    'payment_id' => $payment['id'],
                    'subscription_id' => $payment['billing_agreement_id']
                ]);
            }
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
        array $paymentData,
        string $paymentType,
        bool $isInTrial = false
    ): Subscription {
        $trialStart = $isInTrial ? now() : null;
        $trialEnd = $isInTrial ? now()->addDays($plan->getTrialPeriodDays()) : null;

        return Subscription::create([
            'employer_id' => $employer->id,
            'plan_id' => $plan->id,
            'subscription_id' => $paymentData['id'],
            'payment_provider' => Subscription::PROVIDER_PAYPAL,
            'status' => strtolower($paymentData['status'] ?? Subscription::STATUS_PENDING),
            'amount' => $plan->price,
            'currency' => $plan->getCurrencyCode(),
            'start_date' => now(),
            'end_date' => $plan->isOneTime() ? now()->addDays($plan->duration_days ?? 30) : null,
            'next_billing_date' => $this->getNextBillingDate($plan, $isInTrial),
            'trial_start_date' => $trialStart,
            'trial_end_date' => $trialEnd,
            'is_trial' => $isInTrial,
            'trial_ended' => false,
            'cv_downloads_left' => $plan->resume_views_limit,
            'metadata' => array_merge($paymentData, ['payment_type' => $paymentType]),
            'is_active' => false, // Will be activated after approval
        ]);
    }

    /**
     * Calculate next billing date
     */
    private function getNextBillingDate(Plan $plan, bool $isInTrial = false): ?Carbon
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
     * Extract approval URL from PayPal links
     */
    private function extractApprovalUrl(array $links): ?string
    {
        foreach ($links as $link) {
            if ($link['rel'] === 'approve') {
                return $link['href'];
            }
        }

        // Fallback to index 1 if approve link not found
        return $links[1]['href'] ?? null;
    }

    /**
     * Get return URLs for PayPal
     */
    private function getReturnUrl(string $status): string
    {
        return config('app.frontend_url') . "/employer/dashboard?payment_status={$status}&payment_provider=paypal";
    }
}
