<?php

namespace App\Services\Subscription;

use App\Models\Employer;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PayPalSubscriptionService implements SubscriptionServiceInterface
{
    protected string $baseUrl;
    protected mixed $clientId;
    protected mixed $clientSecret;
    protected string $accessToken;
    protected mixed $webhookId;

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
     * @throws ConnectionException
     * @throws Exception
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

        throw new Exception('Failed to get PayPal access token');
    }

    /**
     * Create a product in PayPal
     *
     * @param SubscriptionPlan $plan
     * @return string Product ID
     * @throws Exception
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

        throw new Exception('Failed to create PayPal product');
    }

    /**
     * Create a subscription plan in the payment gateway
     *
     * @param SubscriptionPlan $plan
     * @return string External plan ID
     * @throws Exception
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

        throw new Exception('Failed to create PayPal plan');
    }

    /**
     * Update a subscription plan in the payment gateway
     *
     * @param SubscriptionPlan $plan
     * @param string $externalPlanId
     * @return bool
     * @throws ConnectionException
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
     * Delete a subscription plan from the payment gateway
     *
     * @param string $externalPlanId
     * @return bool
     * @throws ConnectionException
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
     * List all subscription plans from PayPal
     *
     * @param array $filters Optional filters
     * @return array List of plans
     * @throws ConnectionException
     */
    public function listPlans(array $filters = []): array
    {
        $queryParams = [
            'product_id' => $filters['product_id'] ?? null,
            'page_size' => $filters['page_size'] ?? 20,
            'page' => $filters['page'] ?? 1,
            'total_required' => 'true',
            'status' => $filters['status'] ?? 'ACTIVE',
        ];

        // Remove null values
        $queryParams = array_filter($queryParams);

        $response = Http::withToken($this->getAccessToken())
            ->get("{$this->baseUrl}/v1/billing/plans", $queryParams);

        if ($response->successful()) {
            return $response->json();
        }

        Log::error('PayPal list plans error', [
            'filters' => $filters,
            'response' => $response->json()
        ]);

        return ['plans' => []];
    }

    /**
     * Get details of a specific subscription plan
     *
     * @param string $externalPlanId
     * @return array Plan details
     * @throws ConnectionException
     */
    public function getPlanDetails(string $externalPlanId): array
    {
        $response = Http::withToken($this->getAccessToken())
            ->get("{$this->baseUrl}/v1/billing/plans/{$externalPlanId}");

        if ($response->successful()) {
            return $response->json();
        }

        Log::error('PayPal get plan details error', [
            'externalPlanId' => $externalPlanId,
            'response' => $response->json()
        ]);

        return [];
    }

    /**
     * Create a subscription for an employer
     *
     * @param Employer $employer
     * @param SubscriptionPlan $plan
     * @param array $paymentData
     * @return array Subscription data with redirect URL
     * @throws Exception
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

        throw new Exception('Failed to create PayPal subscription');
    }

    /**
     * List all subscriptions for an employer
     *
     * @param Employer $employer
     * @return array List of subscriptions
     * @throws ConnectionException
     */
    public function listSubscriptions(Employer $employer): array
    {
        // PayPal doesn't provide a direct way to list subscriptions by customer
        // We'll retrieve from our database instead
        $subscriptions = Subscription::query()->where('employer_id', $employer->id)
            ->where('payment_method', 'paypal')
            ->whereNotNull('subscription_id')
            ->get();

        $result = ['subscriptions' => []];

        foreach ($subscriptions as $subscription) {
            if ($subscription->subscription_id) {
                $details = $this->getSubscriptionDetails($subscription->subscription_id);
                if (!empty($details)) {
                    $result['subscriptions'][] = $details;
                }
            }
        }

        return $result;
    }

    /**
     * Get details of a specific subscription
     *
     * @param string $subscriptionId
     * @return array Subscription details
     * @throws ConnectionException
     */
    public function getSubscriptionDetails(string $subscriptionId): array
    {
        $response = Http::withToken($this->getAccessToken())
            ->get("{$this->baseUrl}/v1/billing/subscriptions/{$subscriptionId}");

        if ($response->successful()) {
            return $response->json();
        }

        Log::error('PayPal get subscription details error', [
            'subscriptionId' => $subscriptionId,
            'response' => $response->json()
        ]);

        return [];
    }

    /**
     * Cancel a subscription
     *
     * @param Subscription $subscription
     * @return bool
     * @throws ConnectionException
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
     * Suspend a subscription (temporarily pause)
     *
     * @param Subscription $subscription
     * @return bool
     * @throws ConnectionException
     */
    public function suspendSubscription(Subscription $subscription): bool
    {
        if (!$subscription->subscription_id) {
            return false;
        }

        $response = Http::withToken($this->getAccessToken())
            ->post("{$this->baseUrl}/v1/billing/subscriptions/{$subscription->subscription_id}/suspend", [
                'reason' => 'Suspended by user'
            ]);

        if ($response->successful()) {
//            $subscription->is_active = false;
            $subscription->is_suspended = true;
            $subscription->save();
            return true;
        }

        Log::error('PayPal suspend subscription error', [
            'subscription' => $subscription->toArray(),
            'response' => $response->json()
        ]);

        return false;
    }

    /**
     * Reactivate a suspended subscription
     *
     * @param Subscription $subscription
     * @return bool
     * @throws ConnectionException
     */
    public function reactivateSubscription(Subscription $subscription): bool
    {
        if (!$subscription->subscription_id) {
            return false;
        }

        $response = Http::withToken($this->getAccessToken())
            ->post("{$this->baseUrl}/v1/billing/subscriptions/{$subscription->subscription_id}/activate", [
                'reason' => 'Reactivated by user'
            ]);

        if ($response->successful()) {
            $subscription->is_active = true;
            $subscription->is_suspended = false;
            $subscription->save();
            return true;
        }

        Log::error('PayPal reactivate subscription error', [
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
     * @throws ConnectionException
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
     * @throws ConnectionException
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

            case 'BILLING.SUBSCRIPTION.SUSPENDED':
                return $this->handleSubscriptionSuspended($data);

            case 'BILLING.SUBSCRIPTION.UPDATED':
                return $this->handleSubscriptionUpdated($data);

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
        $subscription = Subscription::query()->where('subscription_id', $subscriptionId)
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
        $subscription = Subscription::query()->where('subscription_id', $subscriptionId)
            ->where('payment_method', 'paypal')
            ->first();

        if (!$subscription) {
            Log::error('PayPal subscription not found', ['subscriptionId' => $subscriptionId]);
            return false;
        }

        // Update subscription status
        $subscription->is_active = true;

        // Get detailed subscription information
        try {
            $details = $this->getSubscriptionDetails($subscriptionId);
            if (!empty($details)) {
                $this->updateSubscriptionWithPayPalDetails($subscription, $details);
            }
        } catch (\Exception $e) {
            Log::error('Failed to get PayPal subscription details', [
                'subscriptionId' => $subscriptionId,
                'error' => $e->getMessage()
            ]);
        }

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
        $subscription = Subscription::query()->where('subscription_id', $billingAgreementId)
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
        $subscription = Subscription::query()->where('subscription_id', $subscriptionId)
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

    /**
     * Handle subscription suspended event
     *
     * @param array $data
     * @return bool
     */
    protected function handleSubscriptionSuspended(array $data): bool
    {
        $subscriptionId = $data['resource']['id'] ?? '';

        // Find the subscription in our database
        $subscription = Subscription::query()->where('subscription_id', $subscriptionId)
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

    /**
     * Handle subscription updated event
     *
     * @param array $data
     * @return bool
     */
    protected function handleSubscriptionUpdated(array $data): bool
    {
        $subscriptionId = $data['resource']['id'] ?? '';

        // Find the subscription in our database
        $subscription = Subscription::query()->where('subscription_id', $subscriptionId)
            ->where('payment_method', 'paypal')
            ->first();

        if (!$subscription) {
            Log::error('PayPal subscription not found', ['subscriptionId' => $subscriptionId]);
            return false;
        }

        // Update subscription status based on the status in the webhook
        $status = $data['resource']['status'] ?? '';
        if ($status === 'ACTIVE') {
            $subscription->is_active = true;
        } elseif ($status === 'SUSPENDED') {
            $subscription->is_suspended = true;
        } elseif (in_array($status, ['CANCELLED', 'EXPIRED'])) {
            $subscription->is_active = false;
        }

        // Get detailed subscription information
        try {
            $details = $this->getSubscriptionDetails($subscriptionId);
            if (!empty($details)) {
                $this->updateSubscriptionWithPayPalDetails($subscription, $details);
            }
        } catch (\Exception $e) {
            Log::error('Failed to get PayPal subscription details', [
                'subscriptionId' => $subscriptionId,
                'error' => $e->getMessage()
            ]);
        }

        $subscription->save();

        return true;
    }

    /**
     * Get subscription transactions
     *
     * @param string $subscriptionId
     * @return array List of transactions
     * @throws ConnectionException
     */
    public function getSubscriptionTransactions(string $subscriptionId): array
    {
        $response = Http::withToken($this->getAccessToken())
            ->get("{$this->baseUrl}/v1/billing/subscriptions/{$subscriptionId}/transactions");

        if ($response->successful()) {
            return $response->json();
        }

        Log::error('PayPal get subscription transactions error', [
            'subscriptionId' => $subscriptionId,
            'response' => $response->json()
        ]);

        return ['transactions' => []];
    }

    /**
     * Update subscription quantity
     *
     * @param Subscription $subscription
     * @param int $quantity
     * @return bool
     * @throws ConnectionException
     */
    public function updateSubscriptionQuantity(Subscription $subscription, int $quantity): bool
    {
        if (!$subscription->subscription_id) {
            return false;
        }

        $response = Http::withToken($this->getAccessToken())
            ->patch("{$this->baseUrl}/v1/billing/subscriptions/{$subscription->subscription_id}", [
                [
                    'op' => 'replace',
                    'path' => '/quantity',
                    'value' => $quantity
                ]
            ]);

        if ($response->successful()) {
            return true;
        }

        Log::error('PayPal update subscription quantity error', [
            'subscription' => $subscription->toArray(),
            'quantity' => $quantity,
            'response' => $response->json()
        ]);

        return false;
    }

    /**
     * Update subscription plan
     *
     * @param Subscription $subscription
     * @param SubscriptionPlan $newPlan
     * @return bool
     * @throws ConnectionException
     */
    public function updateSubscriptionPlan(Subscription $subscription, SubscriptionPlan $newPlan): bool
    {
        if (!$subscription->subscription_id) {
            return false;
        }

        // Get or create the external plan ID for the new plan
        $externalPlanId = $newPlan->external_paypal_id ?? $this->createPlan($newPlan);

        // If we created a new plan, save the ID
        if (!$newPlan->external_paypal_id) {
            $newPlan->external_paypal_id = $externalPlanId;
            $newPlan->save();
        }

        $response = Http::withToken($this->getAccessToken())
            ->post("{$this->baseUrl}/v1/billing/subscriptions/{$subscription->subscription_id}/revise", [
                'plan_id' => $externalPlanId
            ]);

        if ($response->successful()) {
            // Update the subscription in our database
            $subscription->subscription_plan_id = $newPlan->id;
            $subscription->end_date = Carbon::now()->addDays($newPlan->duration_days);
            $subscription->job_posts_left = $newPlan->job_posts_limit;
            $subscription->featured_jobs_left = $newPlan->featured_jobs_limit;
            $subscription->cv_downloads_left = $newPlan->resume_views_limit;
            $subscription->save();

            return true;
        }

        Log::error('PayPal update subscription plan error', [
            'subscription' => $subscription->toArray(),
            'newPlan' => $newPlan->toArray(),
            'response' => $response->json()
        ]);

        return false;
    }

    /**
     * Create a webhook for subscription events
     *
     * @param string $url Webhook URL
     * @param array $events Events to subscribe to
     * @return array Webhook details
     * @throws ConnectionException
     */
    public function createWebhook(string $url, array $events = []): array
    {
        // Default events if none provided
        if (empty($events)) {
            $events = [
                'BILLING.SUBSCRIPTION.CREATED',
                'BILLING.SUBSCRIPTION.ACTIVATED',
                'BILLING.SUBSCRIPTION.UPDATED',
                'BILLING.SUBSCRIPTION.CANCELLED',
                'BILLING.SUBSCRIPTION.SUSPENDED',
                'BILLING.SUBSCRIPTION.EXPIRED',
                'PAYMENT.SALE.COMPLETED'
            ];
        }

        $eventTypes = [];
        foreach ($events as $event) {
            $eventTypes[] = ['name' => $event];
        }

        $response = Http::withToken($this->getAccessToken())
            ->post("{$this->baseUrl}/v1/notifications/webhooks", [
                'url' => $url,
                'event_types' => $eventTypes
            ]);

        if ($response->successful()) {
            return $response->json();
        }

        Log::error('PayPal create webhook error', [
            'url' => $url,
            'events' => $events,
            'response' => $response->json()
        ]);

        return [];
    }

    /**
     * List all webhooks
     *
     * @return array List of webhooks
     * @throws ConnectionException
     */
    public function listWebhooks(): array
    {
        $response = Http::withToken($this->getAccessToken())
            ->get("{$this->baseUrl}/v1/notifications/webhooks");

        if ($response->successful()) {
            return $response->json();
        }

        Log::error('PayPal list webhooks error', [
            'response' => $response->json()
        ]);

        return ['webhooks' => []];
    }

    /**
     * Delete a webhook
     *
     * @param string $webhookId
     * @return bool
     * @throws ConnectionException
     */
    public function deleteWebhook(string $webhookId): bool
    {
        $response = Http::withToken($this->getAccessToken())
            ->delete("{$this->baseUrl}/v1/notifications/webhooks/{$webhookId}");

        if ($response->successful()) {
            return true;
        }

        Log::error('PayPal delete webhook error', [
            'webhookId' => $webhookId,
            'response' => $response->json()
        ]);

        return false;
    }



    /**
     * Update subscription with PayPal details
     *
     * @param Subscription $subscription
     * @param array $details
     * @return void
     */
    public function updateSubscriptionWithPayPalDetails(Subscription $subscription, array $details): void
    {
        // Store subscriber information if available
        if (isset($details['subscriber'])) {
            $subscriber = $details['subscriber'];
            $subscription->subscriber_info = [
                'email_address' => $subscriber['email_address'] ?? null,
                'payer_id' => $subscriber['payer_id'] ?? null,
                'name' => $subscriber['name'] ?? null,
                'tenant' => $subscriber['tenant'] ?? null
            ];
        }

        // Store billing information if available
        if (isset($details['billing_info'])) {
            $billingInfo = $details['billing_info'];
            $subscription->billing_info = [
                'outstanding_balance' => $billingInfo['outstanding_balance'] ?? null,
                'cycle_executions' => $billingInfo['cycle_executions'] ?? null,
                'next_billing_time' => $billingInfo['next_billing_time'] ?? null,
                'final_payment_time' => $billingInfo['final_payment_time'] ?? null,
                'failed_payments_count' => $billingInfo['failed_payments_count'] ?? null
            ];

            // Update subscription end date based on final payment time if available
            if (isset($billingInfo['final_payment_time'])) {
                $subscription->end_date = Carbon::parse($billingInfo['final_payment_time']);
            }

            // Update next billing date if available
            if (isset($billingInfo['next_billing_time'])) {
                $subscription->next_billing_date = Carbon::parse($billingInfo['next_billing_time']);
            }
        }

        // Store status and status update time if available
        if (isset($details['status'])) {
            $subscription->external_status = $details['status'];
        }

        if (isset($details['status_update_time'])) {
            $subscription->status_update_time = Carbon::parse($details['status_update_time']);
        }
    }
}
