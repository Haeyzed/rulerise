<?php

namespace App\Services\Payment;

use App\Models\Employer;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use Exception;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PayPalGateway implements PaymentGatewayInterface
{
    /**
     * PayPal API base URL
     *
     * @var string
     */
    protected string $baseUrl;

    /**
     * PayPal access token
     *
     * @var string|null
     */
    protected ?string $accessToken = null;

    /**
     * Constructor
     * @throws Exception
     */
    public function __construct()
    {
        $this->baseUrl = config('services.paypal.sandbox')
            ? 'https://api-m.sandbox.paypal.com'
            : 'https://api-m.paypal.com';

        $this->getAccessToken();
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

        $response = Http::withBasicAuth(
            config('services.paypal.client_id'),
            config('services.paypal.secret')
        )->asForm()->post("{$this->baseUrl}/v1/oauth2/token", [
            'grant_type' => 'client_credentials'
        ]);

        if ($response->successful()) {
            $this->accessToken = $response->json('access_token');
            return $this->accessToken;
        }

        Log::error('Failed to get PayPal access token: ' . $response->body());
        throw new Exception('Failed to authenticate with PayPal');
    }

    /**
     * Create a payment intent/order
     *
     * @param Employer $employer
     * @param SubscriptionPlan $plan
     * @param array $paymentData
     * @return array
     * @throws Exception
     */
    public function createPaymentIntent(Employer $employer, SubscriptionPlan $plan, array $paymentData): array
    {
        try {
            $response = Http::withToken($this->getAccessToken())
                ->post("{$this->baseUrl}/v2/checkout/orders", [
                    'intent' => 'CAPTURE',
                    'purchase_units' => [
                        [
                            'reference_id' => "plan_{$plan->id}_employer_{$employer->id}",
                            'description' => "Subscription to {$plan->name} plan",
                            'amount' => [
                                'currency_code' => strtoupper($plan->currency),
                                'value' => number_format($plan->price, 2, '.', ''),
                            ],
                            'custom_id' => json_encode([
                                'employer_id' => $employer->id,
                                'plan_id' => $plan->id,
                                'user_id' => $employer->user_id,
                            ]),
                        ],
                    ],
                    'application_context' => [
                        'return_url' => config('app.frontend_url') . '/employer/dashboard',
                        'cancel_url' => config('app.frontend_url') . '/employer/dashboard',
                    ],
                ]);

            if (!$response->successful()) {
                Log::error('PayPal order creation failed: ' . $response->body());
                throw new Exception('Failed to create PayPal order');
            }

            $data = $response->json();

            return [
                'order_id' => $data['id'],
                'approval_url' => collect($data['links'])
                    ->firstWhere('rel', 'approve')['href'],
                'provider' => $this->getProviderName(),
            ];
        } catch (Exception $e) {
            Log::error('PayPal order creation failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Process a successful payment
     *
     * @param array $paymentData
     * @return Subscription
     * @throws Exception
     */
    public function processPayment(array $paymentData): Subscription
    {
        try {
            $orderId = $paymentData['order_id'];

            // Capture the payment
            $response = Http::withToken($this->getAccessToken())
                ->post("{$this->baseUrl}/v2/checkout/orders/{$orderId}/capture");

            if (!$response->successful()) {
                Log::error('PayPal payment capture failed: ' . $response->body());
                throw new Exception('Failed to capture PayPal payment');
            }

            $data = $response->json();

            if ($data['status'] !== 'COMPLETED') {
                throw new Exception("Payment has not completed. Current status: {$data['status']}");
            }

            // Extract custom data
            $customData = json_decode($data['purchase_units'][0]['custom_id'], true);
            $employerId = $customData['employer_id'];
            $planId = $customData['plan_id'];

            $employer = Employer::findOrFail($employerId);
            $plan = SubscriptionPlan::findOrFail($planId);

            // Calculate dates
            $startDate = now();
            $endDate = $startDate->copy()->addDays($plan->duration_days);

            // Create subscription
            return $employer->subscriptions()->create([
                'subscription_plan_id' => $plan->id,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'amount_paid' => $plan->price,
                'currency' => $plan->currency,
                'payment_method' => 'paypal',
                'transaction_id' => $data['id'],
                'payment_reference' => $data['purchase_units'][0]['payments']['captures'][0]['id'],
                'job_posts_left' => $plan->job_posts_limit,
                'featured_jobs_left' => $plan->featured_jobs_limit,
                'cv_downloads_left' => $plan->resume_views_limit,
                'is_active' => true,
            ]);
        } catch (Exception $e) {
            Log::error('PayPal payment processing failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Handle webhook events from PayPal
     *
     * @param array $payload
     * @return bool
     */
    public function handleWebhook(array $payload): bool
    {
        try {
            // Verify webhook signature
            $headers = request()->header();
            $webhookId = config('services.paypal.webhook_id');

            // In a real implementation, you would verify the webhook signature
            // using PayPal's SDK or API

            $event = $payload['event_type'];

            switch ($event) {
                case 'PAYMENT.CAPTURE.COMPLETED':
                    $this->handlePaymentCompleted($payload['resource']);
                    break;
                case 'PAYMENT.CAPTURE.DENIED':
                    $this->handlePaymentDenied($payload['resource']);
                    break;
                // Handle other event types as needed
            }

            return true;
        } catch (Exception $e) {
            Log::error('PayPal webhook handling failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Handle completed payment
     *
     * @param array $resource
     * @return void
     */
    protected function handlePaymentCompleted(array $resource): void
    {
        // This could update subscription status or send notifications
        Log::info('PayPal payment completed: ' . $resource['id']);
    }

    /**
     * Handle denied payment
     *
     * @param array $resource
     * @return void
     */
    protected function handlePaymentDenied(array $resource): void
    {
        // This could update subscription status or send notifications
        Log::info('PayPal payment denied: ' . $resource['id']);
    }

    /**
     * Cancel a subscription
     *
     * @param Subscription $subscription
     * @return bool
     */
    public function cancelSubscription(Subscription $subscription): bool
    {
        // For one-time payments, just mark as inactive
        $subscription->is_active = false;
        return $subscription->save();

        // For recurring subscriptions, you would cancel in PayPal first
        // then update the local record
    }

    /**
     * Get payment provider name
     *
     * @return string
     */
    public function getProviderName(): string
    {
        return 'paypal';
    }
}
