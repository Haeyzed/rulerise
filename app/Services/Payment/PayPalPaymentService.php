<?php

namespace App\Services\Payment;

use App\Models\Employer;
use App\Models\Plan;
use App\Models\Payment;
use App\Models\Subscription;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class PayPalPaymentService
{
    private string $baseUrl;
    private string $clientId;
    private string $clientSecret;

    public function __construct()
    {
        $this->baseUrl = config('services.paypal.mode') === 'live'
            ? 'https://api.paypal.com'
            : 'https://api.sandbox.paypal.com';
        $this->clientId = config('services.paypal.client_id');
        $this->clientSecret = config('services.paypal.client_secret');
    }

    /**
     * Get PayPal access token
     */
    private function getAccessToken(): string
    {
        $response = Http::withBasicAuth($this->clientId, $this->clientSecret)
            ->asForm()
            ->post($this->baseUrl . '/v1/oauth2/token', [
                'grant_type' => 'client_credentials'
            ]);

        if (!$response->successful()) {
            throw new \Exception('Failed to get PayPal access token');
        }

        return $response->json()['access_token'];
    }

    /**
     * Create PayPal product (required before creating plan)
     */
    public function createProduct(Plan $plan): string
    {
        $response = Http::withToken($this->getAccessToken())
            ->post($this->baseUrl . '/v1/catalogs/products', [
                'name' => $plan->name,
                'description' => $plan->description ?? $plan->name,
                'type' => 'SERVICE',
                'category' => 'SOFTWARE',
            ]);

        if (!$response->successful()) {
            Log::error('PayPal create product error', [
                'plan' => $plan->toArray(),
                'response' => $response->json()
            ]);
            throw new \Exception('Failed to create PayPal product');
        }

        return $response->json('id');
    }

    /**
     * Create PayPal billing plan
     */
    public function createPlan(Plan $plan): string
    {
        $productId = $this->createProduct($plan);

        $billingCycles = [];

        // Add regular billing cycle
        $billingCycles[] = [
            'frequency' => [
                'interval_unit' => $plan->billing_cycle === 'yearly' ? 'YEAR' : 'MONTH',
                'interval_count' => 1
            ],
            'tenure_type' => 'REGULAR',
            'sequence' => 1,
            'total_cycles' => 0, // Infinite
            'pricing_scheme' => [
                'fixed_price' => [
                    'value' => (string)$plan->price,
                    'currency_code' => $plan->currency
                ]
            ]
        ];

        $response = Http::withToken($this->getAccessToken())
            ->post($this->baseUrl . '/v1/billing/plans', [
                'product_id' => $productId,
                'name' => $plan->name,
                'description' => $plan->description ?? $plan->name,
                'status' => 'ACTIVE',
                'billing_cycles' => $billingCycles,
                'payment_preferences' => [
                    'auto_bill_outstanding' => true,
                    'setup_fee_failure_action' => 'CONTINUE',
                    'payment_failure_threshold' => 3
                ]
            ]);

        if (!$response->successful()) {
            Log::error('PayPal create plan error', [
                'plan' => $plan->toArray(),
                'response' => $response->json()
            ]);
            throw new \Exception('Failed to create PayPal plan');
        }

        return $response->json('id');
    }

    /**
     * Create one-time payment
     */
    public function createOneTimePayment(Employer $employer, Plan $plan): array
    {
        try {
            $accessToken = $this->getAccessToken();

            $response = Http::withToken($accessToken)
                ->post($this->baseUrl . '/v2/checkout/orders', [
                    'intent' => 'CAPTURE',
                    'purchase_units' => [
                        [
                            'amount' => [
                                'currency_code' => $plan->currency,
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
                                'return_url' => route('paypal.success'),
                                'cancel_url' => route('paypal.cancel'),
                            ]
                        ]
                    ]
                ]);

            if (!$response->successful()) {
                throw new \Exception('PayPal order creation failed: ' . $response->body());
            }

            $order = $response->json();

            // Create payment record
            $payment = Payment::create([
                'employer_id' => $employer->id,
                'plan_id' => $plan->id,
                'payment_id' => $order['id'],
                'payment_provider' => 'paypal',
                'payment_type' => 'one_time',
                'status' => 'pending',
                'amount' => $plan->price,
                'currency' => $plan->currency,
                'provider_response' => $order,
            ]);

            $approvalUrl = collect($order['links'])->firstWhere('rel', 'approve')['href'] ?? null;

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
     * Create recurring subscription
     */
    public function createSubscription(Employer $employer, Plan $plan): array
    {
        try {
            // Create or get existing plan
            $externalPlanId = $plan->paypal_plan_id ?? $this->createPlan($plan);

            if (!$plan->paypal_plan_id) {
                $plan->update(['paypal_plan_id' => $externalPlanId]);
            }

            $accessToken = $this->getAccessToken();

            $response = Http::withToken($accessToken)
                ->post($this->baseUrl . '/v1/billing/subscriptions', [
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
                        'return_url' => route('paypal.subscription.success'),
                        'cancel_url' => route('paypal.subscription.cancel'),
                    ],
                    'custom_id' => "employer_{$employer->id}_plan_{$plan->id}",
                ]);

            if (!$response->successful()) {
                throw new \Exception('PayPal subscription creation failed: ' . $response->body());
            }

            $subscription = $response->json();

            // Create subscription record
            $subscriptionRecord = Subscription::create([
                'employer_id' => $employer->id,
                'plan_id' => $plan->id,
                'subscription_id' => $subscription['id'],
                'payment_provider' => 'paypal',
                'status' => strtolower($subscription['status']),
                'amount' => $plan->price,
                'currency' => $plan->currency,
                'start_date' => now(),
                'next_billing_date' => $this->getNextBillingDate($plan->billing_cycle),
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
            $accessToken = $this->getAccessToken();

            $response = Http::withToken($accessToken)
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
            $accessToken = $this->getAccessToken();

            $response = Http::withToken($accessToken)
                ->post($this->baseUrl . "/v1/billing/subscriptions/{$subscription->subscription_id}/suspend", [
                    'reason' => 'User requested suspension'
                ]);

            if ($response->successful()) {
                $subscription->update([
                    'status' => 'suspended',
                    'is_active' => false,
                ]);
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
            $accessToken = $this->getAccessToken();

            $response = Http::withToken($accessToken)
                ->post($this->baseUrl . "/v1/billing/subscriptions/{$subscription->subscription_id}/activate", [
                    'reason' => 'User requested reactivation'
                ]);

            if ($response->successful()) {
                $subscription->update([
                    'status' => 'active',
                    'is_active' => true,
                ]);
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
            $accessToken = $this->getAccessToken();

            $response = Http::withToken($accessToken)
                ->post($this->baseUrl . "/v2/checkout/orders/{$orderId}/capture");

            if (!$response->successful()) {
                throw new \Exception('PayPal payment capture failed: ' . $response->body());
            }

            $capturedOrder = $response->json();

            // Update payment record
            $payment = Payment::where('payment_id', $orderId)->first();
            if ($payment) {
                $payment->update([
                    'status' => 'completed',
                    'paid_at' => now(),
                    'provider_response' => $capturedOrder,
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
     * Handle webhook events
     */
    public function handleWebhook(array $event): void
    {
        try {
            switch ($event['event_type']) {
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
            }
        } catch (\Exception $e) {
            Log::error('PayPal webhook handling failed', [
                'event_type' => $event['event_type'],
                'error' => $e->getMessage()
            ]);
        }
    }

    private function handleSubscriptionActivated(array $subscription): void
    {
        $subscriptionRecord = Subscription::where('subscription_id', $subscription['id'])->first();

        if ($subscriptionRecord) {
            $subscriptionRecord->update([
                'status' => 'active',
                'is_active' => true,
                'start_date' => now(),
                'metadata' => $subscription,
            ]);
        }
    }

    private function handleSubscriptionCancelled(array $subscription): void
    {
        $subscriptionRecord = Subscription::where('subscription_id', $subscription['id'])->first();

        if ($subscriptionRecord) {
            $subscriptionRecord->cancel();
        }
    }

    private function handleSubscriptionSuspended(array $subscription): void
    {
        $subscriptionRecord = Subscription::where('subscription_id', $subscription['id'])->first();

        if ($subscriptionRecord) {
            $subscriptionRecord->update([
                'status' => 'suspended',
                'is_active' => false,
                'metadata' => $subscription,
            ]);
        }
    }

    private function handlePaymentCompleted(array $payment): void
    {
        // Handle recurring payment completion
        if (isset($payment['billing_agreement_id'])) {
            $subscription = Subscription::where('subscription_id', $payment['billing_agreement_id'])->first();

            if ($subscription) {
                Payment::create([
                    'employer_id' => $subscription->employer_id,
                    'plan_id' => $subscription->plan_id,
                    'subscription_id' => $subscription->id,
                    'payment_id' => $payment['id'],
                    'payment_provider' => 'paypal',
                    'payment_type' => 'recurring',
                    'status' => 'completed',
                    'amount' => $payment['amount']['total'],
                    'currency' => $payment['amount']['currency'],
                    'paid_at' => Carbon::parse($payment['create_time']),
                    'provider_response' => $payment,
                ]);
            }
        }
    }

    private function getNextBillingDate(string $billingCycle): Carbon
    {
        return match ($billingCycle) {
            'monthly' => now()->addMonth(),
            'yearly' => now()->addYear(),
            default => now(),
        };
    }
}
