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
        // Log the request parameters
        Log::info('Stripe success callback received', [
            'params' => $request->all()
        ]);

        $sessionId = $request->get('session_id');

        if ($sessionId) {
            // Find the subscription in our database
            $subscription = Subscription::where('payment_reference', $sessionId)
                ->where('payment_method', 'stripe')
                ->first();

            if ($subscription) {
                // Log that we found the subscription
                Log::info('Found subscription for Stripe callback', [
                    'subscription_id' => $subscription->id,
                    'is_active' => $subscription->is_active,
                    'session_id' => $sessionId
                ]);

                // For one-time payments that might not have been activated yet
                if (!$subscription->is_active && $subscription->isOneTime()) {
                    try {
                        // Try to get session details to verify payment
                        $service = SubscriptionServiceFactory::create('stripe');
                        $session = $service->stripe->checkout->sessions->retrieve($sessionId);

                        if ($session->payment_status === 'paid') {
                            $subscription->is_active = true;
                            $subscription->transaction_id = $session->payment_intent;
                            $subscription->save();

                            // Send notification
                            try {
                                if ($subscription->employer && $subscription->employer->user) {
                                    $subscription->employer->user->notify(new SubscriptionActivatedNotification($subscription));
                                }
                            } catch (\Exception $e) {
                                Log::error('Failed to send subscription activation notification during Stripe success', [
                                    'subscription_id' => $subscription->id,
                                    'error' => $e->getMessage()
                                ]);
                            }
                        }
                    } catch (\Exception $e) {
                        Log::error('Error checking Stripe session during success callback', [
                            'session_id' => $sessionId,
                            'error' => $e->getMessage()
                        ]);
                    }
                }

                return response()->success([
                    'subscription_id' => $subscription->id,
                    'is_active' => $subscription->is_active
                ], 'Subscription process completed');
            }
        }

        // If we can't find the subscription, just return a generic success
        return response()->success(null,'Subscription process completed. It may take a few moments to activate.');
    }

    /**
     * Handle Stripe cancel callback
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function stripeCancel(Request $request): JsonResponse
    {
        Log::info('Stripe cancel callback received', [
            'params' => $request->all()
        ]);

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

        if (!$subscriptionId) {
            return response()->badRequest('Subscription ID is required');
        }

        try {
            $employer = Auth::user()->employer;
            $service = SubscriptionServiceFactory::create('paypal');

            // Find the subscription
            $subscription = Subscription::query()->where('subscription_id', $subscriptionId)
                ->where('employer_id', $employer->id)
                ->where('payment_method', 'paypal')
                ->first();

            if (!$subscription) {
                // Try to find by subscription_id only, in case it was created for this employer
                $subscription = Subscription::where('subscription_id', $subscriptionId)
                    ->where('payment_method', 'paypal')
                    ->first();

                if (!$subscription || ($subscription->employer_id !== $employer->id)) {
                    return response()->notFound('Subscription not found');
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
        } catch (Exception $e) {
            Log::error('Error verifying PayPal subscription', [
                'subscription_id' => $subscriptionId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->serverError('Error verifying subscription: ' . $e->getMessage());
        }
    }

    /**
     * Manually verify a Stripe subscription
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function verifyStripeSubscription(Request $request): JsonResponse
    {
        $sessionId = $request->input('session_id');
        $subscriptionId = $request->input('subscription_id');

        if (!$sessionId && !$subscriptionId) {
            return response()->badRequest('Session ID or Subscription ID is required');
        }

        try {
            $employer = Auth::user()->employer;
            $service = SubscriptionServiceFactory::create('stripe');

            // Find the subscription by session ID or subscription ID
            $subscription = null;

            if ($sessionId) {
                $subscription = Subscription::where('payment_reference', $sessionId)
                    ->where('payment_method', 'stripe')
                    ->first();
            } elseif ($subscriptionId) {
                $subscription = Subscription::where('subscription_id', $subscriptionId)
                    ->where('payment_method', 'stripe')
                    ->first();
            }

            if (!$subscription) {
                // Try to find by employer and latest pending subscription
                $subscription = Subscription::where('employer_id', $employer->id)
                    ->where('payment_method', 'stripe')
                    ->where('is_active', false)
                    ->latest()
                    ->first();

                if (!$subscription) {
                    return response()->notFound('Subscription not found');
                }
            }

            // Verify that the subscription belongs to the current employer
            if ($subscription->employer_id !== $employer->id) {
                return response()->forbidden('Unauthorized access to this subscription');
            }

            // Get subscription details from Stripe
            $details = null;
            $stripeStatus = 'UNKNOWN';

            // Check if we have a Stripe subscription ID
            if ($subscription->subscription_id && $subscription->subscription_id !== $sessionId) {
                try {
                    $details = $service->getSubscriptionDetails($subscription->subscription_id);
                    $stripeStatus = $details['status'] ?? 'UNKNOWN';

                    // Log the details for debugging
                    Log::info('Stripe subscription details', [
                        'subscription_id' => $subscription->subscription_id,
                        'details' => $details
                    ]);
                } catch (\Exception $e) {
                    Log::error('Error retrieving Stripe subscription details', [
                        'subscription_id' => $subscription->subscription_id,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            // If we have a session ID, check the session
            if ($sessionId) {
                try {
                    $session = $service->stripe->checkout->sessions->retrieve($sessionId, [
                        'expand' => ['payment_intent', 'subscription']
                    ]);

                    Log::info('Stripe session details', [
                        'session_id' => $sessionId,
                        'session' => $session->toArray()
                    ]);

                    // Update subscription with session information
                    if ($session->mode === 'subscription' && isset($session->subscription) && !$subscription->subscription_id) {
                        $subscription->subscription_id = is_string($session->subscription)
                            ? $session->subscription
                            : $session->subscription->id;
                        $subscription->save();

                        // Now get the subscription details
                        $details = $service->getSubscriptionDetails($subscription->subscription_id);
                        $stripeStatus = $details['status'] ?? $session->status;
                    } elseif ($session->mode === 'payment') {
                        // For one-time payments
                        $stripeStatus = $session->payment_status;
                        if ($session->payment_intent) {
                            $subscription->transaction_id = is_string($session->payment_intent)
                                ? $session->payment_intent
                                : $session->payment_intent->id;
                        }
                    }

                    // Update billing info with session data
                    $billingInfo = $subscription->billing_info ?? [];
                    $billingInfo['session_status'] = $session->status;
                    $billingInfo['payment_status'] = $session->payment_status ?? null;
                    $billingInfo['session_mode'] = $session->mode;
                    $subscription->billing_info = $billingInfo;
                    $subscription->save();

                } catch (\Exception $e) {
                    Log::error('Error retrieving Stripe session', [
                        'session_id' => $sessionId,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            // Update subscription with Stripe details if available
            if ($details) {
                $service->updateSubscriptionWithStripeDetails($subscription, $details);
            }

            // Determine if subscription should be active
            $isActiveInStripe = false;

            if ($subscription->isOneTime()) {
                // For one-time payments, check session payment status
                $isActiveInStripe = in_array($stripeStatus, ['paid', 'complete', 'succeeded']);
            } else {
                // For recurring subscriptions, check subscription status
                $isActiveInStripe = in_array($stripeStatus, ['active', 'trialing']);
            }

            // If subscription is active in Stripe but not in our database
            if ($isActiveInStripe && !$subscription->is_active) {
                $subscription->is_active = true;
                $subscription->save();

                // Send notification
                try {
                    if ($employer->user) {
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
                    'stripe_status' => $stripeStatus,
                    'session_id' => $sessionId,
                    'stripe_subscription_id' => $subscription->subscription_id
                ]);

                return response()->success([
                    'subscription' => $subscription->fresh(),
                    'plan' => $subscription->plan
                ],'Subscription activated successfully');
            }

            return response()->success([
                'subscription' => $subscription,
                'plan' => $subscription->plan,
                'stripe_status' => $stripeStatus,
                'session_id' => $sessionId,
                'stripe_subscription_id' => $subscription->subscription_id
            ],'Subscription status checked');

        } catch (Exception $e) {
            Log::error('Error verifying Stripe subscription', [
                'session_id' => $sessionId ?? null,
                'subscription_id' => $subscriptionId ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->serverError('Error verifying subscription: ' . $e->getMessage());
        }
    }

    /**
     * Get subscription status for debugging
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getSubscriptionStatus(Request $request): JsonResponse
    {
        $employer = Auth::user()->employer;
        $provider = $request->input('provider', 'stripe');
        $subscriptionId = $request->input('subscription_id');
        $sessionId = $request->input('session_id');

        if (!$subscriptionId && !$sessionId) {
            return response()->badRequest('Subscription ID or Session ID is required');
        }

        try {
            $service = SubscriptionServiceFactory::create($provider);

            // Find subscription in our database
            $subscription = null;
            if ($subscriptionId) {
                $subscription = Subscription::where('subscription_id', $subscriptionId)
                    ->where('employer_id', $employer->id)
                    ->where('payment_method', $provider)
                    ->first();
            } elseif ($sessionId) {
                $subscription = Subscription::where('payment_reference', $sessionId)
                    ->where('employer_id', $employer->id)
                    ->where('payment_method', $provider)
                    ->first();
            }

            $response = [
                'local_subscription' => $subscription,
                'external_details' => null
            ];

            // Get external details
            if ($provider === 'stripe') {
                if ($subscriptionId) {
                    $response['external_details'] = $service->getSubscriptionDetails($subscriptionId);
                } elseif ($sessionId) {
                    try {
                        $session = $service->stripe->checkout->sessions->retrieve($sessionId);
                        $response['external_details'] = $session->toArray();
                    } catch (\Exception $e) {
                        $response['external_error'] = $e->getMessage();
                    }
                }
            } elseif ($provider === 'paypal') {
                if ($subscriptionId) {
                    $response['external_details'] = $service->getSubscriptionDetails($subscriptionId);
                }
            }

            return response()->success($response, 'Subscription status retrieved');

        } catch (Exception $e) {
            return response()->serverError('Error getting subscription status: ' . $e->getMessage());
        }
    }

    /**
     * Sync subscription with external provider
     *
     * @param Request $request
     * @param Subscription $subscription
     * @return JsonResponse
     */
    public function syncSubscription(Request $request, Subscription $subscription): JsonResponse
    {
        $employer = Auth::user()->employer;

        // Check if the subscription belongs to the employer
        if ($subscription->employer_id !== $employer->id) {
            return response()->forbidden('Unauthorized');
        }

        try {
            $service = SubscriptionServiceFactory::create($subscription->payment_method);

            if (!$subscription->subscription_id) {
                return response()->badRequest('Subscription has no external ID to sync with');
            }

            // Get details from external provider
            $details = $service->getSubscriptionDetails($subscription->subscription_id);

            if (empty($details)) {
                return response()->notFound('Subscription not found in external provider');
            }

            // Update subscription with external details
            if ($subscription->payment_method === 'stripe') {
                $service->updateSubscriptionWithStripeDetails($subscription, $details);
            } elseif ($subscription->payment_method === 'paypal') {
                $service->updateSubscriptionWithPayPalDetails($subscription, $details);
            }

            // Update status based on external status
            $externalStatus = $details['status'] ?? 'unknown';
            $wasActive = $subscription->is_active;

            if ($subscription->payment_method === 'stripe') {
                $subscription->is_active = in_array($externalStatus, ['active', 'trialing']);
                $subscription->is_suspended = $externalStatus === 'paused';
            } elseif ($subscription->payment_method === 'paypal') {
                $subscription->is_active = in_array($externalStatus, ['ACTIVE', 'APPROVED']);
                $subscription->is_suspended = $externalStatus === 'SUSPENDED';
            }

            $subscription->external_status = $externalStatus;
            $subscription->save();

            // Send activation notification if subscription became active
            if (!$wasActive && $subscription->is_active) {
                try {
                    if ($employer->user) {
                        $employer->user->notify(new SubscriptionActivatedNotification($subscription));
                    }
                } catch (\Exception $e) {
                    Log::error('Failed to send subscription activation notification during sync', [
                        'subscription_id' => $subscription->id,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            return response()->success([
                'subscription' => $subscription->fresh(),
                'plan' => $subscription->plan,
                'external_status' => $externalStatus
            ], 'Subscription synced successfully');

        } catch (Exception $e) {
            Log::error('Error syncing subscription', [
                'subscription_id' => $subscription->id,
                'error' => $e->getMessage()
            ]);

            return response()->serverError('Error syncing subscription: ' . $e->getMessage());
        }
    }

    /**
     * Get all subscriptions for the authenticated employer
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getAllSubscriptions(Request $request): JsonResponse
    {
        $employer = Auth::user()->employer;
        $provider = $request->input('provider');

        $query = Subscription::where('employer_id', $employer->id)
            ->with('plan');

        if ($provider) {
            $query->where('payment_method', $provider);
        }

        $subscriptions = $query->orderBy('created_at', 'desc')->get();

        return response()->success([
            'subscriptions' => $subscriptions
        ], 'Subscriptions retrieved successfully');
    }

    /**
     * Retry failed subscription
     *
     * @param Request $request
     * @param Subscription $subscription
     * @return JsonResponse
     */
    public function retrySubscription(Request $request, Subscription $subscription): JsonResponse
    {
        $employer = Auth::user()->employer;

        // Check if the subscription belongs to the employer
        if ($subscription->employer_id !== $employer->id) {
            return response()->forbidden('Unauthorized');
        }

        // Only retry failed subscriptions
        if ($subscription->is_active) {
            return response()->badRequest('Subscription is already active');
        }

        try {
            $service = SubscriptionServiceFactory::create($subscription->payment_method);
            $plan = $subscription->plan;

            // Create a new subscription attempt
            $result = $service->createSubscription($employer, $plan);

            return response()->success($result, 'Subscription retry initiated successfully');

        } catch (Exception $e) {
            Log::error('Error retrying subscription', [
                'subscription_id' => $subscription->id,
                'error' => $e->getMessage()
            ]);

            return response()->serverError('Error retrying subscription: ' . $e->getMessage());
        }
    }
}
