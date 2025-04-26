<?php

namespace App\Services\Payment;

use App\Models\Employer;
use App\Models\SubscriptionPlan;
use Exception;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Service for PayPal payment gateway
 */
class PayPalService implements PaymentGatewayInterface
{
    /**
     * PayPal API base URL
     *
     * @var string
     */
    protected string $baseUrl;

    /**
     * PayPal client ID
     *
     * @var string
     */
    protected string $clientId;

    /**
     * PayPal client secret
     *
     * @var string
     */
    protected string $clientSecret;

    /**
     * PayPal access token
     *
     * @var string|null
     */
    protected ?string $accessToken = null;

    /**
     * PayPalService constructor
     */
    public function __construct()
    {
        $this->clientId = config('services.paypal.client_id');
        $this->clientSecret = config('services.paypal.client_secret');
        $this->baseUrl = config('services.paypal.sandbox')
            ? 'https://api-m.sandbox.paypal.com'
            : 'https://api-m.paypal.com';
    }

    /**
     * Process a payment
     *
     * @param Employer $employer
     * @param SubscriptionPlan $plan
     * @param array $paymentData
     * @return array
     */
    public function processPayment(Employer $employer, SubscriptionPlan $plan, array $paymentData): array
    {
        try {
            if (isset($paymentData['paypal_order_id'])) {
                $orderId = $paymentData['paypal_order_id'];

                // Capture the order
                $response = $this->getAuthenticatedRequest()
                    ->post("{$this->baseUrl}/v2/checkout/orders/{$orderId}/capture");

                if ($response->successful()) {
                    $data = $response->json();

                    if ($data['status'] === 'COMPLETED') {
                        return [
                            'success' => true,
                            'transaction_id' => $data['id'],
                            'payment_reference' => $data['purchase_units'][0]['payments']['captures'][0]['id'] ?? null,
                            'message' => 'Payment processed successfully'
                        ];
                    }
                }
            }

            return [
                'success' => false,
                'message' => 'Invalid PayPal payment data'
            ];
        } catch (Exception $e) {
            Log::error('PayPal payment error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'PayPal payment failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Generate a payment link
     *
     * @param Employer $employer
     * @param SubscriptionPlan $plan
     * @param string $callbackUrl
     * @return array
     */
    public function generatePaymentLink(Employer $employer, SubscriptionPlan $plan, string $callbackUrl): array
    {
        try {
            // Create an order
            $response = $this->getAuthenticatedRequest()
                ->post("{$this->baseUrl}/v2/checkout/orders", [
                    'intent' => 'CAPTURE',
                    'purchase_units' => [
                        [
                            'reference_id' => 'plan_' . $plan->id,
                            'description' => $plan->name,
                            'custom_id' => json_encode([
                                'employer_id' => $employer->id,
                                'plan_id' => $plan->id
                            ]),
                            'amount' => [
                                'currency_code' => $plan->currency,
                                'value' => number_format($plan->price, 2, '.', '')
                            ]
                        ]
                    ],
                    'application_context' => [
                        'return_url' => $callbackUrl,
                        'cancel_url' => $callbackUrl . '?canceled=true'
                    ]
                ]);

            if ($response->successful()) {
                $data = $response->json();

                // Find the approval URL
                $approvalUrl = null;
                foreach ($data['links'] as $link) {
                    if ($link['rel'] === 'approve') {
                        $approvalUrl = $link['href'];
                        break;
                    }
                }

                if ($approvalUrl) {
                    return [
                        'success' => true,
                        'payment_link' => $approvalUrl,
                        'order_id' => $data['id'],
                        'message' => 'PayPal payment link generated successfully'
                    ];
                }
            }

            return [
                'success' => false,
                'message' => 'Failed to generate PayPal payment link'
            ];
        } catch (Exception $e) {
            Log::error('PayPal payment link error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to generate PayPal payment link: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Verify a payment
     *
     * @param string $orderId
     * @return array
     */
    public function verifyPayment(string $orderId): array
    {
        try {
            $response = $this->getAuthenticatedRequest()
                ->get("{$this->baseUrl}/v2/checkout/orders/{$orderId}");

            if ($response->successful()) {
                $data = $response->json();

                if ($data['status'] === 'COMPLETED' || $data['status'] === 'APPROVED') {
                    // Extract metadata from custom_id
                    $metadata = [];
                    if (isset($data['purchase_units'][0]['custom_id'])) {
                        $metadata = json_decode($data['purchase_units'][0]['custom_id'], true) ?? [];
                    }

                    return [
                        'success' => true,
                        'transaction_id' => $data['id'],
                        'payment_reference' => $data['purchase_units'][0]['reference_id'] ?? null,
                        'metadata' => $metadata,
                        'message' => 'Payment verified successfully'
                    ];
                }
            }

            return [
                'success' => false,
                'message' => 'Payment not completed or not found'
            ];
        } catch (Exception $e) {
            Log::error('PayPal payment verification error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to verify PayPal payment: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Cancel a subscription
     *
     * @param string $subscriptionId
     * @return bool
     */
    public function cancelSubscription(string $subscriptionId): bool
    {
        try {
            if (!$subscriptionId) {
                return true; // Nothing to cancel
            }

            $response = $this->getAuthenticatedRequest()
                ->post("{$this->baseUrl}/v1/billing/subscriptions/{$subscriptionId}/cancel");

            return $response->successful();
        } catch (Exception $e) {
            Log::error('Failed to cancel PayPal subscription: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get an authenticated HTTP request
     *
     * @return PendingRequest
     */
    protected function getAuthenticatedRequest(): PendingRequest
    {
        if (!$this->accessToken) {
            $this->getAccessToken();
        }

        return Http::withToken($this->accessToken)
            ->withHeaders([
                'Content-Type' => 'application/json'
            ]);
    }

    /**
     * Get an access token from PayPal
     *
     * @return void
     * @throws ConnectionException
     * @throws Exception
     */
    protected function getAccessToken(): void
    {
        $response = Http::withBasicAuth($this->clientId, $this->clientSecret)
            ->asForm()
            ->post("{$this->baseUrl}/v1/oauth2/token", [
                'grant_type' => 'client_credentials'
            ]);

        if ($response->successful()) {
            $this->accessToken = $response->json('access_token');
        } else {
            Log::error('Failed to get PayPal access token: ' . $response->body());
            throw new Exception('Failed to authenticate with PayPal');
        }
    }
}
