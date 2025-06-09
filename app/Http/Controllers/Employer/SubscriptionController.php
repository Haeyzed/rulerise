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
     * Get all available subscription plans with trial eligibility
     *
     * @return JsonResponse
     */
    public function getPlans(): JsonResponse
    {
        $employer = Auth::user()->employer;
        $plans = SubscriptionPlan::where('is_active', true)->get();

        // Add trial eligibility information to each plan
        $plansWithTrialInfo = $plans->map(function ($plan) use ($employer) {
            $planArray = $plan->toArray();
            $planArray['trial_eligible'] = $plan->hasTrial() && $employer->isEligibleForTrial();
            $planArray['trial_already_used'] = $employer->has_used_trial;
            return $planArray;
        });

        return response()->json([
            'success' => true,
            'data' => $plansWithTrialInfo
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
        $useTrial = $request->input('use_trial', false);

        // Validate trial usage
        if ($useTrial && (!$plan->hasTrial() || !$employer->isEligibleForTrial())) {
            return response()->badRequest('Trial is not available for this plan or employer');
        }

        try {
            $service = SubscriptionServiceFactory::create($provider);

            // For one-time plans with trial, create trial subscription directly
            if ($plan->isOneTime() && $useTrial && $employer->isEligibleForTrial()) {
                $subscription = $service->createTrialSubscription($employer, $plan);

                return response()->success([
                    'subscription_id' => $subscription->id,
                    'trial_subscription' => true,
                    'trial_end_date' => $subscription->end_date->toDateTimeString(),
                    'message' => 'Trial subscription activated successfully'
                ], 'Trial subscription created successfully');
            }

            $result = $service->createSubscription($employer, $plan);

            return response()->success($result, 'Subscription created successfully');
        } catch (Exception $e) {
            return response()->serverError($e->getMessage());
        }
    }

    /**
     * Create a trial subscription for one-time plans
     *
     * @param Request $request
     * @param SubscriptionPlan $plan
     * @return JsonResponse
     */
    public function createTrial(Request $request, SubscriptionPlan $plan): JsonResponse
    {
        $employer = Auth::user()->employer;
        $provider = $request->input('payment_provider', 'paypal');

        // Validate trial eligibility
        if (!$plan->hasTrial()) {
            return response()->badRequest('This plan does not offer a trial period');
        }

        if (!$employer->isEligibleForTrial()) {
            return response()->badRequest('You have already used your trial period');
        }

        if (!$plan->isOneTime()) {
            return response()->badRequest('Trial subscriptions are only available for one-time plans');
        }

        try {
            $service = SubscriptionServiceFactory::create($provider);
            $subscription = $service->createTrialSubscription($employer, $plan);

            return response()->success([
                'subscription' => $subscription,
                'plan' => $subscription->plan,
                'trial_end_date' => $subscription->end_date->toDateTimeString(),
                'message' => 'Trial subscription activated successfully'
            ], 'Trial subscription created successfully');
        } catch (Exception $e) {
            return response()->serverError($e->getMessage());
        }
    }

    /**
     * Get trial eligibility status
     *
     * @return JsonResponse
     */
    public function getTrialEligibility(): JsonResponse
    {
        $employer = Auth::user()->employer;

        return response()->success([
            'is_eligible' => $employer->isEligibleForTrial(),
            'has_used_trial' => $employer->has_used_trial,
            'trial_used_at' => $employer->trial_used_at?->toDateTimeString(),
        ], 'Trial eligibility retrieved successfully');
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
            Log::error('Error verifying PayP

al subscription', [
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
        $subscriptionId = $request->input('subscription_id');
        $sessionId = $request->input('session_id');

        if (!$subscriptionId && !$sessionId) {
            return response()->badRequest('Subscription ID or Session ID is required');
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
                return response()->notFound('Subscription not found');
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

                return response()->success([
                    'subscription' => $subscription->fresh(),
                    'plan' => $subscription->plan
                ],'Subscription activated successfully');
            }

            return response()->success([
                'subscription' => $subscription,
                'plan' => $subscription->plan,
                'stripe_status' => $stripeStatus,
                'is_one_time' => $isOneTimePayment
            ],'Subscription status checked');
        } catch (Exception $e) {
            Log::error('Error verifying Stripe subscription', [
                'subscription_id' => $subscriptionId ?? $sessionId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->serverError('Error verifying subscription: ' . $e->getMessage());
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
}
