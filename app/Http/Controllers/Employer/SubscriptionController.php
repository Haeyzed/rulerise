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
use Illuminate\Support\Facades\Log;

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
     * List all plans from a payment provider
     *
     * @param Request $request
     * @param string $provider
     * @return JsonResponse
     */
    public function listProviderPlans(Request $request, string $provider)
    {
        try {
            $filters = $request->all();
            $service = SubscriptionServiceFactory::create($provider);
            $plans = $service->listPlans($filters);

            return response()->json([
                'success' => true,
                'data' => $plans
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get details of a specific plan
     *
     * @param string $provider
     * @param string $externalPlanId
     * @return JsonResponse
     */
    public function getPlanDetails(string $provider, string $externalPlanId)
    {
        try {
            $service = SubscriptionServiceFactory::create($provider);
            $planDetails = $service->getPlanDetails($externalPlanId);

            return response()->json([
                'success' => true,
                'data' => $planDetails
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get details of a specific subscription
     *
     * @param string $provider
     * @param string $subscriptionId
     * @return JsonResponse
     */
    public function getSubscriptionDetails(string $provider, string $subscriptionId)
    {
        try {
            $service = SubscriptionServiceFactory::create($provider);
            $subscriptionDetails = $service->getSubscriptionDetails($subscriptionId);

            return response()->json([
                'success' => true,
                'data' => $subscriptionDetails
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * List all subscriptions for the authenticated employer
     *
     * @param Request $request
     * @param string $provider
     * @return JsonResponse
     */
    public function listEmployerSubscriptions(Request $request, string $provider)
    {
        $employer = Auth::user()->employer;

        try {
            $service = SubscriptionServiceFactory::create($provider);
            $subscriptions = $service->listSubscriptions($employer);

            return response()->json([
                'success' => true,
                'data' => $subscriptions
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Suspend a subscription
     *
     * @param Request $request
     * @param Subscription $subscription
     * @return JsonResponse
     */
    public function suspendSubscription(Request $request, Subscription $subscription)
    {
        $employer = Auth::user()->employer;

        // Check if the subscription belongs to the employer
        if ($subscription->employer_id !== $employer->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        try {
            $service = SubscriptionServiceFactory::create($subscription->payment_method);
            $success = $service->suspendSubscription($subscription);

            if ($success) {
                return response()->json([
                    'success' => true,
                    'message' => 'Subscription suspended successfully'
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to suspend subscription'
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
     * Reactivate a suspended subscription
     *
     * @param Request $request
     * @param Subscription $subscription
     * @return JsonResponse
     */
    public function reactivateSubscription(Request $request, Subscription $subscription)
    {
        $employer = Auth::user()->employer;

        // Check if the subscription belongs to the employer
        if ($subscription->employer_id !== $employer->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        try {
            $service = SubscriptionServiceFactory::create($subscription->payment_method);
            $success = $service->reactivateSubscription($subscription);

            if ($success) {
                return response()->json([
                    'success' => true,
                    'message' => 'Subscription reactivated successfully'
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to reactivate subscription'
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
     * Get subscription transactions
     *
     * @param Request $request
     * @param string $provider
     * @param string $subscriptionId
     * @return JsonResponse
     */
    public function getSubscriptionTransactions(Request $request, string $provider, string $subscriptionId)
    {
        try {
            $service = SubscriptionServiceFactory::create($provider);

            // Check if the service has the method
            if (!method_exists($service, 'getSubscriptionTransactions')) {
                return response()->json([
                    'success' => false,
                    'message' => 'This feature is not supported by the selected payment provider'
                ], 400);
            }

            $transactions = $service->getSubscriptionTransactions($subscriptionId);

            return response()->json([
                'success' => true,
                'data' => $transactions
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update subscription plan
     *
     * @param Request $request
     * @param Subscription $subscription
     * @return JsonResponse
     */
    public function updateSubscriptionPlan(Request $request, Subscription $subscription)
    {
        $employer = Auth::user()->employer;

        // Check if the subscription belongs to the employer
        if ($subscription->employer_id !== $employer->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        // Validate request
        $request->validate([
            'plan_id' => 'required|exists:subscription_plans,id'
        ]);

        $newPlan = SubscriptionPlan::findOrFail($request->plan_id);

        try {
            $service = SubscriptionServiceFactory::create($subscription->payment_method);

            // Check if the service has the method
            if (!method_exists($service, 'updateSubscriptionPlan')) {
                return response()->json([
                    'success' => false,
                    'message' => 'This feature is not supported by the selected payment provider'
                ], 400);
            }

            $success = $service->updateSubscriptionPlan($subscription, $newPlan);

            if ($success) {
                return response()->json([
                    'success' => true,
                    'message' => 'Subscription plan updated successfully',
                    'data' => [
                        'subscription' => $subscription->fresh(),
                        'plan' => $newPlan
                    ]
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to update subscription plan'
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
        // Log the request parameters
        Log::info('PayPal success callback received', [
            'params' => $request->all()
        ]);

        // The subscription is updated via webhook, but we can check status here
        $subscriptionId = $request->get('subscription_id');

        if ($subscriptionId) {
            // Find the subscription in our database
            $subscription = Subscription::where('subscription_id', $subscriptionId)
                ->where('payment_method', 'paypal')
                ->first();

            if ($subscription) {
                // Log that we found the subscription
                Log::info('Found subscription for PayPal callback', [
                    'subscription_id' => $subscription->id,
                    'is_active' => $subscription->is_active
                ]);

                // We don't update the status here as it should be done by the webhook
                // But we can return the current status
                return response()->json([
                    'success' => true,
                    'message' => 'Subscription process completed',
                    'data' => [
                        'subscription_id' => $subscription->id,
                        'is_active' => $subscription->is_active
                    ]
                ]);
            }
        }

        // If we can't find the subscription, just return a generic success
        return response()->json([
            'success' => true,
            'message' => 'Subscription process completed. It may take a few moments to activate.'
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
        Log::info('PayPal cancel callback received', [
            'params' => $request->all()
        ]);

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

    /**
     * Manually verify a PayPal subscription
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function verifyPayPalSubscription(Request $request)
    {
        $subscriptionId = $request->input('subscription_id');

        if (!$subscriptionId) {
            return response()->json([
                'success' => false,
                'message' => 'Subscription ID is required'
            ], 400);
        }

        try {
            $employer = Auth::user()->employer;

            // Find the subscription
            $subscription = Subscription::where('subscription_id', $subscriptionId)
                ->where('employer_id', $employer->id)
                ->where('payment_method', 'paypal')
                ->first();

            if (!$subscription) {
                // Try to find by subscription_id only, in case it was created for this employer
                $subscription = Subscription::where('subscription_id', $subscriptionId)
                    ->where('payment_method', 'paypal')
                    ->first();

                if (!$subscription || ($subscription->employer_id !== $employer->id)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Subscription not found'
                    ], 404);
                }
            }

            // Get subscription details from PayPal
            $service = SubscriptionServiceFactory::create('paypal');
            $details = $service->getSubscriptionDetails($subscriptionId);

            // Log the details for debugging
            Log::info('PayPal subscription details', [
                'subscription_id' => $subscriptionId,
                'details' => $details
            ]);

            // If subscription is active in PayPal but not in our database
            if (isset($details['status']) && ($details['status'] === 'ACTIVE' || $details['status'] === 'APPROVED') && !$subscription->is_active) {
                $subscription->is_active = true;
                $subscription->save();

                Log::info('Subscription activated via manual verification', [
                    'subscription_id' => $subscription->id,
                    'paypal_id' => $subscriptionId
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Subscription activated successfully',
                    'data' => [
                        'subscription' => $subscription->fresh(),
                        'plan' => $subscription->plan
                    ]
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Subscription status checked',
                'data' => [
                    'subscription' => $subscription,
                    'plan' => $subscription->plan,
                    'paypal_status' => $details['status'] ?? 'UNKNOWN'
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error verifying PayPal subscription', [
                'subscription_id' => $subscriptionId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error verifying subscription: ' . $e->getMessage()
            ], 500);
        }
    }
}
