<?php

namespace App\Services\Payment;

use App\Models\Employer;
use App\Models\SubscriptionPlan;
use Illuminate\Http\UploadedFile;

/**
 * Interface for payment gateway implementations
 */
interface PaymentGatewayInterface
{
    /**
     * Process a payment
     *
     * @param Employer $employer
     * @param SubscriptionPlan $plan
     * @param array $paymentData
     * @return array
     */
    public function processPayment(Employer $employer, SubscriptionPlan $plan, array $paymentData): array;

    /**
     * Generate a payment link
     *
     * @param Employer $employer
     * @param SubscriptionPlan $plan
     * @param string $callbackUrl
     * @return array
     */
    public function generatePaymentLink(Employer $employer, SubscriptionPlan $plan, string $callbackUrl): array;

    /**
     * Verify a payment
     *
     * @param string $reference
     * @return array
     */
    public function verifyPayment(string $reference): array;

    /**
     * Cancel a subscription
     *
     * @param string $transactionId
     * @param string|null $reference
     * @return bool
     */
    public function cancelSubscription(string $transactionId, ?string $reference = null): bool;
}
