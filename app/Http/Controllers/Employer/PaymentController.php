<?php

namespace App\Http\Controllers\Employer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Employer\CreatePaymentRequest;
use App\Http\Requests\Employer\CreateSubscriptionRequest;
use App\Models\Employer;
use App\Models\Plan;
use App\Models\Subscription;
use App\Notifications\SubscriptionCreated;
use App\Services\Payment\PayPalPaymentService;
use App\Services\Payment\StripePaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    public function __construct(
        private StripePaymentService $stripeService,
        private PayPalPaymentService $paypalService
    ) {}

    /**
     * Get available plans
     */
    public function getPlans(): JsonResponse
    {
        $plans = Plan::active()->orderBy('price')->get();

        return response()->json([
            'success' => true,
            'data' => $plans
        ]);
    }

    public function getActiveSubscription(): JsonResponse
    {
        $employer = Auth::user()->employer;
        $subscription = $employer->activeSubscription;

        if (!$subscription) {
            return response()->json([
                'success' => true,
                'data' => null,
                'message' => 'No active subscription found'
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'subscription' => $subscription,
                'plan' => $subscription->plan
            ],
            'message' => 'Active subscription retrieved successfully'
        ]);
    }

    /**
     * Create one-time payment with trial period checking
     */
    public function createOneTimePayment(CreatePaymentRequest $request): JsonResponse
    {
        $employer = Auth::user()->employer;
        $plan = Plan::query()->findOrFail($request->plan_id);

        if (!$plan->isOneTime()) {
            return response()->json([
                'success' => false,
                'message' => 'This plan is not available for one-time payment'
            ], 400);
        }

        // Check if this is an upgrade request
        if ($request->boolean('is_upgrade')) {
            $this->handleUpgrade($employer);
        }

        // Check if employer has used trial period
        if (!$employer->hasUsedTrial() && $plan->hasTrial()) {
            // Create trial subscription instead of payment
            $result = $this->createTrialSubscription($employer, $plan, $request->payment_provider);
        } else {
            // Proceed with regular one-time payment
            $result = match ($request->payment_provider) {
                'stripe' => $this->stripeService->createOneTimePayment($employer, $plan),
                'paypal' => $this->paypalService->createOneTimePayment($employer, $plan),
                default => ['success' => false, 'error' => 'Invalid payment provider']
            };
        }

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['error']
            ], 400);
        }

        return response()->json([
            'success' => true,
            'data' => $result,
            'message' => 'Payment created successfully'
        ]);
    }

    /**
     * Create subscription with upgrade handling
     */
    public function createSubscription(CreateSubscriptionRequest $request): JsonResponse
    {
        $employer = Auth::user()->employer;
        $plan = Plan::query()->findOrFail($request->plan_id);

        if (!$plan->isRecurring()) {
            return response()->json([
                'success' => false,
                'message' => 'This plan is not available for subscription'
            ], 400);
        }

        // Check if this is an upgrade request
        if ($request->boolean('is_upgrade')) {
            $this->handleUpgrade($employer);
        } else {
            // Check if employer already has active subscription (only for new subscriptions)
            if ($employer->hasActiveSubscription()) {
                return response()->json([
                    'success' => false,
                    'message' => 'You already have an active subscription'
                ], 400);
            }
        }

        $result = match ($request->payment_provider) {
            'stripe' => $this->stripeService->createSubscription($employer, $plan),
            'paypal' => $this->paypalService->createSubscription($employer, $plan),
            default => ['success' => false, 'error' => 'Invalid payment provider']
        };

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['error']
            ], 400);
        }

        // Send subscription created notification
        if (isset($result['subscription']) && $result['subscription'] instanceof Subscription) {
            $employer->notify(new SubscriptionCreated($result['subscription']));
        }

        // Format the response to be consistent between payment providers
        $responseData = [
            'success' => true,
            'subscription_id' => $result['subscription']->id,
            'approval_url' => $result['approval_url'] ?? null,
            'is_trial' => $result['is_trial'] ?? false,
        ];

        // Add provider-specific data
        if ($request->payment_provider === 'stripe') {
            $responseData['checkout_session_id'] = $result['checkout_session_id'] ?? null;
        } else {
            $responseData['paypal_subscription_id'] = $result['subscription_id'] ?? null;
        }

        return response()->json([
            'success' => true,
            'data' => $responseData,
            'message' => 'Subscription created successfully'
        ]);
    }

    /**
     * Handle upgrade logic - cancel or suspend existing subscription
     */
    private function handleUpgrade(Employer $employer): void
    {
        $activeSubscription = $employer->activeSubscription()->first();

        if ($activeSubscription) {
            // TODO: Ask boss which action to use - cancel or suspend
            // Option 1: Cancel existing subscription
             $this->cancelExistingSubscription($activeSubscription);

            // Option 2: Suspend existing subscription
            // $this->suspendExistingSubscription($activeSubscription);

            Log::info('Upgrade requested - existing subscription found', [
                'employer_id' => $employer->id,
                'existing_subscription_id' => $activeSubscription->id,
                'existing_plan_id' => $activeSubscription->plan_id,
            ]);
        }
    }

    /**
     * Cancel existing subscription for upgrade
     */
    private function cancelExistingSubscription(Subscription $subscription): bool
    {
        try {
            $success = match ($subscription->payment_provider) {
                'stripe' => $this->stripeService->cancelSubscription($subscription),
                'paypal' => $this->paypalService->cancelSubscription($subscription),
                default => false
            };

            if ($success) {
                Log::info('Existing subscription cancelled for upgrade', [
                    'subscription_id' => $subscription->id,
                    'employer_id' => $subscription->employer_id,
                ]);
            }

            return $success;
        } catch (\Exception $e) {
            Log::error('Failed to cancel existing subscription for upgrade', [
                'subscription_id' => $subscription->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Suspend existing subscription for upgrade
     */
    private function suspendExistingSubscription(Subscription $subscription): bool
    {
        try {
            $success = match ($subscription->payment_provider) {
                'stripe' => $this->stripeService->suspendSubscription($subscription),
                'paypal' => $this->paypalService->suspendSubscription($subscription),
                default => false
            };

            if ($success) {
                Log::info('Existing subscription suspended for upgrade', [
                    'subscription_id' => $subscription->id,
                    'employer_id' => $subscription->employer_id,
                ]);
            }

            return $success;
        } catch (\Exception $e) {
            Log::error('Failed to suspend existing subscription for upgrade', [
                'subscription_id' => $subscription->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Create trial subscription for first-time users
     */
    private function createTrialSubscription(Employer $employer, Plan $plan, string $paymentProvider): array
    {
        try {
            // Mark trial as used
            $employer->markTrialAsUsed();

            // Create trial subscription
            $trialStart = now();
            $trialEnd = now()->addDays($plan->getTrialPeriodDays());

            $subscription = Subscription::create([
                'employer_id' => $employer->id,
                'plan_id' => $plan->id,
                'subscription_id' => 'trial_' . uniqid(),
                'payment_provider' => $paymentProvider,
                'status' => 'trialing',
                'amount' => $plan->price,
                'currency' => $plan->getCurrencyCode(),
                'start_date' => $trialStart,
                'end_date' => $trialEnd,
                'next_billing_date' => $trialEnd,
                'trial_start_date' => $trialStart,
                'trial_end_date' => $trialEnd,
                'is_trial' => true,
                'trial_ended' => false,
                'cv_downloads_left' => $plan->resume_views_limit,
                'metadata' => [
                    'trial_created' => true,
                    'original_plan_id' => $plan->id,
                ],
                'is_active' => true,
            ]);

            return [
                'success' => true,
                'subscription' => $subscription,
                'is_trial' => true,
                'trial_end_date' => $trialEnd,
                'message' => 'Trial subscription created successfully'
            ];
        } catch (\Exception $e) {
            Log::error('Failed to create trial subscription', [
                'employer_id' => $employer->id,
                'plan_id' => $plan->id,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => 'Failed to create trial subscription: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Cancel subscription
     */
    public function cancelSubscription(Request $request): JsonResponse
    {
        $employer = Auth::user()->employer;
        $subscription = $employer->activeSubscription()->first();

        if (!$subscription) {
            return response()->json([
                'success' => false,
                'message' => 'No active subscription found'
            ], 404);
        }

        $success = match ($subscription->payment_provider) {
            'stripe' => $this->stripeService->cancelSubscription($subscription),
            'paypal' => $this->paypalService->cancelSubscription($subscription),
            default => false
        };

        if (!$success) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel subscription'
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Subscription cancelled successfully'
        ]);
    }

    /**
     * Suspend subscription
     */
    public function suspendSubscription(Request $request): JsonResponse
    {
        $employer = Auth::user()->employer;
        $subscription = $employer->activeSubscription()->first();

        if (!$subscription) {
            return response()->json([
                'success' => false,
                'message' => 'No active subscription found'
            ], 404);
        }

        $success = match ($subscription->payment_provider) {
            'stripe' => $this->stripeService->suspendSubscription($subscription),
            'paypal' => $this->paypalService->suspendSubscription($subscription),
            default => false
        };

        if (!$success) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to suspend subscription'
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Subscription suspended successfully'
        ]);
    }

    /**
     * Resume subscription
     */
    public function resumeSubscription(Request $request): JsonResponse
    {
        $employer = Auth::user()->employer;
        $subscriptionId = $request->input('subscription_id');

        $subscription = $employer->subscriptions()
            ->where('id', $subscriptionId)
            ->where('status', 'suspended')
            ->first();

        if (!$subscription) {
            return response()->json([
                'success' => false,
                'message' => 'No suspended subscription found'
            ], 404);
        }

        $success = match ($subscription->payment_provider) {
            'stripe' => $this->stripeService->resumeSubscription($subscription),
            'paypal' => $this->paypalService->resumeSubscription($subscription),
            default => false
        };

        if (!$success) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to resume subscription'
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Subscription resumed successfully'
        ]);
    }

    /**
     * Get employer's subscriptions
     */
    public function getSubscriptions(): JsonResponse
    {
        $employer = Auth::user()->employer;
        $subscriptions = $employer->subscriptions()
            ->with('plan')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $subscriptions,
            'message' => 'Subscriptions retrieved successfully'
        ]);
    }

    /**
     * Get employer's payments
     */
    public function getPayments(): JsonResponse
    {
        $employer = Auth::user()->employer;
        $payments = $employer->payments()
            ->with('plan')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $payments,
            'message' => 'Payments retrieved successfully'
        ]);
    }

    /**
     * Capture PayPal payment
     */
    public function capturePayPalPayment(Request $request): JsonResponse
    {
        $request->validate([
            'order_id' => 'required|string'
        ]);

        $result = $this->paypalService->capturePayment($request->order_id);

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['error']
            ], 400);
        }

        return response()->json([
            'success' => true,
            'data' => $result,
            'message' => 'Payment captured successfully'
        ]);
    }
}
