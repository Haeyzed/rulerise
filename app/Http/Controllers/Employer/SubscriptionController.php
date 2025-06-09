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
use Illuminate\Support\Facades\Log;

class SubscriptionController extends Controller
{
    /**
     * Get all available subscription plans
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
     */
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
     * Subscribe to a plan with enhanced business logic
     */
    public function subscribe(Request $request, SubscriptionPlan $plan): JsonResponse
    {
        $employer = Auth::user()->employer;
        $provider = $request->input('payment_provider', 'paypal');

        try {
            $service = SubscriptionServiceFactory::create($provider);

            // Validate business rules before creating subscription
            if ($plan->isOneTime()) {
                if (!$service->canUseOneTimePayment($employer, $plan)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'You must complete a trial period before purchasing one-time plans.',
                        'requires_trial' => true,
                        'trial_available' => $employer->isEligibleForTrial()
                    ], 422);
                }
            }

            // Check if employer needs trial
            $needsTrial = $service->shouldUseTrial($employer, $plan);

            Log::info('Creating subscription', [
                'employer_id' => $employer->id,
                'plan_id' => $plan->id,
                'provider' => $provider,
                'is_one_time' => $plan->isOneTime(),
                'needs_trial' => $needsTrial,
                'has_used_trial' => $employer->has_used_trial
            ]);

            $result = $service->createSubscription($employer, $plan);

