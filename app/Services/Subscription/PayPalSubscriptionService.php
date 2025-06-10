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
    protected string $baseUrl;
    protected string $clientId;
    protected string $clientSecret;
    protected string $webhookId;
    protected ?string $accessToken = null;

    public function __construct()
    {
        $this->baseUrl = config('services.paypal.sandbox')
            ? 'https://api-m.sandbox.paypal.com'
            : 'https://api-m.paypal.com';
        $this->clientId = config('services.paypal.client_id');
        $this->clientSecret = config('services.paypal.client_secret');
        $this->webhookId = config('services.paypal.webhook_id');
    }

    public function canUseOneTimePayment(Employer $employer, SubscriptionPlan $plan): bool
    {
        if (!$plan->isOneTime()) {
            return false;
        }

        // If plan doesn't have trial, allow one-time payment
        if (!$plan->hasTrial()) {
            return true;
        }

        // If plan has trial, employer must have used trial
        return $employer->has_used_trial;
    }

    public function shouldUseTrial(Employer $employer, SubscriptionPlan $plan): bool
    {
        return $plan->hasTrial() &&
            !$employer->has_used_trial &&
            $plan->isRecurring();
    }

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

        if (!$response->successful()) {
            Log::error('PayPal access token error', ['response' => $response->json()]);
            throw new \Exception('Failed to get PayPal access token');
        }

        $this->accessToken = $response->json('access_token');
        return $this->accessToken;
    }

    protected function createProduct(SubscriptionPlan $plan): string
    {
        $response = Http::withToken($this->getAccessToken())
            ->withHeaders(['PayPal-Request-Id' => Str::uuid()->toString()])
            ->post("{$this->baseUrl}/v1/catalogs/products", [
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

    public function createPlan(SubscriptionPlan $plan): string
    {
        if ($plan->isOneTime()) {
            return 'one_time_' . $plan->id;
        }

        $productId = $this->createProduct($plan);

        $response = Http::withToken($this->getAccessToken())
            ->withHeaders(['PayPal-Request-Id' => Str::uuid()->toString()])
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

        if (!$response->successful()) {
            Log::error('PayPal create plan error', [
                'plan' => $plan->toArray(),
                'response' => $response->json()
            ]);
            throw new \Exception('Failed to create PayPal plan');
        }

        return $response->json('id');
    }

    public function createSubscription(Employer $employer, SubscriptionPlan $plan, array $paymentData = []): array
    {
        if ($plan->isOneTime()) {
            if (!$this->canUseOneTimePayment($employer, $plan)) {
                throw new \Exception('One-time payments require trial period to be used first');
            }
            return $this->createOneTimeOrder($employer, $plan, $paymentData);
        }

        return $this->createRecurringSubscription($employer, $plan, $paymentData);
    }

    public function createOneTimeOrder(Employer $employer, SubscriptionPlan $plan, array $paymentData = []): array
    {
        if (!$plan->isOneTime()) {
            throw new \Exception('This method is only for one-time payment plans');
        }

        if (!$this->canUseOneTimePayment($employer, $plan)) {
            throw new \Exception('Employer must use trial period before one-time payments');
        }

        $response = Http::withToken($this->getAccessToken())
            ->withHeaders(['PayPal-Request-Id' => Str::uuid()->toString()])
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
                    'return_url' => config('app.frontend_url') . '/employer/subscription/success',
                    'cancel_url' => config('app.frontend_url') . '/employer/subscription/cancel',
                ]
            ]);

        if (!$response->successful()) {
            Log::error('PayPal create order error', [
                'employer' => $employer->id,
                'plan' => $plan->toArray(),
                'response' => $response->json()
            ]);
            throw new \Exception('Failed to create PayPal order');
        }

        $data = $response->json();
        $subscription = $this->createSubscriptionRecord($employer, $plan, [
            'id' => $data['id'],
            'type' => 'order'
        ]);

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

    protected function createRecurringSubscription(Employer $employer, SubscriptionPlan $plan, array $paymentData = []): array
    {
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
                'return_url' => config('app.frontend_url') . '/employer/subscription/success',
                'cancel_url' => config('app.frontend_url') . '/employer/subscription/cancel',
            ]
        ];

        $response = Http::withToken($this->getAccessToken())
            ->withHeaders(['PayPal-Request-Id' => Str::uuid()->toString()])
            ->post("{$this->baseUrl}/v1/billing/subscriptions", $subscriptionData);

        if (!$response->successful()) {
            Log::error('PayPal create subscription error', [
                'employer' => $employer->id,
                'plan' => $plan->toArray(),
                'response' => $response->json()
            ]);
            throw new \Exception('Failed to create PayPal subscription');
        }

        $data = $response->json();
        $subscription = $this->createSubscriptionRecord($employer, $plan, $data);

        if ($this->shouldUseTrial($employer, $plan)) {
            $employer->markTrialAsUsed();
        }

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
            'is_active' => false,
            'used_trial' => $this->shouldUseTrial($employer, $plan),
        ]);
    }

    public function updatePlan(SubscriptionPlan $plan, string $externalPlanId): bool
    {
        try {
            $response = Http::withToken($this->getAccessToken())
                ->patch("{$this->baseUrl}/v1/billing/plans/{$externalPlanId}", [
                    [
                        'op' => 'replace',
                        'path' => '/description',
                        'value' => $plan->description ?? $plan->name
                    ]
                ]);

            return $response->successful();
        } catch (\Exception $e) {
            Log::error('PayPal update plan error', [
                'plan' => $plan->toArray(),
                'externalPlanId' => $externalPlanId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function deletePlan(string $externalPlanId): bool
    {
        try {
            $response = Http::withToken($this->getAccessToken())
                ->post("{$this->baseUrl}/v1/billing/plans/{$externalPlanId}/deactivate");

            return $response->successful();
        } catch (\Exception $e) {
            Log::error('PayPal deactivate plan error', [
                'externalPlanId' => $externalPlanId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function listPlans(array $filters = []): array
    {
        try {
            $params = array_filter([
                'product_id' => $filters['product_id'] ?? null,
                'page_size' => $filters['page_size'] ?? 20,
                'page' => $filters['page'] ?? 1,
                'total_required' => 'true',
                'status' => $filters['status'] ?? 'ACTIVE',
            ]);

            $response = Http::withToken($this->getAccessToken())
                ->get("{$this->baseUrl}/v1/billing/plans", $params);

            return $response->successful() ? $response->json() : ['plans' => []];
        } catch (\Exception $e) {
            Log::error('PayPal list plans error', [
                'filters' => $filters,
                'error' => $e->getMessage()
            ]);
            return ['plans' => []];
        }
    }

    public function getPlanDetails(string $externalPlanId): array
    {
        try {
            $response = Http::withToken($this->getAccessToken())
                ->get("{$this->baseUrl}/v1/billing/plans/{$externalPlanId}");

            return $response->successful() ? $response->json() : [];
        } catch (\Exception $e) {
            Log::error('PayPal get plan details error', [
                'externalPlanId' => $externalPlanId,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    public function cancelSubscription(Subscription $subscription): bool
    {
        if (!$subscription->subscription_id) {
            return false;
        }

        try {
            $response = Http::withToken($this->getAccessToken())
                ->post("{$this->baseUrl}/v1/billing/subscriptions/{$subscription->subscription_id}/cancel", [
                    'reason' => 'Cancelled by user'
                ]);

            if ($response->successful()) {
                $subscription->update(['is_active' => false]);
                return true;
            }

            return false;
        } catch (\Exception $e) {
            Log::error('PayPal cancel subscription error', [
                'subscription' => $subscription->toArray(),
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function listSubscriptions(Employer $employer): array
    {
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

    public function getSubscriptionDetails(string $subscriptionId): array
    {
        try {
            $response = Http::withToken($this->getAccessToken())
                ->get("{$this->baseUrl}/v1/billing/subscriptions/{$subscriptionId}");

            return $response->successful() ? $response->json() : [];
        } catch (\Exception $e) {
            Log::error('PayPal get subscription details error', [
                'subscriptionId' => $subscriptionId,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    protected function verifyWebhookSignature(string $payload, array $headers): bool
    {
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
                ->post("{$this->baseUrl}/v1/notifications/verify-webhook-signature", [
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

    public function handleWebhook(string $payload, array $headers): bool
    {
        if (!$this->verifyWebhookSignature($payload, $headers)) {
            Log::warning('PayPal webhook signature verification failed');
            return false;
        }

        $data = json_decode($payload, true);
        $event = $data['event_type'] ?? '';
        $resourceId = $data['resource']['id'] ?? '';

        Log::info('PayPal webhook received', [
            'event' => $event,
            'resourceId' => $resourceId
        ]);

        return match ($event) {
            'BILLING.SUBSCRIPTION.ACTIVATED' => $this->handleSubscriptionActivated($data),
            'BILLING.SUBSCRIPTION.CANCELLED' => $this->handleSubscriptionCancelled($data),
            'CHECKOUT.ORDER.APPROVED' => $this->handleOrderApproved($data),
            'CHECKOUT.ORDER.COMPLETED' => $this->handleOrderCompleted($data),
            'PAYMENT.CAPTURE.COMPLETED' => $this->handlePaymentCaptureCompleted($data),
            default => true
        };
    }

    protected function handleSubscriptionActivated(array $data): bool
    {
        $subscriptionId = $data['resource']['id'] ?? '';

        $subscription = Subscription::where('subscription_id', $subscriptionId)
            ->where('payment_method', 'paypal')
            ->first();

        if (!$subscription) {
            Log::error('PayPal subscription not found', ['subscriptionId' => $subscriptionId]);
            return false;
        }

        $subscription->update(['is_active' => true]);
        $this->sendActivationNotification($subscription);

        return true;
    }

    protected function handleSubscriptionCancelled(array $data): bool
    {
        $subscriptionId = $data['resource']['id'] ?? '';

        $subscription = Subscription::where('subscription_id', $subscriptionId)
            ->where('payment_method', 'paypal')
            ->first();

        if (!$subscription) {
            Log::error('PayPal subscription not found', ['subscriptionId' => $subscriptionId]);
            return false;
        }

        $subscription->update(['is_active' => false]);
        return true;
    }

    protected function handleOrderApproved(array $data): bool
    {
        $orderId = $data['resource']['id'] ?? '';

        $subscription = Subscription::where('payment_reference', $orderId)
            ->where('payment_method', 'paypal')
            ->first();

        if (!$subscription) {
            Log::error('PayPal subscription not found for order', ['orderId' => $orderId]);
            return false;
        }

        $subscription->update(['external_status' => 'APPROVED']);
        return true;
    }

    protected function handleOrderCompleted(array $data): bool
    {
        $orderId = $data['resource']['id'] ?? '';

        $subscription = Subscription::where('payment_reference', $orderId)
            ->where('payment_method', 'paypal')
            ->first();

        if (!$subscription) {
            Log::error('PayPal subscription not found for order', ['orderId' => $orderId]);
            return false;
        }

        $subscription->update([
            'external_status' => 'COMPLETED',
            'is_active' => true
        ]);

        $this->sendActivationNotification($subscription);
        return true;
    }

    protected function handlePaymentCaptureCompleted(array $data): bool
    {
        // Handle both order and subscription payment captures
        $orderId = $data['resource']['supplementary_data']['related_ids']['order_id'] ?? null;

        if ($orderId) {
            $subscription = Subscription::where('payment_reference', $orderId)
                ->where('payment_method', 'paypal')
                ->first();

            if ($subscription) {
                $subscription->update([
                    'transaction_id' => $data['resource']['id'],
                    'is_active' => true
                ]);

                $this->sendActivationNotification($subscription);
                return true;
            }
        }

        return true;
    }

    private function sendActivationNotification(Subscription $subscription): void
    {
        try {
            $employer = $subscription->employer;
            if ($employer && $employer->user) {
                $employer->user->notify(new SubscriptionActivatedNotification($subscription));
            }
        } catch (\Exception $e) {
            Log::error('Failed to send PayPal subscription activation notification', [
                'subscription_id' => $subscription->id,
                'error' => $e->getMessage()
            ]);
        }
    }
}
