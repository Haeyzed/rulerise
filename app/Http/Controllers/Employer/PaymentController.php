<?php

namespace App\Http\Controllers\Employer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Employer\CreatePaymentRequest;
use App\Http\Requests\Employer\CreateSubscriptionRequest;
use App\Models\Plan;
use App\Models\Subscription;
use App\Notifications\SubscriptionCreated;
use App\Services\Payment\PayPalPaymentService;
use App\Services\Payment\StripePaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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
     * Create one-time payment
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

        // Handle upgrade validation if this is an upgrade
        if ($request->is_upgrade) {
            $validationResult = $this->validateUpgrade($employer, $plan);
            if (!$validationResult['valid']) {
                return response()->json([
                    'success' => false,
                    'message' => $validationResult['message']
                ], 400);
            }
        }

        // Check if employer has used trial period for one-time payments
        if (!$employer->has_used_trial && $plan->hasTrial()) {
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

        // Handle upgrade if this is an upgrade
        if ($request->is_upgrade) {
            $this->handleUpgrade($employer);
        }

        return response()->json([
            'success' => true,
            'data' => $result,
            'message' => 'Payment created successfully'
        ]);
    }

    /**
     * Create subscription
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

        // Handle upgrade validation if this is an upgrade
        if ($request->is_upgrade) {
            $validationResult = $this->validateUpgrade($employer, $plan);
            if (!$validationResult['valid']) {
                return response()->json([
                    'success' => false,
                    'message' => $validationResult['message']
                ], 400);
            }
        } else {
            // Check if employer already has active subscription (only for new subscriptions, not upgrades)
            if ($employer->hasActiveSubscription()) {
                return response()->json([
                    'success' => false,
                    'message' => 'You already have an active subscription. Use upgrade option to change plans.'
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

        // Handle upgrade if this is an upgrade
        if ($request->is_upgrade) {
            $this->handleUpgrade($employer);
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
     * Validate upgrade request
     */
    private function validateUpgrade($employer, $newPlan): array
    {
        $activeSubscription = $employer->activeSubscription;

        if (!$activeSubscription) {
            return [
                'valid' => false,
                'message' => 'No active subscription found to upgrade from'
            ];
        }

        // Check if trying to upgrade to the same plan
        if ($activeSubscription->plan_id === $newPlan->id) {
            return [
                'valid' => false,
                'message' => 'You are already subscribed to this plan. Please select a different plan to upgrade.'
            ];
        }

        // Optional: Add price comparison validation
//        if ($newPlan->price <= $activeSubscription->plan->price) {
//            return [
//                'valid' => false,
//                'message' => 'You can only upgrade to a higher-priced plan. This appears to be a downgrade.'
//            ];
//        }

        return ['valid' => true];
    }

    /**
     * Handle upgrade by managing existing subscription
     */
    private function handleUpgrade($employer): void
    {
        $activeSubscription = $employer->activeSubscription;

        if ($activeSubscription) {
            // TODO: Uncomment one of the following based on business decision

            // Option 1: Cancel existing subscription
            // $this->cancelExistingSubscription($activeSubscription);

            // Option 2: Suspend existing subscription
            // $this->suspendExistingSubscription($activeSubscription);
        }
    }

    /**
     * Cancel existing subscription during upgrade
     */
    private function cancelExistingSubscription(Subscription $subscription): void
    {
        $success = match ($subscription->payment_provider) {
            'stripe' => $this->stripeService->cancelSubscription($subscription),
            'paypal' => $this->paypalService->cancelSubscription($subscription),
            default => false
        };

        if ($success) {
            \Log::info('Existing subscription cancelled during upgrade', [
                'subscription_id' => $subscription->id,
                'employer_id' => $subscription->employer_id
            ]);
        } else {
            \Log::error('Failed to cancel existing subscription during upgrade', [
                'subscription_id' => $subscription->id,
                'employer_id' => $subscription->employer_id
            ]);
        }
    }

    /**
     * Suspend existing subscription during upgrade
     */
    private function suspendExistingSubscription(Subscription $subscription): void
    {
        $success = match ($subscription->payment_provider) {
            'stripe' => $this->stripeService->suspendSubscription($subscription),
            'paypal' => $this->paypalService->suspendSubscription($subscription),
            default => false
        };

        if ($success) {
            \Log::info('Existing subscription suspended during upgrade', [
                'subscription_id' => $subscription->id,
                'employer_id' => $subscription->employer_id
            ]);
        } else {
            \Log::error('Failed to suspend existing subscription during upgrade', [
                'subscription_id' => $subscription->id,
                'employer_id' => $subscription->employer_id
            ]);
        }
    }

    /**
     * Create trial subscription for one-time payment plans
     */
    private function createTrialSubscription($employer, $plan, $paymentProvider): array
    {
        // Mark trial as used
        $employer->markTrialAsUsed();

        // Create trial subscription using the subscription method
        $result = match ($paymentProvider) {
            'stripe' => $this->stripeService->createSubscription($employer, $plan),
            'paypal' => $this->paypalService->createSubscription($employer, $plan),
            default => ['success' => false, 'error' => 'Invalid payment provider']
        };

        if ($result['success']) {
            \Log::info('Trial subscription created for one-time plan', [
                'employer_id' => $employer->id,
                'plan_id' => $plan->id,
                'trial_days' => $plan->getTrialPeriodDays()
            ]);
        }

        return $result;
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
