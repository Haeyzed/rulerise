<?php

namespace App\Services\Payment;

use App\Models\Employer;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use Exception;
use Illuminate\Support\Facades\Log;

class PaymentService
{
    /**
     * Get payment service for the specified provider
     *
     * @param string $provider
     * @return PaymentGatewayInterface
     * @throws Exception
     */
    public function getPaymentService(string $provider): PaymentGatewayInterface
    {
        return match (strtolower($provider)) {
            'stripe' => app(StripeGateway::class),
            'paypal' => app(PayPalGateway::class),
            default => throw new Exception("Unsupported payment provider: {$provider}"),
        };
    }

    /**
     * Create a payment intent/order
     *
     * @param Employer $employer
     * @param SubscriptionPlan $plan
     * @param string $provider
     * @param array $paymentData
     * @return array
     * @throws Exception
     */
    public function createPaymentIntent(
        Employer $employer,
        SubscriptionPlan $plan,
        string $provider,
        array $paymentData = []
    ): array {
        try {
            $paymentService = $this->getPaymentService($provider);
            return $paymentService->createPaymentIntent($employer, $plan, $paymentData);
        } catch (Exception $e) {
            Log::error("Failed to create payment intent with {$provider}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Process a successful payment
     *
     * @param string $provider
     * @param array $paymentData
     * @return Subscription
     * @throws Exception
     */
    public function processPayment(string $provider, array $paymentData): Subscription
    {
        try {
            $paymentService = $this->getPaymentService($provider);
            return $paymentService->processPayment($paymentData);
        } catch (Exception $e) {
            Log::error("Failed to process payment with {$provider}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Handle webhook events from payment provider
     *
     * @param string $provider
     * @param array $payload
     * @return bool
     */
    public function handleWebhook(string $provider, array $payload): bool
    {
        try {
            $paymentService = $this->getPaymentService($provider);
            return $paymentService->handleWebhook($payload);
        } catch (Exception $e) {
            Log::error("Failed to handle webhook from {$provider}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Cancel a subscription
     *
     * @param Subscription $subscription
     * @return bool
     * @throws Exception
     */
    public function cancelSubscription(Subscription $subscription): bool
    {
        try {
            $provider = $subscription->payment_method;
            $paymentService = $this->getPaymentService($provider);
            return $paymentService->cancelSubscription($subscription);
        } catch (Exception $e) {
            Log::error("Failed to cancel subscription with {$subscription->payment_method}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get available payment providers
     *
     * @return array
     */
    public function getAvailableProviders(): array
    {
        return [
            'stripe' => 'Credit Card (Stripe)',
            'paypal' => 'PayPal',
        ];
    }
}
