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
            return response()->success(null, 'No active subscription found');
        }

        return response()->success([
                'subscription' => $subscription,
                'plan' => $subscription->plan
            ], 'Active subscription retrieved successfully');
    }

    /**
     * Create one-time payment
     */
    public function createOneTimePayment(CreatePaymentRequest $request): JsonResponse
    {
        $employer = Auth::user()->employer;
        $plan = Plan::findOrFail($request->plan_id);

        if (!$plan->isOneTime()) {
            return response()->json([
                'success' => false,
                'message' => 'This plan is not available for one-time payment'
            ], 400);
        }

        $result = match ($request->payment_provider) {
            'stripe' => $this->stripeService->createOneTimePayment($employer, $plan),
            'paypal' => $this->paypalService->createOneTimePayment($employer, $plan),
            default => ['success' => false, 'error' => 'Invalid payment provider']
        };

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['error']
            ], 400);
        }

        return response()->json([
            'success' => true,
            'data' => $result
        ]);
    }

    /**
     * Create subscription
     */
    public function createSubscription(CreateSubscriptionRequest $request): JsonResponse
    {
        $employer = Auth::user()->employer;
        $plan = Plan::findOrFail($request->plan_id);

        if (!$plan->isRecurring()) {
            return response()->json([
                'success' => false,
                'message' => 'This plan is not available for subscription'
            ], 400);
        }

        // Check if employer already has active subscription
        if ($employer->hasActiveSubscription()) {
            return response()->json([
                'success' => false,
                'message' => 'You already have an active subscription'
            ], 400);
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
            'data' => $responseData
        ]);
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
            'data' => $subscriptions
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
            'data' => $payments
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
            'data' => $result
        ]);
    }
}
