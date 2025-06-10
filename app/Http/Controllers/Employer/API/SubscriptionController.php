<?php

namespace App\Http\Controllers\Employer\API;

use App\Http\Controllers\Controller;
use App\Models\Employer;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class SubscriptionController extends Controller
{
    protected SubscriptionService $subscriptionService;

    public function __construct(SubscriptionService $subscriptionService)
    {
        $this->subscriptionService = $subscriptionService;
    }

    public function getPlans(): JsonResponse
    {
        $plans = SubscriptionPlan::where('is_active', true)
            ->orderBy('price')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $plans
        ]);
    }

    public function getActiveSubscription(): JsonResponse
    {
        $employer = $this->getEmployer();

        if (!$employer) {
            return response()->json([
                'success' => false,
                'message' => 'Employer profile not found'
            ], 404);
        }

        $activeSubscription = $employer->activeSubscription;

        if (!$activeSubscription) {
            return response()->json([
                'success' => true,
                'data' => null,
                'message' => 'No active subscription found'
            ]);
        }

        $activeSubscription->load('plan');

        return response()->json([
            'success' => true,
            'data' => [
                'subscription' => $activeSubscription,
                'status_text' => $activeSubscription->getStatusText(),
                'days_remaining' => $activeSubscription->daysRemaining(),
                'is_trial_eligible' => $employer->isEligibleForTrial(),
            ]
        ]);
    }

    public function getAllSubscriptions(): JsonResponse
    {
        $employer = $this->getEmployer();

        if (!$employer) {
            return response()->json([
                'success' => false,
                'message' => 'Employer profile not found'
            ], 404);
        }

        $subscriptions = $employer->subscriptions()
            ->with('plan')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $subscriptions
        ]);
    }

    public function subscribe(Request $request, SubscriptionPlan $plan): JsonResponse
    {
        $employer = $this->getEmployer();

        if (!$employer) {
            return response()->json([
                'success' => false,
                'message' => 'Employer profile not found'
            ], 404);
        }

        $request->validate([
            'payment_provider' => ['required', Rule::in(['stripe', 'paypal'])],
            'payment_data' => 'array',
            'return_url' => 'url',
            'cancel_url' => 'url',
        ]);

        if (!$plan->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'This plan is not available'
            ], 400);
        }

        $result = $this->subscriptionService->subscribe(
            $employer,
            $plan,
            $request->payment_provider,
            $request->payment_data ?? []
        );

        return response()->json($result, $result['success'] ? 200 : 400);
    }

    public function cancel(Request $request): JsonResponse
    {
        $employer = $this->getEmployer();

        if (!$employer) {
            return response()->json([
                'success' => false,
                'message' => 'Employer profile not found'
            ], 404);
        }

        $activeSubscription = $employer->activeSubscription;

        if (!$activeSubscription) {
            return response()->json([
                'success' => false,
                'message' => 'No active subscription found'
            ], 404);
        }

        $result = $this->subscriptionService->cancelSubscription($activeSubscription);

        return response()->json($result, $result['success'] ? 200 : 400);
    }

    public function suspendSubscription(Subscription $subscription): JsonResponse
    {
        $employer = $this->getEmployer();

        if (!$employer || $subscription->employer_id !== $employer->id) {
            return response()->json([
                'success' => false,
                'message' => 'Subscription not found or access denied'
            ], 404);
        }

        $result = $this->subscriptionService->suspendSubscription($subscription);

        return response()->json($result, $result['success'] ? 200 : 400);
    }

    public function reactivateSubscription(Subscription $subscription): JsonResponse
    {
        $employer = $this->getEmployer();

        if (!$employer || $subscription->employer_id !== $employer->id) {
            return response()->json([
                'success' => false,
                'message' => 'Subscription not found or access denied'
            ], 404);
        }

        $result = $this->subscriptionService->reactivateSubscription($subscription);

        return response()->json($result, $result['success'] ? 200 : 400);
    }

    public function verifyPayPalSubscription(Request $request): JsonResponse
    {
        $request->validate([
            'subscription_id' => 'required|string',
        ]);

        $result = $this->subscriptionService->verifyPayPalSubscription($request->all());

        return response()->json($result, $result['success'] ? 200 : 400);
    }

    public function verifyStripeSubscription(Request $request): JsonResponse
    {
        $request->validate([
            'subscription_id' => 'required|string',
        ]);

        $result = $this->subscriptionService->verifyStripeSubscription($request->all());

        return response()->json($result, $result['success'] ? 200 : 400);
    }

    public function handleStripeWebhook(Request $request): JsonResponse
    {
        try {
            $result = $this->subscriptionService->handleWebhook('stripe', $request->all());
            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function handlePayPalWebhook(Request $request): JsonResponse
    {
        try {
            $result = $this->subscriptionService->handleWebhook('paypal', $request->all());
            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    protected function getEmployer(): ?Employer
    {
        $user = Auth::user();

        if (!$user) {
            return null;
        }

        if ($user->isEmployer()) {
            return $user->employer;
        }

        if ($user->isEmployerStaff() && $user->employer_id) {
            return Employer::find($user->employer_id);
        }

        return null;
    }
}