            return response()->json([
                'success' => true,
                'data' => array_merge($result, [
                    'needs_trial' => $needsTrial,
                    'plan_type' => $plan->payment_type
                ]),
                'message' => 'Subscription created successfully'
            ]);

        } catch (Exception $e) {
            Log::error('Subscription creation failed', [
                'employer_id' => $employer->id,
                'plan_id' => $plan->id,
                'provider' => $provider,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Activate free trial for eligible employers
     */
    public function activateFreeTrial(Request $request): JsonResponse
    {
        $employer = Auth::user()->employer;

        if (!$employer->isEligibleForTrial()) {
            return response()->json([
                'success' => false,
                'message' => 'Trial period has already been used'
            ], 422);
        }

        try {
            // Find a trial-enabled plan or create a trial subscription
            $trialPlan = SubscriptionPlan::where('has_trial', true)
                ->where('is_active', true)
                ->first();

            if (!$trialPlan) {
                return response()->json([
                    'success' => false,
                    'message' => 'No trial plans available'
                ], 404);
            }

            // Create a trial subscription record
            $subscription = Subscription::create([
                'employer_id' => $employer->id,
                'subscription_plan_id' => $trialPlan->id,
                'start_date' => now(),
                'end_date' => now()->addDays($trialPlan->getTrialPeriodDays()),
                'amount_paid' => 0,
                'currency' => $trialPlan->currency,
                'payment_method' => 'trial',
                'job_posts_left' => $trialPlan->job_posts_limit,
                'featured_jobs_left' => $trialPlan->featured_jobs_limit,
                'cv_downloads_left' => $trialPlan->resume_views_limit,
                'payment_type' => 'trial',
                'is_active' => true,
                'used_trial' => true,
            ]);

            // Mark trial as used
            $employer->markTrialAsUsed();

            return response()->json([
                'success' => true,
                'data' => [
                    'subscription' => $subscription,
                    'plan' => $trialPlan
                ],
                'message' => 'Trial activated successfully'
            ]);

        } catch (Exception $e) {
            Log::error('Trial activation failed', [
                'employer_id' => $employer->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to activate trial'
            ], 500);
        }
    }

    /**
     * Check subscription eligibility
     */
    public function checkEligibility(Request $request, SubscriptionPlan $plan): JsonResponse
    {
        $employer = Auth::user()->employer;
        $provider = $request->input('payment_provider', 'paypal');

        try {
            $service = SubscriptionServiceFactory::create($provider);

            $eligibility = [
                'can_subscribe' => true,
                'can_use_one_time' => $service->canUseOneTimePayment($employer, $plan),
                'should_use_trial' => $service->shouldUseTrial($employer, $plan),
                'is_eligible_for_trial' => $employer->isEligibleForTrial(),
                'has_used_trial' => $employer->has_used_trial,
                'plan_type' => $plan->payment_type,
                'requires_trial_first' => false
            ];

            // Check if one-time plan requires trial first
            if ($plan->isOneTime() && !$eligibility['can_use_one_time']) {
                $eligibility['can_subscribe'] = false;
                $eligibility['requires_trial_first'] = true;
            }

            return response()->json([
                'success' => true,
                'data' => $eligibility
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cancel subscription
     */
    public function cancel(Request $request): JsonResponse
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
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * List all plans from a payment provider
     */
    public function listProviderPlans(Request $request, string $provider): JsonResponse
    {
        try {
            $filters = $request->all();
            $service = SubscriptionServiceFactory::create($provider);
            $plans = $service->listPlans($filters);

            return response()->json([
                'success' => true,
                'data' => $plans,
                'message' => 'Plans retrieved successfully'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get details of a specific plan
     */
    public function getPlanDetails(string $provider, string $externalPlanId): JsonResponse
    {
        try {
            $service = SubscriptionServiceFactory::create($provider);
            $planDetails = $service->getPlanDetails($externalPlanId);

            return response()->json([
                'success' => true,
                'data' => $planDetails,
                'message' => 'Plan details retrieved successfully'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get details of a specific subscription
     */
    public function getSubscriptionDetails(string $provider, string $subscriptionId): JsonResponse
    {
        try {
            $service = SubscriptionServiceFactory::create($provider);
            $subscriptionDetails = $service->getSubscriptionDetails($subscriptionId);

            return response()->json([
                'success' => true,
                'data' => $subscriptionDetails,
                'message' => 'Subscription details retrieved successfully'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * List all subscriptions for the authenticated employer
     */
    public function listEmployerSubscriptions(Request $request, string $provider): JsonResponse
    {
        $employer = Auth::user()->employer;

        try {
            $service = SubscriptionServiceFactory::create($provider);
            $subscriptions = $service->listSubscriptions($employer);

            return response()->json([
                'success' => true,
                'data' => $subscriptions,
                'message' => 'Employer subscriptions retrieved successfully'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Suspend a subscription
     */
    public function suspendSubscription(Request $request, Subscription $subscription): JsonResponse
    {
        $employer = Auth::user()->employer;

        // Check if the subscription belongs to the employer
        if ($subscription->employer_id !== $employer->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        // One-time subscriptions can't be suspended
        if ($subscription->isOneTime()) {
            return response()->json([
                'success' => false,
                'message' => 'One-time subscriptions cannot be suspended'
            ], 400);
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
                    'success' => true,
                    'message' => 'Subscription already suspended'
                ]);
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
     */
    public function reactivateSubscription(Request $request, Subscription $subscription): JsonResponse
    {
        $employer = Auth::user()->employer;

        // Check if the subscription belongs to the employer
        if ($subscription->employer_id !== $employer->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        // One-time subscriptions can't be reactivated
        if ($subscription->isOneTime()) {
            return response()->json([
                'success' => false,
                'message' => 'One-time subscriptions cannot be reactivated'
            ], 400);
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
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get subscription transactions
     */
    public function getSubscriptionTransactions(Request $request, string $provider, string $subscriptionId): JsonResponse
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
                'data' => $transactions,
                'message' => 'Transactions retrieved successfully'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update subscription plan
     */
    public function updateSubscriptionPlan(Request $request, Subscription $subscription): JsonResponse
    {
        $employer = Auth::user()->employer;

        // Check if the subscription belongs to the employer
        if ($subscription->employer_id !== $employer->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        // One-time subscriptions can't be updated
        if ($subscription->isOneTime()) {
            return response()->json([
                'success' => false,
                'message' => 'One-time subscriptions cannot be updated to a different plan'
            ], 400);
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
                    'data' => [
                        'subscription' => $subscription->fresh(),
                        'plan' => $newPlan
                    ],
                    'message' => 'Subscription plan updated successfully'
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to update subscription plan'
                ], 500);
            }
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Handle PayPal success callback
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
                return response()->json([
                    'success' => true,
                    'data' => [
                        'subscription_id' => $subscription->id,
                        'is_active' => $subscription->is_active
                    ],
                    'message' => 'Subscription process completed'
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
     */
    public function stripeSuccess(Request $request): JsonResponse
    {
        // Log the request parameters
        Log::info('Stripe success callback received', [
            'params' => $request->all()
        ]);

        // Get the session ID from the request
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
                    'is_active' => $subscription->is_active
                ]);

                // We don't update the status here as it should be done by the webhook
                // But we can return the current status
                return response()->json([
                    'success' => true,
                    'data' => [
                        'subscription_id' => $subscription->id,
                        'is_active' => $subscription->is_active
                    ],
                    'message' => 'Subscription process completed'
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
     * Handle Stripe cancel callback
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
     */
    public function verifyPayPalSubscription(Request $request): JsonResponse
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

                return response()->json([
                    'success' => true,
                    'data' => [
                        'subscription' => $subscription->fresh(),
                        'plan' => $subscription->plan
                    ],
                    'message' => 'Subscription activated successfully'
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'subscription' => $subscription,
                    'plan' => $subscription->plan,
                    'paypal_status' => $details['status'] ?? 'UNKNOWN'
                ],
                'message' => 'Subscription status checked'
            ]);
        } catch (Exception $e) {
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

    /**
     * Manually verify a Stripe subscription
     */
    public function verifyStripeSubscription(Request $request): JsonResponse
    {
        $subscriptionId = $request->input('subscription_id');
        $sessionId = $request->input('session_id');

        if (!$subscriptionId && !$sessionId) {
            return response()->json([
                'success' => false,
                'message' => 'Subscription ID or Session ID is required'
            ], 400);
        }

        try {
            $employer = Auth::user()->employer;
            $service = SubscriptionServiceFactory::create('stripe');

            // Find the subscription
            $subscription = null;

            if ($subscriptionId) {
                $subscription = Subscription::where('id', $subscriptionId)
                    ->where('employer_id', $employer->id)
                    ->where('payment_method', 'stripe')
                    ->first();
            } elseif ($sessionId) {
                $subscription = Subscription::where('payment_reference', $sessionId)
                    ->where('employer_id', $employer->id)
                    ->where('payment_method', 'stripe')
                    ->first();
            }

            if (!$subscription) {
                return response()->json([
                    'success' => false,
                    'message' => 'Subscription not found'
                ], 404);
            }

            // Check if this is a one-time payment or recurring subscription
            $isOneTimePayment = $subscription->isOneTime();

            // Get details from Stripe
            $details = [];
            $shouldBeActive = false;
            $stripeStatus = 'unknown';

            if ($isOneTimePayment) {
                // For one-time payments, check the checkout session
                if ($subscription->payment_reference && method_exists($service, 'getCheckoutSessionDetails')) {
                    $sessionDetails = $service->getCheckoutSessionDetails($subscription->payment_reference);
                    $details = $sessionDetails;

                    // For one-time payments, check session status and payment status
                    $sessionStatus = $sessionDetails['status'] ?? '';
                    $paymentStatus = $sessionDetails['payment_status'] ?? '';

                    Log::info('Stripe one-time payment session details', [
                        'session_id' => $subscription->payment_reference,
                        'session_status' => $sessionStatus,
                        'payment_status' => $paymentStatus,
                        'details' => $sessionDetails
                    ]);

                    // One-time payment is successful if session is complete and payment is paid
                    if ($sessionStatus === 'complete' && $paymentStatus === 'paid') {
                        $shouldBeActive = true;
                        $stripeStatus = 'paid';
                    }

                    // Update transaction ID if available
                    if (isset($sessionDetails['payment_intent'])) {
                        // Extract just the ID if it's an object, otherwise use as-is
                        $paymentIntent = $sessionDetails['payment_intent'];
                        $subscription->transaction_id = is_array($paymentIntent) ? ($paymentIntent['id'] ?? null) : $paymentIntent;
                    }
                }
            } else {
                // For recurring subscriptions, check subscription details
                if ($subscription->subscription_id) {
                    $details = $service->getSubscriptionDetails($subscription->subscription_id);
                } elseif ($sessionId && method_exists($service, 'getCheckoutSessionDetails')) {
                    $sessionDetails = $service->getCheckoutSessionDetails($sessionId);

                    // If session has subscription, get subscription details
                    if (isset($sessionDetails['subscription']['id'])) {
                        $subscription->subscription_id = $sessionDetails['subscription']['id'];
                        $subscription->save();
                        $details = $service->getSubscriptionDetails($sessionDetails['subscription']['id']);
                    } else {
                        $details = $sessionDetails;
                    }
                }

                // For recurring subscriptions, check subscription status
                $stripeStatus = $details['status'] ?? 'unknown';
                $activeStatuses = ['active', 'trialing'];

                if (in_array($stripeStatus, $activeStatuses)) {
                    $shouldBeActive = true;
                }
            }

            // Log the details for debugging
            Log::info('Stripe subscription verification details', [
                'subscription_id' => $subscription->id,
                'is_one_time' => $isOneTimePayment,
                'stripe_status' => $stripeStatus,
                'should_be_active' => $shouldBeActive,
                'current_is_active' => $subscription->is_active
            ]);

            // Update subscription with Stripe details if we have them
            if (!empty($details) && isset($details['status']) && method_exists($service, 'updateSubscriptionWithStripeDetails')) {
                $service->updateSubscriptionWithStripeDetails($subscription, $details);
            }

            // If subscription should be active but isn't in our database
            if ($shouldBeActive && !$subscription->is_active) {
                $subscription->is_active = true;
                $subscription->external_status = $stripeStatus;
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
                    'stripe_id' => $subscription->subscription_id ?? $subscription->payment_reference,
                    'stripe_status' => $stripeStatus,
                    'is_one_time' => $isOneTimePayment
                ]);

                return response()->json([
                    'success' => true,
                    'data' => [
                        'subscription' => $subscription->fresh(),
                        'plan' => $subscription->plan
                    ],
                    'message' => 'Subscription activated successfully'
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'subscription' => $subscription,
                    'plan' => $subscription->plan,
                    'stripe_status' => $stripeStatus,
                    'is_one_time' => $isOneTimePayment
                ],
                'message' => 'Subscription status checked'
            ]);
        } catch (Exception $e) {
            Log::error('Error verifying Stripe subscription', [
                'subscription_id' => $subscriptionId ?? $sessionId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error verifying subscription: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all subscriptions for the authenticated employer
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

        return response()->json([
            'success' => true,
            'data' => [
                'subscriptions' => $subscriptions
            ],
            'message' => 'Subscriptions retrieved successfully'
        ]);
    }
}
