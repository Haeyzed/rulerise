<?php

namespace App\Services\Subscription;

use App\Models\Employer;
use App\Models\OldSubscription;
use App\Models\SubscriptionPlan;
use App\Notifications\SubscriptionActivatedNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PayPalSubscriptionService
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
        $productId = $this->createProduct($plan);

        $response = Http::withToken($this->getAccessToken())
            ->withHeaders(['PayPal-Request-Id' => Str::uuid()->toString()])
            ->post("{$this->baseUrl}/v1/billing/plans", [
                'product_id' => $productId,
                'name' => $plan->name,
                'description' => $plan->description ?? $plan->name,
                'status' => 'ACTIVE',
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

    public function createSubscription(Employer $employer, SubscriptionPlan $plan): array
    {
        // Create or get existing plan
        $externalPlanId = $plan->external_paypal_id ?? $this->createPlan($plan);

        if (!$plan->external_paypal_id) {
            $plan->update(['external_paypal_id' => $externalPlanId]);
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

        // Create subscription record
        $endDate = null;
        if ($plan->duration_days && $plan->isRecurring()) {
            $endDate = Carbon::now()->addDays($plan->duration_days);
            if ($plan->hasTrial() && !$employer->has_used_trial) {
                $endDate->addDays($plan->getTrialPeriodDays());
            }
        }

        $subscription = OldSubscription::create([
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
            'used_trial' => $plan->hasTrial() && !$employer->has_used_trial,
            'external_status' => $data['status'],
        ]);

        // Mark trial as used if applicable
        if ($plan->hasTrial() && !$employer->has_used_trial) {
            $employer->markTrialAsUsed();
        }

        $approvalUrl = collect($data['links'])
            ->firstWhere('rel', 'approve')['href'] ?? null;

        return [
            'subscription_id' => $subscription->id,
            'external_subscription_id' => $data['id'],
            'redirect_url' => $approvalUrl,
            'status' => $data['status'],
            'payment_type' => $plan->payment_type
        ];
    }

    public function getSubscriptionDetails(string $subscriptionId): array
    {
        try {
            $response = Http::withToken($this->getAccessToken())
                ->get("{$this->baseUrl}/v1/billing/subscriptions/{$subscriptionId}");

            if ($response->successful()) {
                $details = $response->json();

                // Extract next billing date if available
                if (isset($details['billing_info']['next_billing_time'])) {
                    $details['next_billing_date'] = $details['billing_info']['next_billing_time'];
                }

                return $details;
            }

            return [];
        } catch (\Exception $e) {
            Log::error('PayPal get subscription details error', [
                'subscriptionId' => $subscriptionId,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    public function cancelSubscription(string $subscriptionId, string $reason = 'Cancelled by user'): bool
    {
        try {
            $response = Http::withToken($this->getAccessToken())
                ->post("{$this->baseUrl}/v1/billing/subscriptions/{$subscriptionId}/cancel", [
                    'reason' => $reason
                ]);

            return $response->successful();
        } catch (\Exception $e) {
            Log::error('PayPal cancel subscription error', [
                'subscriptionId' => $subscriptionId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function suspendSubscription(string $subscriptionId, string $reason = 'Suspended by user'): bool
    {
        try {
            $response = Http::withToken($this->getAccessToken())
                ->post("{$this->baseUrl}/v1/billing/subscriptions/{$subscriptionId}/suspend", [
                    'reason' => $reason
                ]);

            return $response->successful();
        } catch (\Exception $e) {
            Log::error('PayPal suspend subscription error', [
                'subscriptionId' => $subscriptionId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function activateSubscription(string $subscriptionId, string $reason = 'Reactivating the subscription'): bool
    {
        try {
            $response = Http::withToken($this->getAccessToken())
                ->post("{$this->baseUrl}/v1/billing/subscriptions/{$subscriptionId}/activate", [
                    'reason' => $reason
                ]);

            return $response->successful();
        } catch (\Exception $e) {
            Log::error('PayPal activate subscription error', [
                'subscriptionId' => $subscriptionId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function handleWebhook(string $payload, array $headers): bool
    {
        // Verify webhook signature if needed
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
            'BILLING.SUBSCRIPTION.CREATED' => $this->handleSubscriptionCreated($data),
            'BILLING.SUBSCRIPTION.ACTIVATED' => $this->handleSubscriptionActivated($data),
            'BILLING.SUBSCRIPTION.CANCELLED' => $this->handleSubscriptionCancelled($data),
            'BILLING.SUBSCRIPTION.SUSPENDED' => $this->handleSubscriptionSuspended($data),
            'BILLING.SUBSCRIPTION.PAYMENT.FAILED' => $this->handleSubscriptionPaymentFailed($data),
            'PAYMENT.SALE.COMPLETED' => $this->handlePaymentSaleCompleted($data),
            default => true
        };
    }

    protected function verifyWebhookSignature(string $payload, array $headers): bool
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

    protected function handleSubscriptionActivated(array $data): bool
    {
        $subscriptionId = $data['resource']['id'] ?? '';

        $subscription = OldSubscription::where('subscription_id', $subscriptionId)
            ->where('payment_method', 'paypal')
            ->first();

        if (!$subscription) {
            Log::error('PayPal subscription not found', ['subscriptionId' => $subscriptionId]);
            return false;
        }

        $subscription->is_active = true;
        $subscription->external_status = 'ACTIVE';

        // Extract and save next billing date if available
        if (isset($data['resource']['billing_info']['next_billing_time'])) {
            $subscription->next_billing_date = Carbon::parse($data['resource']['billing_info']['next_billing_time']);
        }

        // Save billing info for future reference
        if (isset($data['resource']['billing_info'])) {
            $subscription->billing_info = $data['resource']['billing_info'];
        }

        $subscription->save();

        // Send notification
        $this->sendActivationNotification($subscription);

        return true;
    }

    protected function handleSubscriptionCancelled(array $data): bool
    {
        $subscriptionId = $data['resource']['id'] ?? '';

        $subscription = OldSubscription::where('subscription_id', $subscriptionId)
            ->where('payment_method', 'paypal')
            ->first();

        if (!$subscription) {
            Log::error('PayPal subscription not found', ['subscriptionId' => $subscriptionId]);
            return false;
        }

        $subscription->is_active = false;
        $subscription->external_status = 'CANCELLED';
        $subscription->save();

        return true;
    }

    protected function handleSubscriptionSuspended(array $data): bool
    {
        $subscriptionId = $data['resource']['id'] ?? '';

        $subscription = OldSubscription::where('subscription_id', $subscriptionId)
            ->where('payment_method', 'paypal')
            ->first();

        if (!$subscription) {
            Log::error('PayPal subscription not found', ['subscriptionId' => $subscriptionId]);
            return false;
        }

        $subscription->update([
            'is_suspended' => true,
            'external_status' => 'SUSPENDED'
        ]);

        return true;
    }

    protected function handleSubscriptionCreated(array $data): bool
    {
        $subscriptionId = $data['resource']['id'] ?? '';

        $subscription = OldSubscription::where('subscription_id', $subscriptionId)
            ->where('payment_method', 'paypal')
            ->first();

        if (!$subscription) {
            Log::error('PayPal subscription not found', ['subscriptionId' => $subscriptionId]);
            return false;
        }

        $subscription->external_status = $data['resource']['status'] ?? $subscription->external_status;
        $subscription->save();

        return true;
    }

    protected function handleSubscriptionPaymentFailed(array $data): bool
    {
        $subscriptionId = $data['resource']['id'] ?? '';

        $subscription = OldSubscription::where('subscription_id', $subscriptionId)
            ->where('payment_method', 'paypal')
            ->first();

        if (!$subscription) {
            Log::error('PayPal subscription not found', ['subscriptionId' => $subscriptionId]);
            return false;
        }

        $subscription->update([
            'external_status' => 'PAYMENT_FAILED',
            'status_update_time' => Carbon::now()
        ]);

        return true;
    }

    protected function handlePaymentSaleCompleted(array $data): bool
    {
        $subscriptionId = $data['resource']['billing_agreement_id'] ?? null;

        if ($subscriptionId) {
            $subscription = OldSubscription::where('subscription_id', $subscriptionId)
                ->where('payment_method', 'paypal')
                ->first();

            if ($subscription) {
                $subscription->transaction_id = $data['resource']['id'];

                if (!$subscription->is_active) {
                    $subscription->is_active = true;
                    $subscription->external_status = 'ACTIVE';
                    $this->sendActivationNotification($subscription);
                }

                $subscription->save();
                return true;
            }
        }

        return true;
    }

    private function sendActivationNotification(OldSubscription $subscription): void
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
