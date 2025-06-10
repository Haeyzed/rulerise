<?php

namespace App\Services\Payment;

use App\Models\Subscription;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PayPalService
{
    protected string $baseUrl;
    protected string $clientId;
    protected string $clientSecret;

    public function __construct()
    {
        $this->baseUrl = config('services.paypal.mode') === 'live'
            ? 'https://api.paypal.com'
            : 'https://api.sandbox.paypal.com';
        $this->clientId = config('services.paypal.client_id');
        $this->clientSecret = config('services.paypal.client_secret');
    }

    public function processPayment(Subscription $subscription, array $paymentData): array
    {
        try {
            $accessToken = $this->getAccessToken();

            if ($subscription->isRecurring()) {
                return $this->createRecurringSubscription($subscription, $accessToken, $paymentData);
            } else {
                return $this->createOneTimePayment($subscription, $accessToken, $paymentData);
            }

        } catch (Exception $e) {
            Log::error('PayPal payment failed', [
                'subscription_id' => $subscription->id,
                'error' => $e->getMessage()
            ]);

            throw new Exception('PayPal payment failed: ' . $e->getMessage());
        }
    }

    protected function createRecurringSubscription(Subscription $subscription, string $accessToken, array $paymentData): array
    {
        $plan = $subscription->plan;

        $productId = $this->createOrGetProduct($plan, $accessToken);
        $paypalPlanId = $this->createOrGetPlan($plan, $productId, $accessToken);

        $subscriptionData = [
            'plan_id' => $paypalPlanId,
            'subscriber' => [
                'name' => [
                    'given_name' => $subscription->employer->user->first_name,
                    'surname' => $subscription->employer->user->last_name,
                ],
                'email_address' => $subscription->employer->user->email,
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
                'return_url' => $paymentData['return_url'] ?? config('app.frontend_url') . '/employer/dashboard',
                'cancel_url' => $paymentData['cancel_url'] ?? config('app.frontend_url') . '/employer/dashboard',
            ],
        ];

        $response = Http::withToken($accessToken)
            ->post($this->baseUrl . '/v1/billing/subscriptions', $subscriptionData);

        if ($response->failed()) {
            throw new Exception('Failed to create PayPal subscription: ' . $response->body());
        }

        $responseData = $response->json();

        $subscription->update([
            'subscription_id' => $responseData['id'],
            'external_status' => $responseData['status'],
            'payment_reference' => $responseData['id'],
        ]);

        return [
            'status' => 'created',
            'subscription_id' => $responseData['id'],
            'approval_url' => collect($responseData['links'])->firstWhere('rel', 'approve')['href'] ?? null,
        ];
    }

    protected function createOneTimePayment(Subscription $subscription, string $accessToken, array $paymentData): array
    {
        $orderData = [
            'intent' => 'CAPTURE',
            'purchase_units' => [
                [
                    'reference_id' => 'subscription_' . $subscription->id,
                    'amount' => [
                        'currency_code' => strtoupper($subscription->currency),
                        'value' => number_format($subscription->plan->price, 2, '.', ''),
                    ],
                    'description' => $subscription->plan->name,
                ],
            ],
            'application_context' => [
                'brand_name' => config('app.name'),
                'locale' => 'en-US',
                'landing_page' => 'BILLING',
                'shipping_preference' => 'NO_SHIPPING',
                'user_action' => 'PAY_NOW',
                'return_url' => $paymentData['return_url'] ?? config('app.url') . '/subscription/success',
                'cancel_url' => $paymentData['cancel_url'] ?? config('app.url') . '/subscription/cancel',
            ],
        ];

        $response = Http::withToken($accessToken)
            ->post($this->baseUrl . '/v2/checkout/orders', $orderData);

        if ($response->failed()) {
            throw new Exception('Failed to create PayPal order: ' . $response->body());
        }

        $responseData = $response->json();

        $subscription->update([
            'transaction_id' => $responseData['id'],
            'external_status' => $responseData['status'],
            'payment_reference' => $responseData['id'],
        ]);

        return [
            'status' => 'created',
            'order_id' => $responseData['id'],
            'approval_url' => collect($responseData['links'])->firstWhere('rel', 'approve')['href'] ?? null,
        ];
    }

    public function cancelSubscription(Subscription $subscription): array
    {
        try {
            $accessToken = $this->getAccessToken();

            $response = Http::withToken($accessToken)
                ->post($this->baseUrl . "/v1/billing/subscriptions/{$subscription->subscription_id}/cancel", [
                    'reason' => 'User requested cancellation',
                ]);

            if ($response->failed()) {
                throw new Exception('Failed to cancel PayPal subscription: ' . $response->body());
            }

            return ['status' => 'cancelled'];

        } catch (Exception $e) {
            Log::error('PayPal cancellation failed', [
                'subscription_id' => $subscription->id,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    public function verifySubscription(array $data): array
    {
        try {
            $accessToken = $this->getAccessToken();
            $subscriptionId = $data['subscription_id'] ?? null;

            if (!$subscriptionId) {
                throw new Exception('Subscription ID is required');
            }

            $response = Http::withToken($accessToken)
                ->get($this->baseUrl . "/v1/billing/subscriptions/{$subscriptionId}");

            if ($response->failed()) {
                throw new Exception('Failed to verify PayPal subscription: ' . $response->body());
            }

            $subscriptionData = $response->json();

            $subscription = Subscription::where('subscription_id', $subscriptionId)->first();
            if ($subscription) {
                $subscription->update([
                    'external_status' => $subscriptionData['status'],
                    'status_update_time' => now(),
                ]);
            }

            return [
                'success' => true,
                'status' => $subscriptionData['status'],
                'subscription_data' => $subscriptionData,
            ];

        } catch (Exception $e) {
            Log::error('PayPal verification failed', [
                'data' => $data,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    public function handleWebhook(array $data): array
    {
        try {
            $eventType = $data['event_type'] ?? null;
            $resource = $data['resource'] ?? [];

            switch ($eventType) {
                case 'BILLING.SUBSCRIPTION.ACTIVATED':
                    return $this->handleSubscriptionActivated($resource);
                case 'BILLING.SUBSCRIPTION.CANCELLED':
                    return $this->handleSubscriptionCancelled($resource);
                case 'BILLING.SUBSCRIPTION.SUSPENDED':
                    return $this->handleSubscriptionSuspended($resource);
                case 'PAYMENT.SALE.COMPLETED':
                    return $this->handlePaymentCompleted($resource);
                default:
                    Log::info('Unhandled PayPal webhook event', ['event_type' => $eventType]);
                    return ['status' => 'ignored'];
            }

        } catch (Exception $e) {
            Log::error('PayPal webhook handling failed', [
                'data' => $data,
                'error' => $e->getMessage()
            ]);

            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    protected function getAccessToken(): string
    {
        $response = Http::asForm()
            ->withBasicAuth($this->clientId, $this->clientSecret)
            ->post($this->baseUrl . '/v1/oauth2/token', [
                'grant_type' => 'client_credentials',
            ]);

        if ($response->failed()) {
            throw new Exception('Failed to get PayPal access token');
        }

        return $response->json()['access_token'];
    }

    protected function createOrGetProduct($plan, string $accessToken): string
    {
        if ($plan->external_paypal_id) {
            return $plan->external_paypal_id;
        }

        $productData = [
            'name' => $plan->name,
            'description' => $plan->description,
            'type' => 'SERVICE',
            'category' => 'SOFTWARE',
        ];

        $response = Http::withToken($accessToken)
            ->post($this->baseUrl . '/v1/catalogs/products', $productData);

        if ($response->failed()) {
            throw new Exception('Failed to create PayPal product: ' . $response->body());
        }

        $productId = $response->json()['id'];

        $plan->update(['external_paypal_id' => $productId]);

        return $productId;
    }

    protected function createOrGetPlan($plan, string $productId, string $accessToken): string
    {
        $planData = [
            'product_id' => $productId,
            'name' => $plan->name,
            'description' => $plan->description,
            'billing_cycles' => [
                [
                    'frequency' => [
                        'interval_unit' => $plan->interval_unit,
                        'interval_count' => $plan->interval_count,
                    ],
                    'tenure_type' => 'REGULAR',
                    'sequence' => 1,
                    'total_cycles' => $plan->total_cycles ?: 0,
                    'pricing_scheme' => [
                        'fixed_price' => [
                            'value' => number_format($plan->price, 2, '.', ''),
                            'currency_code' => strtoupper($plan->currency),
                        ],
                    ],
                ],
            ],
            'payment_preferences' => [
                'auto_bill_outstanding' => true,
                'setup_fee_failure_action' => 'CONTINUE',
                'payment_failure_threshold' => 3,
            ],
        ];

        $response = Http::withToken($accessToken)
            ->post($this->baseUrl . '/v1/billing/plans', $planData);

        if ($response->failed()) {
            throw new Exception('Failed to create PayPal plan: ' . $response->body());
        }

        return $response->json()['id'];
    }

    protected function handleSubscriptionActivated(array $resource): array
    {
        $subscriptionId = $resource['id'] ?? null;

        if ($subscriptionId) {
            $subscription = Subscription::where('subscription_id', $subscriptionId)->first();
            if ($subscription) {
                $subscription->update([
                    'is_active' => true,
                    'external_status' => 'ACTIVE',
                    'status_update_time' => now(),
                ]);
            }
        }

        return ['status' => 'processed'];
    }

    protected function handleSubscriptionCancelled(array $resource): array
    {
        $subscriptionId = $resource['id'] ?? null;

        if ($subscriptionId) {
            $subscription = Subscription::where('subscription_id', $subscriptionId)->first();
            if ($subscription) {
                $subscription->update([
                    'is_active' => false,
                    'external_status' => 'CANCELLED',
                    'status_update_time' => now(),
                ]);
            }
        }

        return ['status' => 'processed'];
    }

    protected function handleSubscriptionSuspended(array $resource): array
    {
        $subscriptionId = $resource['id'] ?? null;

        if ($subscriptionId) {
            $subscription = Subscription::where('subscription_id', $subscriptionId)->first();
            if ($subscription) {
                $subscription->update([
                    'is_suspended' => true,
                    'external_status' => 'SUSPENDED',
                    'status_update_time' => now(),
                ]);
            }
        }

        return ['status' => 'processed'];
    }

    protected function handlePaymentCompleted(array $resource): array
    {
        Log::info('PayPal payment completed', ['resource' => $resource]);

        return ['status' => 'processed'];
    }
}
