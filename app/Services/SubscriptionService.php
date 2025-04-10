<?php

namespace App\Services;

use App\Models\Employer;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Services\Payment\FlutterwaveService;
use App\Services\Payment\PaymentGatewayInterface;
use App\Services\Payment\PaystackService;
use App\Services\Payment\StripeService;
use App\Services\Storage\StorageService;
use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

/**
 * Service class for subscription related operations
 */
class SubscriptionService
{
    /**
     * @var StorageService
     */
    protected StorageService $storageService;

    /**
     * @var PaymentGatewayInterface
     */
    protected PaymentGatewayInterface $paymentGateway;

    /**
     * Supported payment gateways
     */
    const GATEWAY_STRIPE = 'stripe';
    const GATEWAY_PAYSTACK = 'paystack';
    const GATEWAY_FLUTTERWAVE = 'flutterwave';

    /**
     * SubscriptionService constructor.
     *
     * @param StorageService $storageService
     */
    public function __construct(StorageService $storageService)
    {
        $this->storageService = $storageService;

        // Set default payment gateway from config
        $defaultGateway = config('services.payment.default_gateway', self::GATEWAY_STRIPE);
        $this->setPaymentGateway($defaultGateway);
    }

    /**
     * Set the payment gateway to use
     *
     * @param string $gateway
     * @return self
     * @throws Exception
     */
    public function setPaymentGateway(string $gateway): self
    {
        switch ($gateway) {
            case self::GATEWAY_STRIPE:
                $this->paymentGateway = app(StripeService::class);
                break;

            case self::GATEWAY_PAYSTACK:
                $this->paymentGateway = app(PaystackService::class);
                break;

            case self::GATEWAY_FLUTTERWAVE:
                $this->paymentGateway = app(FlutterwaveService::class);
                break;

            default:
                throw new Exception("Unsupported payment gateway: {$gateway}");
        }

        return $this;
    }

    /**
     * Subscribe to a plan using the specified payment gateway
     *
     * @param Employer $employer
     * @param SubscriptionPlan $plan
     * @param array $paymentData
     * @param string $gateway
     * @param UploadedFile|null $receiptFile
     * @return Subscription
     * @throws Exception
     */
    public function subscribeToPlan(
        Employer $employer,
        SubscriptionPlan $plan,
        array $paymentData,
        string $gateway = self::GATEWAY_STRIPE,
        ?UploadedFile $receiptFile = null
    ): Subscription {
        // Set the payment gateway if different from current
        if ($gateway !== $this->getCurrentGateway()) {
            $this->setPaymentGateway($gateway);
        }

        // Process payment
        $paymentResult = $this->paymentGateway->processPayment($employer, $plan, $paymentData);

        if (!$paymentResult['success']) {
            throw new Exception($paymentResult['message'] ?? 'Payment failed');
        }

        // Upload receipt if provided
        $receiptPath = null;
        if ($receiptFile) {
            $receiptPath = $this->uploadReceipt($receiptFile);
        }

        // Calculate dates
        $startDate = now();
        $endDate = $startDate->copy()->addDays($plan->duration_days);

        // Create subscription
        $subscription = $employer->subscriptions()->create([
            'subscription_plan_id' => $plan->id,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'amount_paid' => $plan->price,
            'currency' => $plan->currency,
            'payment_method' => $gateway,
            'transaction_id' => $paymentResult['transaction_id'] ?? null,
            'payment_reference' => $paymentResult['payment_reference'] ?? null,
            'receipt_path' => $receiptPath,
            'job_posts_left' => $plan->job_posts,
            'featured_jobs_left' => $plan->featured_jobs,
            'cv_downloads_left' => $plan->cv_downloads,
            'is_active' => true,
        ]);

        // Deactivate previous active subscription if exists
        $this->deactivatePreviousSubscriptions($employer, $subscription->id);

        return $subscription;
    }

