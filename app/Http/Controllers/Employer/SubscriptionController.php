<?php

namespace App\Http\Controllers\Employer;

use App\Http\Controllers\Controller;
use App\Models\Employer;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Notifications\SubscriptionActivatedNotification;
use App\Services\Subscription\SubscriptionServiceFactory;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SubscriptionController extends Controller
{
    /**
     * Get all available subscription plans
     *
     * @return JsonResponse
     */
    public function getPlans(): JsonResponse
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
    public function getActiveSubscription(): JsonResponse
    {
        $employer = Auth::user()->employer;
        $subscription = $employer->activeSubscription;

        if (!$subscription) {
            return response()->success(null,'No active subscription found');
        }

        return response()->success([
            'subscription' => $subscription,
            'plan' => $subscription->plan
        ], 'Active subscription retrieved successfully');
    }

    /**
     * Subscribe to a plan
     *
     * @param Request $request
     * @param SubscriptionPlan $plan
     * @return JsonResponse
     */
    public function subscribe(Request $request, SubscriptionPlan $plan): JsonResponse
    {
        $employer = Auth::user()->employer;
        $provider = $request->input('payment_provider', 'paypal');

        try {
            $service = SubscriptionServiceFactory::create($provider);
            $result = $service->createSubscription($employer, $plan);

            return response()->success($result, 'Subscription created successfully');
        } catch (Exception $e) {
            return response()->serverError($e->getMessage());
        }
    }

    /**
     * Cancel subscription
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function cancel(Request $request): JsonResponse
    {
        $employer = Auth::user()->employer;
        $subscription = $employer->activeSubscription;

        if (!$subscription) {
            return response()->notFound('No active subscription found');
        }

        try {
            $service = SubscriptionServiceFactory::create($subscription->payment_method);
            $success = $service->cancelSubscription($subscription);

            return response()->success(null, 'Subscription cancelled successfully');
        } catch (Exception $e) {
            return response()->serverError($e->getMessage());
        }
    }

    /**
     * List all plans from a payment provider
     *
     * @param Request $request
     * @param string $provider
     * @return JsonResponse
     */
    public function listProviderPlans(Request $request, string $provider): JsonResponse
    {
        try {
            $filters = $request->all();
            $service = SubscriptionServiceFactory::create($provider);
            $plans = $service->listPlans($filters);

            return response()->success($plans, 'Plans retrieved successfully');
        } catch (Exception $e) {
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
    public function getPlanDetails(string $provider, string $externalPlanId): JsonResponse
    {
        try {
            $service = SubscriptionServiceFactory::create($provider);
            $planDetails = $service->getPlanDetails($externalPlanId);

            return response()->success($planDetails, 'Plan details retrieved successfully');
        } catch (Exception $e) {
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
    public function getSubscriptionDetails(string $provider, string $subscriptionId): JsonResponse
    {
        try {
            $service = SubscriptionServiceFactory::create($provider);
            $subscriptionDetails = $service->getSubscriptionDetails($subscriptionId);

            return response()->success($subscriptionDetails, 'Subscription details retrieved successfully');
        } catch (Exception $e) {
            return response()->serverError($e->getMessage());
        }
    }

    /**
     * List all subscriptions for the authenticated employer
     *
     * @param Request $request
     * @param string $provider
     * @return JsonResponse
     */
    public function listEmployerSubscriptions(Request $request, string $provider): JsonResponse
    {
        $employer = Auth::user()->employer;

        try {
            $service = SubscriptionServiceFactory::create($provider);
            $subscriptions = $service->listSubscriptions($employer);

            return response()->success($subscriptions, 'Employer subscription retrieved successfully');
        } catch (Exception $e) {
            return response()->serverError($e->getMessage());
        }
    }

    /**
     * Suspend a subscription
     *
     * @param Request $request
     * @param Subscription $subscription
     * @return JsonResponse
     */
    public function suspendSubscription(Request $request, Subscription $subscription): JsonResponse
    {
        $employer = Auth::user()->employer;

        // Check if the subscription belongs to the employer
        if ($subscription->employer_id !== $employer->id) {
            return response()->forbidden('Unauthorized');
        }

        // One-time subscriptions can't be suspended
        if ($subscription->isOneTime()) {
            return response()->badRequest('One-time subscriptions cannot be suspended');
        }

        try {
            $service = SubscriptionServiceFactory::create($subscription->payment_method);
            $success = $service->suspendSubscription($subscription);

            if ($success) {
                return response()->success(null, 'Subscription suspended successfully');
            } else {
                return response()->success('Subscription already suspended');
            }
        } catch (Exception $e) {
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
    public function reactivateSubscription(Request $request, Subscription $subscription): JsonResponse
    {
        $employer = Auth::user()->employer;

        // Check if the subscription belongs to the employer
        if ($subscription->employer_id !== $employer->id) {
            return response()->forbidden('Unauthorized');
        }

        // One-time subscriptions can't be reactivated
        if ($subscription->isOneTime()) {
            return response()->badRequest('One-time subscriptions cannot be reactivated');
        }

        try {
            $service = SubscriptionServiceFactory::create($subscription->payment_method);
            $success = $service->reactivateSubscription($subscription);

            if ($success) {
                return response()->success(null, 'Subscription reactivated successfully');
            } else {
                return response()->serverError('Failed to reactivate subscription');
            }
        } catch (Exception $e) {
            return response()->serverError($e->getMessage());
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
    public function getSubscriptionTransactions(Request $request, string $provider, string $subscriptionId): JsonResponse
    {
        try {
            $service = SubscriptionServiceFactory::create($provider);

            // Check if the service has the method
            if (!method_exists($service, 'getSubscriptionTransactions')) {
                return response()->badRequest('This feature is not supported by the selected payment provider');
            }

            $transactions = $service->getSubscriptionTransactions($subscriptionId);

            return response()->success($transactions, 'Transactions retrieved successfully');
        } catch (Exception $e) {
            return response()->serverError($e->getMessage());
        }
    }

    /**
     * Update subscription plan
     *
     * @param Request $request
     * @param Subscription $subscription
     * @return JsonResponse
     */
    public function updateSubscriptionPlan(Request $request, Subscription $subscription): JsonResponse
    {
        $employer = Auth::user()->employer;

        // Check if the subscription belongs to the employer
        if ($subscription->employer_id !== $employer->id) {
            return response()->forbidden('Unauthorized');
        }

        // One-time subscriptions can't be updated
        if ($subscription->isOneTime()) {
            return response()->badRequest('One-time subscriptions cannot be updated to a different plan');
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
                return response()->badRequest('This feature is not supported by the selected payment provider');
            }

            $success = $service->updateSubscriptionPlan($subscription, $newPlan);

            if ($success) {
                return response()->success([
                        'subscription' => $subscription->fresh(),
                        'plan' => $newPlan
                    ],'Subscription plan updated successfully');
            } else {
                return response()->serverError('Failed to update subscription plan');
            }
        } catch (Exception $e) {
            return response()->serverError($e->getMessage());
        }
    }

    /**
     * Handle PayPal success callback
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function paypalSuccess(Request $request): JsonResponse
    {
        // Log the request parameters
        Log::info('PayPal success callback received', [
            'params' => $request->all()
        ]);

        // The subscription is updated via webhook, but we can check status here
        $subscriptionId = $request->get('subscription_id');
        $orderId = $request->get('order_id'); // For one-time payments

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
                return response()->success([
                        'subscription_id' => $subscription->id,
                        'is_active' => $subscription->is_active
                    ], 'Subscription process completed');
            }
        } elseif ($orderId) {
            // Find the one-time payment subscription
            $subscription = Subscription::where('payment_reference', $orderId)
                ->where('payment_method', 'paypal')
                ->where('payment_type', SubscriptionPlan::PAYMENT_TYPE_ONE_TIME)
                ->first();

            if ($subscription) {
                Log::info('Found one-time subscription for PayPal callback', [
                    'subscription_id' => $subscription->id,
                    'is_active' => $subscription->is_active
                ]);

                return response()->success([
                        'subscription_id' => $subscription->id,
                        'is_active' => $subscription->is_active
                    ],'One-time payment process completed');
            }
        }

        // If we can't find the subscription, just return a generic success
        return response()->success(null,'Subscription process completed. It may take a few moments to activate.');
    }

    /**
     * Handle PayPal cancel callback
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function paypalCancel(Request $request): JsonResponse
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
    public function stripeSuccess(Request $request): JsonResponse
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

                // Send notification
                try {
                    $employer = $subscription->employer;
                    if ($employer && $employer->user) {
                        $employer->user->notify(new SubscriptionActivatedNotification($subscription));
                    }
                } catch (\Exception $e) {
                    Log::error('Failed to send subscription activation notification during Stripe success', [
                        'subscription_id' => $subscription->id,
                        'error' => $e->getMessage()
                    ]);
                }
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
    public function stripeCancel(Request $request): JsonResponse
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
    public function verifyPayPalSubscription(Request $request): JsonResponse
    {
        $subscriptionId = $request->input('subscription_id');
        $orderId = $request->input('order_id'); // For one-time payments

        if (!$subscriptionId && !$orderId) {
            return response()->json([
                'success' => false,
                'message' => 'Subscription ID or Order ID is required'
            ], 400);
        }

        try {
            $employer = Auth::user()->employer;
            $service = SubscriptionServiceFactory::create('paypal');

            if ($subscriptionId) {
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
                $details = $service->getSubscriptionDetails($subscriptionId);

                // Log the details for debugging
                Log::info('PayPal subscription details', [
                    'subscription_id' => $subscriptionId,
                    'details' => $details
                ]);

                // Update subscription with PayPal details
                $service->updateSubscriptionWithPayPalDetails($subscription, $details);

                // If subscription is active in PayPal but not in our database
                if (isset($details['status']) && ($details['status'] === 'ACTIVE' || $details['status'] === 'APPROVED') && !$subscription->is_active) {
                    $subscription->is_active = true;
                    $subscription->save();

                    // Send notification
                    try {
                        $employer = $subscription->employer;
                        if ($employer && $employer->user) {
                            $employer->user->notify(new SubscriptionActivatedNotification($subscription));
                        }
                    } catch (\Exception $e) {
                        Log::error('Failed to send subscription activation notification during verification', [
                            'subscription_id' => $subscription->id,
                            'error' => $e->getMessage()
                        ]);
                    }

                    Log::info('Subscription activated via manual verification', [
                        'subscription_id' => $subscription->id,
                        'paypal_id' => $subscriptionId
                    ]);

                    return response()->success([
                        'subscription' => $subscription->fresh(),
                        'plan' => $subscription->plan
                    ],'Subscription activated successfully');
                }

                return response()->success([
                    'subscription' => $subscription,
                    'plan' => $subscription->plan,
                    'paypal_status' => $details['status'] ?? 'UNKNOWN'
                ],'Subscription status checked');
            } elseif ($orderId) {
                // Find the one-time payment subscription
                $subscription = Subscription::where('payment_reference', $orderId)
                    ->where('employer_id', $employer->id)
                    ->where('payment_method', 'paypal')
                    ->where('payment_type', SubscriptionPlan::PAYMENT_TYPE_ONE_TIME)
                    ->first();

                if (!$subscription) {
                    return response()->json([
                        'success' => false,
                        'message' => 'One-time subscription not found'
                    ], 404);
                }

                // Get order details from PayPal
                $response = Http::withToken($service->getAccessToken())
                    ->get("{$service->baseUrl}/v2/checkout/orders/{$orderId}");

                if (!$response->successful()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Failed to get order details from PayPal'
                    ], 500);
                }

                $orderDetails = $response->json();

                // Log the details for debugging
                Log::info('PayPal order details', [
                    'order_id' => $orderId,
                    'details' => $orderDetails
                ]);

                // If order is completed but subscription is not active
                if (isset($orderDetails['status']) && $orderDetails['status'] === 'COMPLETED' && !$subscription->is_active) {
                    $subscription->is_active = true;
                    $subscription->external_status = 'COMPLETED';
                    $subscription->save();

                    // Send notification
                    try {
                        $employer = $subscription->employer;
                        if ($employer && $employer->user) {
                            $employer->user->notify(new SubscriptionActivatedNotification($subscription));
                        }
                    } catch (\Exception $e) {
                        Log::error('Failed to send one-time subscription activation notification during verification', [
                            'subscription_id' => $subscription->id,
                            'error' => $e->getMessage()
                        ]);
                    }

                    Log::info('One-time subscription activated via manual verification', [
                        'subscription_id' => $subscription->id,
                        'order_id' => $orderId
                    ]);

                    return response()->success([
                        'subscription' => $subscription->fresh(),
                        'plan' => $subscription->plan
                    ],'One-time subscription activated successfully');
                }

                return response()->success([
                    'subscription' => $subscription,
                    'plan' => $subscription->plan,
                    'paypal_status' => $orderDetails['status'] ?? 'UNKNOWN'
                ],'One-time subscription status checked');
            }
        } catch (Exception $e) {
            Log::error('Error verifying PayPal subscription', [
                'subscription_id' => $subscriptionId ?? null,
                'order_id' => $orderId ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->serverError('Error verifying subscription: ' . $e->getMessage());
        }
        return response()->badRequest('Unable to verify subscription');
    }
}
