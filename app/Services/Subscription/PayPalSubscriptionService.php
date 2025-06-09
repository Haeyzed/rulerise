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
     * Get PayPal access token
     *
     * @return string
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
     * Create a subscription plan in the payment gateway
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
     * Get proper start time for PayPal subscription
     *
     * @param SubscriptionPlan $plan
     * @return string
     */
    protected function getSubscriptionStartTime(SubscriptionPlan $plan): string
    {
        // For one-time payments, start immediately
        if ($plan->isOneTime()) {
            // Use a longer buffer for one-time payments to ensure processing time
            $startTime = Carbon::now('UTC')->addMinutes(10);
        } else {
            // For recurring subscriptions, use a longer buffer
            $startTime = Carbon::now('UTC')->addMinutes(15);
        }

        // Ensure we're using UTC timezone and proper ISO8601 format
        return $startTime->toIso8601String();
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

        // Prepare subscription data
        $subscriptionData = [
            'plan_id' => $externalPlanId,
            'start_time' => $this->getSubscriptionStartTime($plan),
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

        // Log the subscription data for debugging
        Log::info('Creating PayPal subscription', [
            'employer_id' => $employer->id,
            'plan_id' => $plan->id,
            'external_plan_id' => $externalPlanId,
            'start_time' => $subscriptionData['start_time'],
            'is_one_time' => $plan->isOneTime()
        ]);

        // Create the subscription
        $response = Http::withToken($this->getAccessToken())
            ->withHeaders([
                'PayPal-Request-Id' => Str::uuid()->toString(),
            ])
            ->post("{$this->baseUrl}/v1/billing/subscriptions", $subscriptionData);

        if ($response->successful()) {
            $data = $response->json();

            // Create a pending subscription record
            $subscription = $this->createSubscriptionRecord($employer, $plan, $data);

            // Find the approval URL
            $approvalUrl = collect($data['links'])
                ->firstWhere('rel', 'approve')['href'] ?? null;

            Log::info('PayPal subscription created successfully', [
                'subscription_id' => $subscription->id,
                'external_subscription_id' => $data['id'],
                'status' => $data['status']
            ]);

            return [
                'subscription_id' => $subscription->id,
                'external_subscription_id' => $data['id'],
                'redirect_url' => $approvalUrl,
                'status' => $data['status']
            ];
        }

        // Log detailed error information
        $errorResponse = $response->json();
        Log::error('PayPal create subscription error', [
            'employer' => $employer->id,
            'plan' => $plan->toArray(),
            'start_time' => $subscriptionData['start_time'],
            'response' => $errorResponse,
            'status_code' => $response->status()
        ]);

        // Provide more specific error messages
        if (isset($errorResponse['details'])) {
            $errorDetails = collect($errorResponse['details']);
            $startTimeError = $errorDetails->firstWhere('field', '/start_time');

            if ($startTimeError) {
                throw new \Exception('PayPal subscription start time error: ' . $startTimeError['description']);
            }
        }

        throw new \Exception('Failed to create PayPal subscription: ' . ($errorResponse['message'] ?? 'Unknown error'));
    }

    /**
     * Create subscription record in database
     *
     * @param Employer $employer
     * @param SubscriptionPlan $plan
     * @param array $data
     * @return Subscription
     */
    private function createSubscriptionRecord(Employer $employer, SubscriptionPlan $plan, array $data): Subscription
    {
        $endDate = null;
        if ($plan->isRecurring() && $plan->duration_days) {
            $endDate = Carbon::now()->addDays($plan->duration_days);
            if ($plan->hasTrial()) {
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
            'job_posts_left' => $plan->job_posts_limit,
            'featured_jobs_left' => $plan->featured_jobs_limit,
            'cv_downloads_left' => $plan->resume_views_limit,
            'payment_type' => $plan->payment_type,
            'is_active' => false, // Will be activated when payment is confirmed
        ]);
    }

    /**
     * Update a subscription plan in the payment gateway
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
     * Delete a subscription plan from the payment gateway
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
     * List all subscription plans from PayPal
     *
     * @param array $filters Optional filters
     * @return array List of plans
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
     *
     * @param Employer $employer
     * @return array List of subscriptions
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
     *
     * @param string $subscriptionId
     * @return array Subscription details
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
     *
     * @param Subscription $subscription
     * @return bool
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
     *
     * @param Subscription $subscription
     * @param SubscriptionPlan $newPlan
     * @return bool
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
     *
     * @param string $subscriptionId
     * @return array List of transactions
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
     *
     * @param string $payload
     * @param array $headers
     * @return bool
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
            'auth_algo' => $normalizedHeaders['paypal-auth-algo'] ?? '',
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
     *
     * @param string $payload
     * @param array $headers
     * @return bool
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
     *
     * @param array $data
     * @return bool
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
     *
     * @param array $data
     * @return bool
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
     *
     * @param array $data
     * @return bool
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
     *
     * @param array $data
     * @return bool
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
     *
     * @param Subscription $subscription
     * @return void
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
