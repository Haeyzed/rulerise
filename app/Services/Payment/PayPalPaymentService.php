<?php

namespace App\Services\Payment;

use App\Models\Employer;
use App\Models\Plan;
use App\Models\Payment;
use App\Models\Subscription;
use App\Notifications\PaymentFailed;
use App\Notifications\PaymentSuccessful;
use App\Notifications\TrialEnding;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Illuminate\Support\Str;

class PayPalPaymentService
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

    /**
     * Get PayPal access token
     */
    private function getAccessToken(): string
    {
        if ($this->accessToken) {
            return $this->accessToken;
        }

        $response = Http::withBasicAuth($this->clientId, $this->clientSecret)
            ->asForm()
            ->post($this->baseUrl . '/v1/oauth2/token', [
                'grant_type' => 'client_credentials'
            ]);

        if (!$response->successful()) {
            Log::error('PayPal access token error', ['response' => $response->json()]);
            throw new \Exception('Failed to get PayPal access token');
        }

        $this->accessToken = $response->json('access_token');
        return $this->accessToken;
    }

    /**
     * Create PayPal product (required before creating plan)
     */
    public function createProduct(Plan $plan): string
    {
        $response = Http::withToken($this->getAccessToken())
            ->withHeaders(['PayPal-Request-Id' => Str::uuid()->toString()])
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
                'response' => $response->json()
            ]);
            throw new \Exception('Failed to create PayPal product: ' . $response->body());
        }

        $productId = $response->json('id');

        // Update plan with product ID
        $plan->update(['paypal_product_id' => $productId]);

        return $productId;
    }

    /**
     * Create PayPal billing plan with trial support
     */
    public function createPlan(Plan $plan): array
    {
        try {
            // Ensure product exists
            if (!$plan->paypal_product_id) {
                $productId = $this->createProduct($plan);
            } else {
                $productId = $plan->paypal_product_id;
            }

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
                ->post($this->baseUrl . '/v1/billing/plans', $planData);

            if (!$response->successful()) {
                Log::error('PayPal plan creation failed', [
                    'plan_id' => $plan->id,
                    'response' => $response->json()
                ]);
                throw new \Exception('Failed to create PayPal plan: ' . $response->body());
            }

            $paypalPlanId = $response->json('id');

            // Update plan with PayPal plan ID
            $plan->update(['paypal_plan_id' => $paypalPlanId]);

            return [
                'success' => true,
                'plan' => $response->json()
            ];
        } catch (\Exception $e) {
            Log::error('PayPal plan creation failed', [
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
     * Build billing cycles for PayPal plan
     */
    private function buildBillingCycles(Plan $plan): array
    {
        $cycles = [];
        $sequence = 1;

        // Add trial cycle if plan has trial
        if ($plan->hasTrial()) {
            $cycles[] = [
                'frequency' => [
                    'interval_unit' => strtoupper($plan->billing_cycle === 'yearly' ? 'YEAR' : 'MONTH'),
                    'interval_count' => 1
                ],
                'tenure_type' => 'TRIAL',
                'sequence' => $sequence++,
                'total_cycles' => 1, // Trial runs for 1 cycle
                'pricing_scheme' => [
                    'fixed_price' => [
                        'value' => '0.00', // Free trial
                        'currency_code' => $plan->getCurrencyCode()
                    ]
                ]
            ];
        }

        // Add regular billing cycle
        $totalCycles = $plan->billing_cycle === 'yearly' ? 50 : 120; // Limit cycles for safety

        $cycles[] = [
            'frequency' => [
                'interval_unit' => strtoupper($plan->billing_cycle === 'yearly' ? 'YEAR' : 'MONTH'),
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

    /**
     * Create one-time payment
     */
    public function createOneTimePayment(Employer $employer, Plan $plan): array
    {
        try {
            $accessToken = $this->getAccessToken();

            $response = Http::withToken($accessToken)
                ->withHeaders(['PayPal-Request-Id' => Str::uuid()->toString()])
                ->post($this->baseUrl . '/v2/checkout/orders', [
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
                                'return_url' => config('app.frontend_url') . '/employer/dashboard',
                                'cancel_url' => config('app.frontend_url') . '/employer/dashboard',
                            ]
                        ]
                    ]
                ]);

            if (!$response->successful()) {
                throw new \Exception('PayPal order creation failed: ' . $response->body());
            }

            $order = $response->json();

            // Create payment record
//            $payment = Payment::create([
//                'employer_id' => $employer->id,
//                'plan_id' => $plan->id,
//                'payment_id' => $order['id'],
//                'payment_provider' => 'paypal',
//                'payment_type' => 'one_time',
//                'status' => 'pending',
//                'amount' => $plan->price,
//                'currency' => $plan->getCurrencyCode(),
//                'provider_response' => $order,
//            ]);


            // PayPal will handle trial through billing cycles, so we determine trial status from plan
            $isInTrial = $plan->hasTrial();
            $trialStart = $isInTrial ? now() : null;
            $trialEnd = $isInTrial ? now()->addDays($plan->getTrialPeriodDays()) : null;

            // Create subscription record
            $payment = Subscription::query()->create([
                'employer_id' => $employer->id,
                'plan_id' => $plan->id,
                'subscription_id' => $order['id'],
                'payment_provider' => 'paypal',
                'status' => 'pending',
                'amount' => $plan->price,
                'currency' => $plan->getCurrencyCode(),
                'start_date' => now(),
                'next_billing_date' => null,
                'trial_start_date' => $trialStart,
                'trial_end_date' => $trialEnd,
                'is_trial' => $isInTrial,
                'trial_ended' => false,
                'cv_downloads_left' => $plan->resume_views_limit,
                'metadata' => $order,
                'is_active' => false, // Will be activated after approval
            ]);

            $approvalUrl = $order['links'][1]['href'] ?? null;

            return [
                'success' => true,
                'order_id' => $order['id'],
                'approval_url' => $approvalUrl,
                'payment' => $payment,
            ];
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
     * Create recurring subscription (PayPal handles trial through billing cycles)
     */
    public function createSubscription(Employer $employer, Plan $plan): array
    {
        try {
            // Ensure PayPal plan exists
            if (!$plan->paypal_plan_id) {
                $result = $this->createPlan($plan);
                if (!$result['success']) {
                    throw new \Exception('Failed to create PayPal plan: ' . $result['error']);
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
                    'return_url' => config('app.frontend_url') . '/employer/dashboard',
                    'cancel_url' => config('app.frontend_url') . '/employer/dashboard',
                ],
                'custom_id' => "employer_{$employer->id}_plan_{$plan->id}",
            ];

            $response = Http::withToken($this->getAccessToken())
                ->withHeaders([
                    'PayPal-Request-Id' => 'SUBSCRIPTION-' . Str::uuid()->toString(),
                    'Prefer' => 'return=representation'
                ])
                ->post($this->baseUrl . '/v1/billing/subscriptions', $subscriptionData);

            if (!$response->successful()) {
                throw new \Exception('PayPal subscription creation failed: ' . $response->body());
            }

            $subscription = $response->json();

            // PayPal will handle trial through billing cycles, so we determine trial status from plan
            $isInTrial = $plan->hasTrial();
            $trialStart = $isInTrial ? now() : null;
            $trialEnd = $isInTrial ? now()->addDays($plan->getTrialPeriodDays()) : null;

            // Create subscription record
            $subscriptionRecord = Subscription::query()->create([
                'employer_id' => $employer->id,
                'plan_id' => $plan->id,
                'subscription_id' => $subscription['id'],
                'payment_provider' => 'paypal',
                'status' => strtolower($subscription['status']),
                'amount' => $plan->price,
                'currency' => $plan->getCurrencyCode(),
                'start_date' => now(),
                'next_billing_date' => $this->getNextBillingDate($plan, $isInTrial),
                'trial_start_date' => $trialStart,
                'trial_end_date' => $trialEnd,
                'is_trial' => $isInTrial,
                'trial_ended' => false,
                'cv_downloads_left' => $plan->resume_views_limit,
                'metadata' => $subscription,
                'is_active' => false, // Will be activated after approval
            ]);

            $approvalUrl = collect($subscription['links'])->firstWhere('rel', 'approve')['href'] ?? null;

            return [
                'success' => true,
                'subscription_id' => $subscription['id'],
                'approval_url' => $approvalUrl,
                'subscription' => $subscriptionRecord,
                'is_trial' => $isInTrial,
                'trial_end_date' => $trialEnd,
            ];
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

    /**
     * Cancel subscription
     */
    public function cancelSubscription(Subscription $subscription): bool
    {
        try {
            $response = Http::withToken($this->getAccessToken())
                ->post($this->baseUrl . "/v1/billing/subscriptions/{$subscription->subscription_id}/cancel", [
                    'reason' => 'User requested cancellation'
                ]);

            if ($response->successful()) {
                $subscription->cancel();
                return true;
            }

            return false;
        } catch (\Exception $e) {
            Log::error('Failed to cancel PayPal subscription', [
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
            $response = Http::withToken($this->getAccessToken())
                ->post($this->baseUrl . "/v1/billing/subscriptions/{$subscription->subscription_id}/suspend", [
                    'reason' => 'Suspended by user'
                ]);

            if ($response->successful()) {
                $subscription->suspend();
                return true;
            }

            return false;
        } catch (\Exception $e) {
            Log::error('Failed to suspend PayPal subscription', [
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
            $response = Http::withToken($this->getAccessToken())
                ->post($this->baseUrl . "/v1/billing/subscriptions/{$subscription->subscription_id}/activate", [
                    'reason' => 'Reactivating the subscription'
                ]);

            if ($response->successful()) {
                $subscription->resume();
                return true;
            }

            return false;
        } catch (\Exception $e) {
            Log::error('Failed to resume PayPal subscription', [
                'subscription_id' => $subscription->subscription_id,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Capture one-time payment
     */
    public function capturePayment(string $orderId): array
    {
        try {
            $response = Http::withToken($this->getAccessToken())
                ->withHeaders([
                    'Content-Type' => 'application/json',
                     'PayPal-Request-Id' => Str::uuid()->toString(), // Optional: Idempotency
                ])
                ->post($this->baseUrl . "/v2/checkout/orders/{$orderId}/capture", (object)[]);

            if (!$response->successful()) {
                throw new \Exception('PayPal payment capture failed: ' . $response->body());
            }

            $capturedOrder = $response->json();

            // Update payment record
//            $payment = Payment::where('payment_id', $orderId)->first();
//            if ($payment) {
//                $payment->update([
//                    'status' => 'completed',
//                    'paid_at' => now(),
//                    'provider_response' => $capturedOrder,
//                ]);
//            }

            $payment = Subscription::where('subscription_id', $orderId)->first();
            if ($payment) {
                $payment->update([
                    'status' => strtolower($capturedOrder['status']),
                    'is_active' => true,
                    'metadata' => $capturedOrder,
                ]);
            }

            return [
                'success' => true,
                'order' => $capturedOrder,
                'payment' => $payment,
            ];
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

    /**
     * Get subscription details
     */
    public function getSubscriptionDetails(string $subscriptionId): array
    {
        try {
            $response = Http::withToken($this->getAccessToken())
                ->get($this->baseUrl . "/v1/billing/subscriptions/{$subscriptionId}");

            if (!$response->successful()) {
                throw new \Exception('Failed to get PayPal subscription details: ' . $response->body());
            }

            return [
                'success' => true,
                'subscription' => $response->json()
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get PayPal subscription details', [
                'subscription_id' => $subscriptionId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Handle webhook events
     */
    public function handleWebhook(array $event): void
    {
        try {
            switch ($event['event_type']) {
                case 'BILLING.SUBSCRIPTION.CREATED':
                    $this->handleSubscriptionCreated($event['resource']);
                    break;

                case 'BILLING.SUBSCRIPTION.ACTIVATED':
                    $this->handleSubscriptionActivated($event['resource']);
                    break;

                case 'BILLING.SUBSCRIPTION.CANCELLED':
                    $this->handleSubscriptionCancelled($event['resource']);
                    break;

                case 'BILLING.SUBSCRIPTION.SUSPENDED':
                    $this->handleSubscriptionSuspended($event['resource']);
                    break;

                case 'PAYMENT.SALE.COMPLETED':
                    $this->handlePaymentCompleted($event['resource']);
                    break;

                case 'BILLING.SUBSCRIPTION.PAYMENT.FAILED':
                    $this->handlePaymentFailed($event['resource']);
                    break;
            }
        } catch (\Exception $e) {
            Log::error('PayPal webhook handling failed', [
                'event_type' => $event['event_type'],
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Verify webhook signature
     */
    public function verifyWebhookSignature(string $payload, array $headers): bool
    {
        // Skip verification in development
        if (config('app.env') === 'local') {
            return true;
        }

        $normalizedHeaders = array_change_key_case($headers, CASE_LOWER);

        $requiredHeaders = [
            'paypal-transmission-id',
            'paypal-transmission-time',
            'paypal-cert-url',
            'paypal-auth-algo',
            'paypal-transmission-sig'
        ];

        foreach ($requiredHeaders as $header) {
            if (empty($normalizedHeaders[$header])) {
                Log::error('Missing PayPal webhook header', ['header' => $header]);
                return false;
            }
        }

        try {
            $response = Http::withToken($this->getAccessToken())
                ->post($this->baseUrl . "/v1/notifications/verify-webhook-signature", [
                    'webhook_id' => $this->webhookId,
                    'transmission_id' => $normalizedHeaders['paypal-transmission-id'],
                    'transmission_time' => $normalizedHeaders['paypal-transmission-time'],
                    'transmission_sig' => $normalizedHeaders['paypal-transmission-sig'],
                    'cert_url' => $normalizedHeaders['paypal-cert-url'],
                    'auth_algo' => $normalizedHeaders['paypal-auth-algo'],
                    'webhook_event' => json_decode($payload, true)
                ]);

            return $response->successful() &&
                $response->json('verification_status') === 'SUCCESS';
        } catch (\Exception $e) {
            Log::error('PayPal webhook signature verification failed', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    private function handleSubscriptionCreated(array $subscription): void
    {
        $subscriptionRecord = Subscription::where('subscription_id', $subscription['id'])->first();

        if ($subscriptionRecord) {
            $subscriptionRecord->update([
                'status' => strtolower($subscription['status'] ?? $subscriptionRecord->status),
                'metadata' => array_merge($subscriptionRecord->metadata ?? [], $subscription),
            ]);
        }
    }

    private function handleSubscriptionActivated(array $subscription): void
    {
        $subscriptionRecord = Subscription::where('subscription_id', $subscription['id'])->first();

        if ($subscriptionRecord) {
            $updateData = [
                'status' => 'active',
                'is_active' => true,
                'metadata' => array_merge($subscriptionRecord->metadata ?? [], $subscription),
            ];

            // Extract and save next billing date if available
            if (isset($subscription['billing_info']['next_billing_time'])) {
                $updateData['next_billing_date'] = Carbon::parse($subscription['billing_info']['next_billing_time']);
            }

            $subscriptionRecord->update($updateData);

            // Activate the subscription (this will send the notification)
            $subscriptionRecord->activate();
        }
    }

    private function handleSubscriptionCancelled(array $subscription): void
    {
        $subscriptionRecord = Subscription::where('subscription_id', $subscription['id'])->first();

        if ($subscriptionRecord) {
            $updateData = [
                'status' => 'canceled',
                'is_active' => false,
                'metadata' => array_merge($subscriptionRecord->metadata ?? [], $subscription),
            ];

            $subscriptionRecord->update($updateData);

            // Activate the subscription (this will send the notification)
            $subscriptionRecord->cancel();
        }
    }

    private function handleSubscriptionSuspended(array $subscription): void
    {
        $subscriptionRecord = Subscription::where('subscription_id', $subscription['id'])->first();

        if ($subscriptionRecord) {
            $updateData = [
                'status' => 'suspended',
                'is_active' => false,
//                'is_suspended' => false,
                'metadata' => array_merge($subscriptionRecord->metadata ?? [], $subscription),
            ];

            $subscriptionRecord->update($updateData);

            // Activate the subscription (this will send the notification)
            $subscriptionRecord->suspend();
        }
    }

    private function handlePaymentCompleted(array $payment): void
    {
        // Handle recurring payment completion
        if (isset($payment['billing_agreement_id'])) {
            $subscription = Subscription::where('subscription_id', $payment['billing_agreement_id'])->first();

            if ($subscription) {
                $paymentRecord = Payment::create([
                    'employer_id' => $subscription->employer_id,
                    'plan_id' => $subscription->plan_id,
                    'subscription_id' => $subscription->id,
                    'payment_id' => $payment['id'],
                    'payment_provider' => 'paypal',
                    'payment_type' => 'recurring',
                    'status' => 'completed',
                    'amount' => $payment['amount']['total'],
                    'currency' => strtoupper($payment['amount']['currency']),
                    'paid_at' => Carbon::parse($payment['create_time']),
                    'provider_response' => $payment,
                ]);

                // Send payment successful notification
                $subscription->employer->notify(new PaymentSuccessful($paymentRecord));

                // If this is the first payment after trial, end the trial
                if ($subscription->isInTrial()) {
                    $subscription->endTrial();
                }
            }
        }
    }

    private function handlePaymentFailed(array $payment): void
    {
        if (isset($payment['id'])) {
            Log::warning('PayPal subscription payment failed', [
                'payment' => $payment
            ]);

            // Update subscription status if available
            if (isset($payment['billing_agreement_id'])) {
                $subscription = Subscription::where('subscription_id', $payment['billing_agreement_id'])->first();

                if ($subscription) {
                    $subscription->update([
                        'status' => 'payment_failed',
                    ]);

                    // Create a failed payment record
                    $paymentRecord = Payment::create([
                        'employer_id' => $subscription->employer_id,
                        'plan_id' => $subscription->plan_id,
                        'subscription_id' => $subscription->id,
                        'payment_id' => $payment['id'],
                        'payment_provider' => 'paypal',
                        'payment_type' => 'recurring',
                        'status' => 'failed',
                        'amount' => $payment['amount']['total'] ?? 0,
                        'currency' => strtoupper($payment['amount']['currency'] ?? 'USD'),
                        'provider_response' => $payment,
                    ]);

                    // Send payment failed notification
                    $subscription->employer->notify(new PaymentFailed($paymentRecord));
                }
            }
        }
    }

    private function getNextBillingDate(Plan $plan, bool $isInTrial = false): Carbon
    {
        $startDate = $isInTrial ? now()->addDays($plan->getTrialPeriodDays()) : now();

        return match ($plan->billing_cycle) {
            'monthly' => $startDate->copy()->addMonth(),
            'yearly' => $startDate->copy()->addYear(),
            default => $startDate,
        };
    }
}
