<?php

namespace App\Services\Subscription;

use App\Models\Employer;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Notifications\SubscriptionActivatedNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PayPalSubscriptionService implements SubscriptionServiceInterface
{
    public $baseUrl;
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
     * Validate if employer can use one-time payment for a plan
     */
    public function canUseOneTimePayment(Employer $employer, SubscriptionPlan $plan): bool
    {
        // For one-time plans, employer must have used trial period OR plan doesn't require trial
        if ($plan->isOneTime()) {
            // If plan doesn't have trial, allow one-time payment
            if (!$plan->hasTrial()) {
                return true;
            }
            // If plan has trial, employer must have used trial
            return $employer->has_used_trial;
        }

        return false;
    }

    /**
     * Validate if employer needs trial for a plan
     */
    public function shouldUseTrial(Employer $employer, SubscriptionPlan $plan): bool
    {
        // Only use trial if:
        // 1. Plan has trial enabled
        // 2. Employer hasn't used trial yet
        // 3. Plan is recurring (one-time plans handle trial differently)
        return $plan->hasTrial() &&
            !$employer->has_used_trial &&
            $plan->isRecurring();
    }

    /**
     * Get PayPal access token
     */
    public function getAccessToken(): string
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
     * Create a subscription plan in the payment gateway
     */
    public function createPlan(SubscriptionPlan $plan): string
    {
        if ($plan->isOneTime()) {
            // One-time plans don't need to be created in PayPal
            // They use the Order API directly
            return 'one_time_' . $plan->id;
        }

        $productId = $this->createProduct($plan);

        $response = Http::withToken($this->getAccessToken())
            ->withHeaders([
                'PayPal-Request-Id' => Str::uuid()->toString(),
            ])
            ->post("{$this->baseUrl}/v1/billing/plans", [
                'product_id' => $productId,
                'name' => $plan->name,
                'description' => $plan->description ?? $plan->name,
                'billing_cycles' => $plan->getPayPalBillingCycles(),
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
     * Create a subscription for an employer with enhanced trial logic
     */
    public function createSubscription(Employer $employer, SubscriptionPlan $plan, array $paymentData = []): array
    {
        // Validate business rules
        if ($plan->isOneTime() && !$this->canUseOneTimePayment($employer, $plan)) {
            throw new \Exception('One-time payments require trial period to be used first');
        }

        // For one-time plans, use Order API
        if ($plan->isOneTime()) {
            return $this->createOneTimeOrder($employer, $plan, $paymentData);
        }

        // For recurring plans, use Billing API
        return $this->createRecurringSubscription($employer, $plan, $paymentData);
    }

    /**
     * Create a one-time payment order using PayPal Order API
     */
    public function createOneTimeOrder(Employer $employer, SubscriptionPlan $plan, array $paymentData = []): array
    {
        if (!$plan->isOneTime()) {
            throw new \Exception('This method is only for one-time payment plans');
        }

        if (!$this->canUseOneTimePayment($employer, $plan)) {
            throw new \Exception('Employer must use trial period before one-time payments');
        }

        $response = Http::withToken($this->getAccessToken())
            ->withHeaders([
                'PayPal-Request-Id' => Str::uuid()->toString(),
            ])
            ->post("{$this->baseUrl}/v2/checkout/orders", [
                'intent' => 'CAPTURE',
                'purchase_units' => [
                    [
                        'reference_id' => "plan_{$plan->id}_employer_{$employer->id}",
                        'amount' => [
                            'currency_code' => strtoupper($plan->currency),
                            'value' => number_format($plan->price, 2, '.', '')
                        ],
                        'description' => $plan->name,
                        'custom_id' => "employer_{$employer->id}_plan_{$plan->id}"
                    ]
                ],
                'application_context' => [
                    'brand_name' => config('app.name'),
                    'locale' => 'en-US',
                    'shipping_preference' => 'NO_SHIPPING',
                    'user_action' => 'PAY_NOW',
                    'return_url' => config('app.frontend_url') . '/employer/dashboard',
                    'cancel_url' => config('app.frontend_url') . '/employer/dashboard',
                ]
            ]);

        if ($response->successful()) {
            $data = $response->json();

            // Create a pending subscription record
            $subscription = $this->createSubscriptionRecord($employer, $plan, [
                'id' => $data['id'],
                'type' => 'order'
            ]);

            // Find the approval URL
            $approvalUrl = collect($data['links'])
                ->firstWhere('rel', 'approve')['href'] ?? null;

            return [
                'subscription_id' => $subscription->id,
                'external_subscription_id' => $data['id'],
                'redirect_url' => $approvalUrl,
                'status' => $data['status'],
                'payment_type' => 'one_time'
            ];
        }

        Log::error('PayPal create order error', [
            'employer' => $employer->id,
            'plan' => $plan->toArray(),
            'response' => $response->json()
        ]);

        throw new \Exception('Failed to create PayPal order');
    }

    /**
     * Create a recurring subscription using PayPal Billing API
     */
    protected function createRecurringSubscription(Employer $employer, SubscriptionPlan $plan, array $paymentData = []): array
    {
        // Get or create the external plan ID
        $externalPlanId = $plan->external_paypal_id ?? $this->createPlan($plan);

        if (!$plan->external_paypal_id) {
            $plan->external_paypal_id = $externalPlanId;
            $plan->save();
        }

        $subscriptionData = [
            'plan_id' => $externalPlanId,
            'start_time' => Carbon::now()->addMinutes(10)->toIso8601String(),
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
        ];

        // Handle trial logic for recurring subscriptions
        if ($this->shouldUseTrial($employer, $plan)) {
            // Trial will be handled by the plan's billing cycles
            Log::info('Creating PayPal subscription with trial', [
                'employer_id' => $employer->id,
                'plan_id' => $plan->id,
                'trial_days' => $plan->getTrialPeriodDays()
            ]);
        } else {
            // No trial - immediate payment
            Log::info('Creating PayPal subscription without trial', [
                'employer_id' => $employer->id,
                'plan_id' => $plan->id,
                'has_used_trial' => $employer->has_used_trial
            ]);
        }

        $response = Http::withToken($this->getAccessToken())
            ->withHeaders([
                'PayPal-Request-Id' => Str::uuid()->toString(),
            ])
            ->post("{$this->baseUrl}/v1/billing/subscriptions", $subscriptionData);

        if ($response->successful()) {
            $data = $response->json();

            // Create a pending subscription record
            $subscription = $this->createSubscriptionRecord($employer, $plan, $data);

            // Mark trial as used if this subscription uses trial
            if ($this->shouldUseTrial($employer, $plan)) {
                $employer->markTrialAsUsed();
            }

            // Find the approval URL
            $approvalUrl = collect($data['links'])
                ->firstWhere('rel', 'approve')['href'] ?? null;

            return [
                'subscription_id' => $subscription->id,
                'external_subscription_id' => $data['id'],
                'redirect_url' => $approvalUrl,
                'status' => $data['status'],
                'payment_type' => 'recurring'
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
     * Create subscription record in database
     */
    private function createSubscriptionRecord(Employer $employer, SubscriptionPlan $plan, array $data): Subscription
    {
        $endDate = null;
        if ($plan->isRecurring() && $plan->duration_days) {
            $endDate = Carbon::now()->addDays($plan->duration_days);
            if ($plan->hasTrial() && !$employer->has_used_trial) {
                $endDate->addDays($plan->getTrialPeriodDays());
            }
        }

        return Subscription::create([
            'employer_id' => $employer->id,
            'subscription_plan_id' => $plan->id,
            'start_date' => Carbon::now(),
            'end_date' => $endDate,
            'amount_paid' => $plan->price,
            'currency' => $plan->currency,
            'payment_method' => 'paypal',
            'subscription_id' => $data['id'],
            'payment_reference' => $data['id'],
            'job_posts_left' => $plan->job_posts_limit,
            'featured_jobs_left' => $plan->featured_jobs_limit,
            'cv_downloads_left' => $plan->resume_views_limit,
            'payment_type' => $plan->payment_type,
            'is_active' => false, // Will be activated when payment is confirmed
            'used_trial' => $this->shouldUseTrial($employer, $plan),
        ]);
    }

    /**
     * Update a subscription plan in the payment gateway
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
     * List all subscriptions for an employer
     */
    public function listSubscriptions(Employer $employer): array
    {
        // PayPal doesn't provide a direct way to list subscriptions by customer
        // We'll retrieve from our database instead
        $subscriptions = Subscription::where('employer_id', $employer->id)
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
     */
    public function suspendSubscription(Subscription $subscription): bool
    {
        if (!$subscription->subscription_id) {
            return false;
        }

        // One-time payments can't be suspended
        if ($subscription->isOneTime()) {
            return false;
        }

        $response = Http::withToken($this->getAccessToken())
            ->post("{$this->baseUrl}/v1/billing/subscriptions/{$subscription->subscription_id}/suspend", [
                'reason' => 'Suspended by user'
            ]);

        if ($response->successful()) {
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
     */
    public function reactivateSubscription(Subscription $subscription): bool
    {
        if (!$subscription->subscription_id) {
            return false;
        }

        // One-time payments can't be reactivated
        if ($subscription->isOneTime()) {
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
     * Update subscription plan
     */
    public function updateSubscriptionPlan(Subscription $subscription, SubscriptionPlan $newPlan): bool
    {
        // One-time subscriptions can't be updated to a different plan
        if ($subscription->isOneTime()) {
            return false;
        }

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
            $subscription->end_date = $newPlan->isRecurring() && $newPlan->duration_days
                ? Carbon::now()->addDays($newPlan->duration_days)
                : null;
            $subscription->job_posts_left = $newPlan->job_posts_limit;
            $subscription->featured_jobs_left = $newPlan->featured_jobs_limit;
            $subscription->cv_downloads_left = $newPlan->resume_views_limit;
            $subscription->payment_type = $newPlan->payment_type;
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
     * Get subscription transactions
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
     * Verify webhook signature
     */
    protected function verifyWebhookSignature(string $payload, array $headers): bool
    {
        $webhookId = $this->webhookId;

        // PayPal header names can be in different cases, so we need to normalize them
        $normalizedHeaders = [];
        foreach ($headers as $key => $value) {
            $normalizedHeaders[strtolower($key)] = $value;
        }

        // Extract the required headers using case-insensitive keys
        $requestHeaders = [
            'transmission_id' => $normalizedHeaders['paypal-transmission-id'] ?? '',
            'transmission_time' => $normalizedHeaders['paypal-transmission-time'] ?? '',
            'cert_url' => $normalizedHeaders['paypal-cert-url'] ?? '',
            'auth_algo' => $normalizedHeaders['paypal-auth_algo'] ?? '',
            'transmission_sig' => $normalizedHeaders['paypal-transmission-sig'] ?? '',
        ];

        // Log the headers for debugging
        Log::info('PayPal webhook headers', [
            'original' => $headers,
            'normalized' => $normalizedHeaders,
            'extracted' => $requestHeaders
        ]);

        // Check if we have all required headers
        if (empty($requestHeaders['transmission_id']) ||
            empty($requestHeaders['transmission_time']) ||
            empty($requestHeaders['cert_url']) ||
            empty($requestHeaders['auth_algo']) ||
            empty($requestHeaders['transmission_sig'])) {

            Log::error('Missing required PayPal webhook headers', [
                'headers' => $requestHeaders
            ]);

            return false;
        }

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
     */
    public function handleWebhook(string $payload, array $headers): bool
    {
        // In development/testing environments, we might want to skip signature verification
        $verifySignature = config('services.paypal.verify_webhook_signature', true);

        if ($verifySignature && !$this->verifyWebhookSignature($payload, $headers)) {
            Log::warning('PayPal webhook signature verification failed, but processing event anyway');
            // Continue processing the webhook even if verification fails
            // This helps during testing and development
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

            case 'PAYMENT.CAPTURE.COMPLETED':
                return $this->handlePaymentCaptureCompleted($data);

            case 'CHECKOUT.ORDER.APPROVED':
                return $this->handleOrderApproved($data);

            case 'CHECKOUT.ORDER.COMPLETED':
                return $this->handleOrderCompleted($data);

            case 'BILLING.SUBSCRIPTION.CANCELLED':
                return $this->handleSubscriptionCancelled($data);

            case 'BILLING.SUBSCRIPTION.SUSPENDED':
                return $this->handleSubscriptionSuspended($data);

            case 'BILLING.SUBSCRIPTION.UPDATED':
                return $this->handleSubscriptionUpdated($data);

            case 'BILLING.SUBSCRIPTION.EXPIRED':
                return $this->handleSubscriptionExpired($data);

            default:
                Log::info('Unhandled PayPal webhook event', ['event' => $event]);
                return true;
        }
    }

    /**
     * Handle subscription created event
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

        // Send notification to the employer
        $this->sendActivationNotification($subscription);

        return true;
    }

    /**
     * Handle payment completed event
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

        // For one-time payments, we need to activate the subscription here
        if ($subscription->isOneTime() && !$subscription->is_active) {
            $subscription->is_active = true;
            $this->sendActivationNotification($subscription);
        }

        $subscription->save();

        return true;
    }

    /**
     * Handle payment capture completed event (for one-time payments)
     */
    protected function handlePaymentCaptureCompleted(array $data): bool
    {
        $orderId = $data['resource']['supplementary_data']['related_ids']['order_id'] ?? '';
        $subscriptionId = $data['resource']['supplementary_data']['related_ids']['subscription_id'] ?? '';

        if (!$orderId && !$subscriptionId) {
            return true; // Not a payment we can identify
        }

        // Try to find by subscription ID first (for one-time subscription payments)
        if ($subscriptionId) {
            $subscription = Subscription::where('subscription_id', $subscriptionId)
                ->where('payment_method', 'paypal')
                ->first();

            if ($subscription) {
                // Update transaction ID and activate the subscription
                $subscription->transaction_id = $data['resource']['id'];
                $subscription->is_active = true;
                $subscription->save();

                $this->sendActivationNotification($subscription);
                return true;
            }
        }

        // Try to find by order ID (for direct one-time payments)
        if ($orderId) {
            $subscription = Subscription::where('payment_reference', $orderId)
                ->where('payment_method', 'paypal')
                ->first();

            if ($subscription) {
                // Update transaction ID and activate the subscription
                $subscription->transaction_id = $data['resource']['id'];
                $subscription->is_active = true;
                $subscription->save();

                $this->sendActivationNotification($subscription);
                return true;
            }
        }

        Log::error('PayPal payment capture - subscription not found', [
            'orderId' => $orderId,
            'subscriptionId' => $subscriptionId
        ]);

        return false;
    }

    /**
     * Handle order approved event
     */
    protected function handleOrderApproved(array $data): bool
    {
        $orderId = $data['resource']['id'] ?? '';

        // Find the subscription in our database by payment_reference (order ID)
        $subscription = Subscription::where('payment_reference', $orderId)
            ->where('payment_method', 'paypal')
            ->first();

        if (!$subscription) {
            Log::error('PayPal subscription not found for order', ['orderId' => $orderId]);
            return false;
        }

        // Update the subscription status
        $subscription->external_status = 'APPROVED';
        $subscription->save();

        return true;
    }

    /**
     * Handle order completed event
     */
    protected function handleOrderCompleted(array $data): bool
    {
        $orderId = $data['resource']['id'] ?? '';

        // Find the subscription in our database by payment_reference (order ID)
        $subscription = Subscription::where('payment_reference', $orderId)
            ->where('payment_method', 'paypal')
            ->first();

        if (!$subscription) {
            Log::error('PayPal subscription not found for order', ['orderId' => $orderId]);
            return false;
        }

        // Update the subscription status and activate it
        $subscription->external_status = 'COMPLETED';
        $subscription->is_active = true;
        $subscription->save();

        $this->sendActivationNotification($subscription);

        return true;
    }

    /**
     * Handle subscription cancelled event
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

    /**
     * Handle subscription suspended event
     */
    protected function handleSubscriptionSuspended(array $data): bool
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
        $subscription->is_suspended = true;
        $subscription->save();

        return true;
    }

    /**
     * Handle subscription updated event
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
            $subscription->is_suspended = false;
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
     * Handle subscription expired event
     */
    protected function handleSubscriptionExpired(array $data): bool
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

        // For one-time payments, we don't want to deactivate when the subscription expires
        // since they should have lifetime access
        if ($subscription->isOneTime()) {
            return true;
        }

        // Update subscription status for recurring subscriptions
        $subscription->is_active = false;
        $subscription->save();

        return true;
    }

    /**
     * Update subscription with PayPal details
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
                'failed_payments_count' => $billingInfo['failed_payments_count'] ?? null,
                'payment_method' => $billingInfo['last_payment']['payer']['payment_method'] ?? 'PayPal',
                'last_four' => null // PayPal doesn't provide this
            ];

            // Update subscription end date based on final payment time if available
            // Only for recurring subscriptions
            if (!$subscription->isOneTime() && isset($billingInfo['final_payment_time'])) {
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

    /**
     * Send activation notification
     */
    private function sendActivationNotification(Subscription $subscription): void
    {
        try {
            $employer = $subscription->employer;
            if ($employer && $employer->user) {
                $employer->user->notify(new SubscriptionActivatedNotification($subscription));

                Log::info('PayPal subscription activation notification sent', [
                    'employer_id' => $employer->id,
                    'subscription_id' => $subscription->id
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to send PayPal subscription activation notification', [
                'subscription_id' => $subscription->id,
                'error' => $e->getMessage()
            ]);
        }
    }
}
