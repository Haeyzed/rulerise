<?php

namespace App\Http\Controllers\Employer;

use App\Http\Controllers\Controller;
use App\Models\Employer;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Services\Subscription\SubscriptionServiceFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SubscriptionController extends Controller
{
    /**
     * Get all available subscription plans
     *
     * @return JsonResponse
     */
    public function getPlans()
    {
        $plans = SubscriptionPlan::where('is_active', true)->get();

        return response()->json([
            'success' => true,
            'data' => $plans
        ]);
    }

    /**
     * Get the active subscription for the authenticated employer
     *
     * @return JsonResponse
     */
    public function getActiveSubscription()
    {
        $employer = Auth::user()->employer;
        $subscription = $employer->activeSubscription;

        if (!$subscription) {
            return response()->json([
                'success' => false,
                'message' => 'No active subscription found'
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'subscription' => $subscription,
                'plan' => $subscription->plan
            ]
        ]);
    }

    /**
     * Subscribe to a plan
     *
     * @param Request $request
     * @param SubscriptionPlan $plan
     * @return JsonResponse
     */
    public function subscribe(Request $request, SubscriptionPlan $plan)
    {
        $employer = Auth::user()->employer;
        $provider = $request->input('payment_provider', 'stripe');

        try {
            $service = SubscriptionServiceFactory::create($provider);
            $result = $service->createSubscription($employer, $plan);

            return response()->json([
                'success' => true,
                'data' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cancel subscription
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function cancel(Request $request)
    {
        $employer = Auth::user()->employer;
        $subscription = $employer->activeSubscription;

        if (!$subscription) {
            return response()->json([
                'success' => false,
                'message' => 'No active subscription found'
            ], 404);
        }

        try {
            $service = SubscriptionServiceFactory::create($subscription->payment_method);
            $success = $service->cancelSubscription($subscription);

            if ($success) {
                return response()->json([
                    'success' => true,
                    'message' => 'Subscription cancelled successfully'
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to cancel subscription'
                ], 500);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Handle PayPal success callback
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function paypalSuccess(Request $request)
    {
        // The subscription is updated via webhook
        return response()->json([
            'success' => true,
            'message' => 'Subscription activated successfully'
        ]);
    }

    /**
     * Handle PayPal cancel callback
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function paypalCancel(Request $request)
    {
        return response()->json([
            'success' => false,
            'message' => 'Subscription process was cancelled'
        ]);
    }

    /**
     * Handle Stripe success callback
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function stripeSuccess(Request $request)
    {
        $sessionId = $request->get('session_id');

        if ($sessionId) {
            // Update the subscription status if needed
            $subscription = Subscription::where('payment_reference', $sessionId)
                ->where('payment_method', 'stripe')
                ->first();

            if ($subscription && !$subscription->is_active) {
                $subscription->is_active = true;
                $subscription->save();
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Subscription activated successfully'
        ]);
    }

    /**
     * Handle Stripe cancel callback
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function stripeCancel(Request $request)
    {
        return response()->json([
            'success' => false,
            'message' => 'Subscription process was cancelled'
        ]);
    }
}
