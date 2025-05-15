<?php

namespace App\Services\Subscription;

use App\Models\Employer;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PayPalSubscriptionService implements SubscriptionServiceInterface
{
    protected $baseUrl;
    protected $clientId;
    protected $clientSecret;
    protected $accessToken;
    protected $webhookId;

    public function __construct()
    {
        $this->baseUrl = config('services.paypal.sandbox')
            ? 'https://api-m.sandbox.paypal.com'
            : 'https://api-m.paypal.com';
        $this->clientId = config('services.paypal.client_id');
        $this->clientSecret = config('services.paypal.client_secret');
        $this->webhookId = config('services.paypal.webhook_id');
    }

    /**
     * Get PayPal access token
     *
     * @return string
     */
    protected function getAccessToken(): string
    {
        if ($this->accessToken) {
            return $this->accessToken;
        }

        $response = Http::withBasicAuth($this->clientId, $this->clientSecret)
            ->asForm()
            ->post("{$this->baseUrl}/v1/oauth2/token", [
                'grant_type' => 'client_credentials'
            ]);

        if ($response->successful()) {
            $this->accessToken = $response->json('access_token');
            return $this->accessToken;
        }

        Log::error('PayPal access token error', [
            'response' => $response->json()
        ]);

        throw new \Exception('Failed to get PayPal access token');
    }

    /**
     * Create a product in PayPal
     *
     * @param SubscriptionPlan $plan
     * @return string Product ID
     */
    protected function createProduct(SubscriptionPlan $plan): string
    {
        $response = Http::withToken($this->getAccessToken())
            ->withHeaders([
                'PayPal-Request-Id' => Str::uuid()->toString(),
            ])
            ->post("{$this->baseUrl}/v1/catalogs/products", [
                'name' => $plan->name,
                'description' => $plan->description ?? $plan->name,
                'type' => 'SERVICE',
                'category' => 'SOFTWARE',
            ]);

        if ($response->successful()) {
            return $response->json('id');
        }

        Log::error('PayPal create product error', [
            'plan' => $plan->toArray(),
            'response' => $response->json()
        ]);

        throw new \Exception('Failed to create PayPal product');
    }

    /**
     * Create a subscription plan in PayPal
     *
     * @param SubscriptionPlan $plan
     * @return string External plan ID
     */
    public function createPlan(SubscriptionPlan $plan): string
    {
        $productId = $this->createProduct($plan);

        $response = Http::withToken($this->getAccessToken())
            ->withHeaders([
                'PayPal-Request-Id' => Str::uuid()->toString(),
            ])
            ->post("{$this->baseUrl}/v1/billing/plans", [
                'product_id' => $productId,
                'name' => $plan->name,
                'description' => $plan->description ?? $plan->name,
                'billing_cycles' => [
                    [
                        'frequency' => [
                            'interval_unit' => 'DAY',
                            'interval_count' => 7
                        ],
                        'tenure_type' => 'TRIAL',
                        'sequence' => 1,
                        'total_cycles' => 1,
                        'pricing_scheme' => [
                            'fixed_price' => [
                                'value' => '0',
                                'currency_code' => strtoupper($plan->currency)
                            ]
                        ]
                    ],
                    [
                        'frequency' => [
                            'interval_unit' => 'DAY',
                            'interval_count' => $plan->duration_days
                        ],
                        'tenure_type' => 'REGULAR',
                        'sequence' => 2,
                        'total_cycles' => 1,
                        'pricing_scheme' => [
                            'fixed_price' => [
                                'value' => (string) $plan->price,
                                'currency_code' => strtoupper($plan->currency)
                            ]
                        ]
                    ]
                ],
                'payment_preferences' => [
                    'auto_bill_outstanding' => true,
                    'setup_fee_failure_action' => 'CONTINUE',
                    'payment_failure_threshold' => 3
                ]
            ]);

        if ($response->successful()) {
            return $response->json('id');
        }

        Log::error('PayPal create plan error', [
            'plan' => $plan->toArray(),
            'response' => $response->json()
        ]);

        throw new \Exception('Failed to create PayPal plan');
    }

    /**
     * Update a subscription plan in PayPal
     *
     * @param SubscriptionPlan $plan
     * @param string $externalPlanId
     * @return bool
     */
    public function updatePlan(SubscriptionPlan $plan, string $externalPlanId): bool
    {
        // PayPal doesn't allow updating core plan details, so we'll just update the status
        $response = Http::withToken($this->getAccessToken())
            ->patch("{$this->baseUrl}/v1/billing/plans/{$externalPlanId}", [
                [
                    'op' => 'replace',
                    'path' => '/description',
                    'value' => $plan->description ?? $plan->name
                ]
            ]);

        if ($response->successful()) {
            return true;
        }

        Log::error('PayPal update plan error', [
            'plan' => $plan->toArray(),
            'externalPlanId' => $externalPlanId,
            'response' => $response->json()
        ]);

        return false;
    }

    /**
     * Delete a subscription plan from PayPal
     *
     * @param string $externalPlanId
     * @return bool
     */
    public function deletePlan(string $externalPlanId): bool
    {
        // PayPal doesn't allow deleting plans, only deactivating them
        $response = Http::withToken($this->getAccessToken())
            ->post("{$this->baseUrl}/v1/billing/plans/{$externalPlanId}/deactivate");

        if ($response->successful()) {
            return true;
        }

        Log::error('PayPal deactivate plan error', [
            'externalPlanId' => $externalPlanId,
            'response' => $response->json()
        ]);

        return false;
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
        // Get or create the external plan ID
        $externalPlanId = $plan->external_paypal_id ?? $this->createPlan($plan);

        // If we created a new plan, save the ID
        if (!$plan->external_paypal_id) {
            $plan->external_paypal_id = $externalPlanId;
            $plan->save();
        }

        // Create the subscription
        $response = Http::withToken($this->getAccessToken())
            ->withHeaders([
                'PayPal-Request-Id' => Str::uuid()->toString(),
            ])
            ->post("{$this->baseUrl}/v1/billing/subscriptions", [
                'plan_id' => $externalPlanId,
                'start_time' => Carbon::now()->addMinutes(5)->toIso8601String(),
                'quantity' => '1',
                'subscriber' => [
                    'name' => [
                        'given_name' => $employer->user->first_name ?? $employer->company_name,
                        'surname' => $employer->user->last_name ?? ''
                    ],
                    'email_address' => $employer->user->email ?? $employer->company_email
                ],
                'application_context' => [
                    'brand_name' => config('app.name'),
                    'locale' => 'en-US',
                    'shipping_preference' => 'NO_SHIPPING',
                    'user_action' => 'SUBSCRIBE_NOW',
                    'payment_method' => [
                        'payer_selected' => 'PAYPAL',
                        'payee_preferred' => 'IMMEDIATE_PAYMENT_REQUIRED'
                    ],
                    'return_url' => config('app.frontend_url') . '/employer/dashboard',
                    'cancel_url' => config('app.frontend_url') . '/employer/dashboard',
//                    'return_url' => url('/api/subscription/paypal/success'),
//                    'cancel_url' => url('/api/subscription/paypal/cancel')
                ]
            ]);

        if ($response->successful()) {
            $data = $response->json();

            // Create a pending subscription record
            $subscription = new Subscription([
                'employer_id' => $employer->id,
                'subscription_plan_id' => $plan->id,
                'start_date' => Carbon::now(),
                'end_date' => Carbon::now()->addDays($plan->duration_days + 7), // Including trial
                'amount_paid' => $plan->price,
                'currency' => $plan->currency,
                'payment_method' => 'paypal',
                'subscription_id' => $data['id'],
                'job_posts_left' => $plan->job_posts_limit,
                'featured_jobs_left' => $plan->featured_jobs_limit,
                'cv_downloads_left' => $plan->resume_views_limit,
                'is_active' => false // Will be activated when payment is confirmed
            ]);
            $subscription->save();

            // Find the approval URL
            $approvalUrl = collect($data['links'])
                ->firstWhere('rel', 'approve')['href'] ?? null;

            return [
                'subscription_id' => $subscription->id,
                'external_subscription_id' => $data['id'],
                'redirect_url' => $approvalUrl,
                'status' => $data['status']
            ];
        }

        Log::error('PayPal create subscription error', [
            'employer' => $employer->id,
            'plan' => $plan->toArray(),
            'response' => $response->json()
        ]);

        throw new \Exception('Failed to create PayPal subscription');
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

        $response = Http::withToken($this->getAccessToken())
            ->post("{$this->baseUrl}/v1/billing/subscriptions/{$subscription->subscription_id}/cancel", [
                'reason' => 'Cancelled by user'
            ]);

        if ($response->successful()) {
            $subscription->is_active = false;
            $subscription->save();
            return true;
        }

        Log::error('PayPal cancel subscription error', [
            'subscription' => $subscription->toArray(),
            'response' => $response->json()
        ]);

        return false;
    }

    /**
     * Verify webhook signature
     *
     * @param string $payload
     * @param array $headers
     * @return bool
     */
    protected function verifyWebhookSignature(string $payload, array $headers): bool
    {
        $webhookId = $this->webhookId;
        $requestHeaders = [
            'transmission_id' => $headers['Paypal-Transmission-Id'] ?? '',
            'transmission_time' => $headers['Paypal-Transmission-Time'] ?? '',
            'cert_url' => $headers['Paypal-Cert-Url'] ?? '',
            'auth_algo' => $headers['Paypal-Auth-Algo'] ?? '',
            'transmission_sig' => $headers['Paypal-Transmission-Sig'] ?? '',
        ];

        $response = Http::withToken($this->getAccessToken())
            ->post("{$this->baseUrl}/v1/notifications/verify-webhook-signature", [
                'webhook_id' => $webhookId,
                'transmission_id' => $requestHeaders['transmission_id'],
                'transmission_time' => $requestHeaders['transmission_time'],
                'transmission_sig' => $requestHeaders['transmission_sig'],
                'cert_url' => $requestHeaders['cert_url'],
                'auth_algo' => $requestHeaders['auth_algo'],
                'webhook_event' => json_decode($payload, true)
            ]);

        if ($response->successful() && $response->json('verification_status') === 'SUCCESS') {
            return true;
        }

        Log::error('PayPal webhook signature verification failed', [
            'headers' => $requestHeaders,
            'response' => $response->json()
        ]);

        return false;
    }

    /**
     * Handle webhook events from PayPal
     *
     * @param string $payload
     * @param array $headers
     * @return bool
     */
    public function handleWebhook(string $payload, array $headers): bool
    {
        // Verify webhook signature
        if (!$this->verifyWebhookSignature($payload, $headers)) {
            return false;
        }

        $data = json_decode($payload, true);
        $event = $data['event_type'] ?? '';
        $resourceId = $data['resource']['id'] ?? '';

        Log::info('PayPal webhook received', [
            'event' => $event,
            'resourceId' => $resourceId
        ]);

        switch ($event) {
            case 'BILLING.SUBSCRIPTION.CREATED':
                return $this->handleSubscriptionCreated($data);

            case 'BILLING.SUBSCRIPTION.ACTIVATED':
                return $this->handleSubscriptionActivated($data);

            case 'PAYMENT.SALE.COMPLETED':
                return $this->handlePaymentCompleted($data);

            case 'BILLING.SUBSCRIPTION.CANCELLED':
                return $this->handleSubscriptionCancelled($data);

            default:
                Log::info('Unhandled PayPal webhook event', ['event' => $event]);
                return true;
        }
    }

    /**
     * Handle subscription created event
     *
     * @param array $data
     * @return bool
     */
    protected function handleSubscriptionCreated(array $data): bool
    {
        $subscriptionId = $data['resource']['id'] ?? '';

        // Find the subscription in our database
        $subscription = Subscription::where('subscription_id', $subscriptionId)
            ->where('payment_method', 'paypal')
            ->first();

        if (!$subscription) {
            Log::error('PayPal subscription not found', ['subscriptionId' => $subscriptionId]);
            return false;
        }

        // Update subscription status
        $subscription->payment_reference = $data['resource']['id'];
        $subscription->save();

        return true;
    }

    /**
     * Handle subscription activated event
     *
     * @param array $data
     * @return bool
     */
    protected function handleSubscriptionActivated(array $data): bool
    {
        $subscriptionId = $data['resource']['id'] ?? '';

        // Find the subscription in our database
        $subscription = Subscription::where('subscription_id', $subscriptionId)
            ->where('payment_method', 'paypal')
            ->first();

        if (!$subscription) {
            Log::error('PayPal subscription not found', ['subscriptionId' => $subscriptionId]);
            return false;
        }

        // Update subscription status
        $subscription->is_active = true;
        $subscription->save();

        return true;
    }

    /**
     * Handle payment completed event
     *
     * @param array $data
     * @return bool
     */
    protected function handlePaymentCompleted(array $data): bool
    {
        $billingAgreementId = $data['resource']['billing_agreement_id'] ?? '';

        if (!$billingAgreementId) {
            return true; // Not a subscription payment
        }

        // Find the subscription in our database
        $subscription = Subscription::where('subscription_id', $billingAgreementId)
            ->where('payment_method', 'paypal')
            ->first();

        if (!$subscription) {
            Log::error('PayPal subscription not found', ['billingAgreementId' => $billingAgreementId]);
            return false;
        }

        // Update transaction ID
        $subscription->transaction_id = $data['resource']['id'];
        $subscription->save();

        return true;
    }

    /**
     * Handle subscription cancelled event
     *
     * @param array $data
     * @return bool
     */
    protected function handleSubscriptionCancelled(array $data): bool
    {
        $subscriptionId = $data['resource']['id'] ?? '';

        // Find the subscription in our database
        $subscription = Subscription::where('subscription_id', $subscriptionId)
            ->where('payment_method', 'paypal')
            ->first();

        if (!$subscription) {
            Log::error('PayPal subscription not found', ['subscriptionId' => $subscriptionId]);
            return false;
        }

        // Update subscription status
        $subscription->is_active = false;
        $subscription->save();

        return true;
    }
}