    /**
     * Update an existing subscription
     *
     * @param Subscription $subscription
     * @param SubscriptionPlan $newPlan
     * @param array $paymentData
     * @param string $gateway
     * @param UploadedFile|null $receiptFile
     * @return Subscription
     * @throws Exception
     */
    public function updateSubscription(
        Subscription $subscription,
        SubscriptionPlan $newPlan,
        array $paymentData,
        string $gateway = self::GATEWAY_STRIPE,
        ?UploadedFile $receiptFile = null
    ): Subscription {
        $employer = $subscription->employer;

        // Set the payment gateway if different from current
        if ($gateway !== $this->getCurrentGateway()) {
            $this->setPaymentGateway($gateway);
        }

        // Process payment for the upgrade/downgrade
        $paymentResult = $this->paymentGateway->processPayment($employer, $newPlan, $paymentData);

        if (!$paymentResult['success']) {
            throw new Exception($paymentResult['message'] ?? 'Payment failed');
        }

        // Upload receipt if provided
        $receiptPath = $subscription->receipt_path;
        if ($receiptFile) {
            // Delete old receipt if exists
            if ($subscription->receipt_path) {
                $this->storageService->delete($subscription->receipt_path);
            }
            $receiptPath = $this->uploadReceipt($receiptFile);
        }

        // Calculate new end date based on remaining days and new plan duration
        $remainingDays = now()->diffInDays($subscription->end_date, false);
        $remainingDays = max(0, $remainingDays); // Ensure it's not negative

        $startDate = now();
        $endDate = $startDate->copy()->addDays($newPlan->duration_days + $remainingDays);

        // Update subscription
        $subscription->update([
            'subscription_plan_id' => $newPlan->id,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'amount_paid' => $newPlan->price,
            'currency' => $newPlan->currency,
            'payment_method' => $gateway,
            'transaction_id' => $paymentResult['transaction_id'] ?? $subscription->transaction_id,
            'payment_reference' => $paymentResult['payment_reference'] ?? $subscription->payment_reference,
            'receipt_path' => $receiptPath,
            'job_posts_left' => $newPlan->job_posts,
            'featured_jobs_left' => $newPlan->featured_jobs,
            'cv_downloads_left' => $newPlan->cv_downloads,
            'is_active' => true,
        ]);

        return $subscription;
    }

    /**
     * Cancel a subscription
     *
     * @param Subscription $subscription
     * @return bool
     */
    public function cancelSubscription(Subscription $subscription): bool
    {
        // Set the payment gateway based on the subscription's payment method
        $this->setPaymentGateway($subscription->payment_method);

        // For subscriptions with external payment providers, we might need to cancel on their end too
        if (in_array($subscription->payment_method, [self::GATEWAY_STRIPE, self::GATEWAY_PAYSTACK, self::GATEWAY_FLUTTERWAVE])) {
            $this->paymentGateway->cancelSubscription(
                $subscription->transaction_id,
                $subscription->payment_reference
            );
        }

        // Mark as inactive but don't delete the record
        return $subscription->update([
            'is_active' => false,
            'end_date' => now(), // End immediately
        ]);
    }

    /**
     * Generate payment link for a subscription
     *
     * @param Employer $employer
     * @param SubscriptionPlan $plan
     * @param string $gateway
     * @param string $callbackUrl
     * @return array
     * @throws Exception
     */
    public function generatePaymentLink(
        Employer $employer,
        SubscriptionPlan $plan,
        string $gateway = self::GATEWAY_STRIPE,
        string $callbackUrl = ''
    ): array {
        // Set the payment gateway if different from current
        if ($gateway !== $this->getCurrentGateway()) {
            $this->setPaymentGateway($gateway);
        }

        return $this->paymentGateway->generatePaymentLink($employer, $plan, $callbackUrl);
    }

    /**
     * Verify payment for a subscription
     *
     * @param string $reference
     * @param string $gateway
     * @return array
     * @throws Exception
     */
    public function verifyPayment(string $reference, string $gateway = self::GATEWAY_STRIPE): array
    {
        // Set the payment gateway if different from current
        if ($gateway !== $this->getCurrentGateway()) {
            $this->setPaymentGateway($gateway);
        }

        return $this->paymentGateway->verifyPayment($reference);
    }

    /**
     * Get the current payment gateway
     *
     * @return string
     */
    protected function getCurrentGateway(): string
    {
        if ($this->paymentGateway instanceof StripeService) {
            return self::GATEWAY_STRIPE;
        } elseif ($this->paymentGateway instanceof PaystackService) {
            return self::GATEWAY_PAYSTACK;
        } elseif ($this->paymentGateway instanceof FlutterwaveService) {
            return self::GATEWAY_FLUTTERWAVE;
        }

        return config('services.payment.default_gateway', self::GATEWAY_STRIPE);
    }

    /**
     * Upload receipt file
     *
     * @param UploadedFile $file
     * @return string
     */
    protected function uploadReceipt(UploadedFile $file): string
    {
        return $this->storageService->upload(
            $file,
            config('filestorage.paths.receipts', 'receipts'),
            ['visibility' => 'private']
        );
    }

    /**
     * Deactivate previous active subscriptions
     *
     * @param Employer $employer
     * @param int $exceptId
     * @return void
     */
    protected function deactivatePreviousSubscriptions(Employer $employer, int $exceptId): void
    {
        $employer->subscriptions()
            ->where('id', '!=', $exceptId)
            ->where('is_active', true)
            ->update(['is_active' => false]);
    }
}
