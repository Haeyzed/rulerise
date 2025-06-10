<?php

namespace App\Http\Controllers\Employer;

use App\Http\Controllers\Controller;
use App\Models\Employer;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Services\Subscription\SubscriptionServiceFactory;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class SubscriptionController extends Controller
{
    public function getPlans(): JsonResponse
    {
        $plans = SubscriptionPlan::where('is_active', true)->get();

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

    public function subscribe(Request $request, SubscriptionPlan $plan): JsonResponse
    {
        $employer = Auth::user()->employer;
        $provider = $request->input('payment_provider', 'paypal');

        try {
            $service = SubscriptionServiceFactory::create($provider);

            // Validate business rules
            if ($plan->isOneTime() && !$service->canUseOneTimePayment($employer, $plan)) {
                if ($plan->hasTrial() && !$employer->has_used_trial) {
                    return $this->createTrialSubscription($employer, $plan);
                }

                return response()->json([
                    'success' => false,
                    'message' => 'One-time payment not available for this plan configuration.',
                    'requires_trial' => false,
                    'trial_available' => false,
                    'plan_has_trial' => $plan->hasTrial()
                ], 422);
            }

            Log::info('Creating subscription', [
                'employer_id' => $employer->id,
                'plan_id' => $plan->id,
                'provider' => $provider,
                'is_one_time' => $plan->isOneTime(),
                'needs_trial' => $service->shouldUseTrial($employer, $plan),
            ]);

            $result = $service->createSubscription($employer, $plan);

            return response()->json([
                'success' => true,
                'data' => $result,
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

    private function createTrialSubscription(Employer $employer, SubscriptionPlan $plan): JsonResponse
    {
        try {
            $subscription = Subscription::create([
                'employer_id' => $employer->id,
                'subscription_plan_id' => $plan->id,
                'start_date' => now(),
                'end_date' => now()->addDays($plan->getTrialPeriodDays()),
                'amount_paid' => 0,
                'currency' => $plan->currency,
                'payment_method' => 'trial',
                'job_posts_left' => $plan->job_posts_limit,
                'featured_jobs_left' => $plan->featured_jobs_limit,
                'cv_downloads_left' => $plan->resume_views_limit,
                'payment_type' => 'trial',
                'is_active' => true,
                'used_trial' => true,
            ]);

            $employer->markTrialAsUsed();

            return response()->json([
                'success' => true,
                'data' => [
                    'requires_trial' => true,
                    'trial_available' => true,
                    'plan_has_trial' => $plan->hasTrial(),
                    'trial_activated' => true,
                    'trial_end_date' => $subscription->end_date
                ],
                'message' => 'Trial period activated. Please try again after the trial.'
            ]);
        } catch (Exception $e) {
            Log::error('Trial creation failed', [
                'employer_id' => $employer->id,
                'plan_id' => $plan->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to activate trial'
            ], 500);
        }
    }

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
                'plan_has_trial' => $plan->hasTrial(),
                'requires_trial_first' => false
            ];

            if ($plan->isOneTime() && !$eligibility['can_use_one_time']) {
                if ($plan->hasTrial() && !$employer->has_used_trial) {
                    $eligibility['can_subscribe'] = false;
                    $eligibility['requires_trial_first'] = true;
                }
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

            return response()->json([
                'success' => $success,
                'message' => $success ? 'Subscription cancelled successfully' : 'Failed to cancel subscription'
            ], $success ? 200 : 500);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

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

            $subscription = Subscription::where('subscription_id', $subscriptionId)
                ->where('employer_id', $employer->id)
                ->where('payment_method', 'paypal')
                ->first();

            if (!$subscription) {
                return response()->json([
                    'success' => false,
                    'message' => 'Subscription not found'
                ], 404);
            }

            $details = $service->getSubscriptionDetails($subscriptionId);

            Log::info('PayPal subscription verification', [
                'subscription_id' => $subscriptionId,
                'details' => $details
            ]);

            if (isset($details['status']) && in_array($details['status'], ['ACTIVE', 'APPROVED']) && !$subscription->is_active) {
                $subscription->update(['is_active' => true]);

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
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error verifying subscription: ' . $e->getMessage()
            ], 500);
        }
    }

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

            $isOneTimePayment = $subscription->isOneTime();
            $details = [];
            $shouldBeActive = false;
            $stripeStatus = 'unknown';

            if ($isOneTimePayment) {
                if ($subscription->payment_reference) {
                    $sessionDetails = $service->getCheckoutSessionDetails($subscription->payment_reference);
                    $details = $sessionDetails;

                    $sessionStatus = $sessionDetails['status'] ?? '';
                    $paymentStatus = $sessionDetails['payment_status'] ?? '';

                    if ($sessionStatus === 'complete' && $paymentStatus === 'paid') {
                        $shouldBeActive = true;
                        $stripeStatus = 'paid';
                    }

                    if (isset($sessionDetails['payment_intent'])) {
                        $paymentIntent = $sessionDetails['payment_intent'];
                        $subscription->transaction_id = is_array($paymentIntent) ? ($paymentIntent['id'] ?? null) : $paymentIntent;
                    }
                }
            } else {
                if ($subscription->subscription_id) {
                    $details = $service->getSubscriptionDetails($subscription->subscription_id);
                } elseif ($sessionId) {
                    $sessionDetails = $service->getCheckoutSessionDetails($sessionId);

                    if (isset($sessionDetails['subscription']['id'])) {
                        $subscription->subscription_id = $sessionDetails['subscription']['id'];
                        $subscription->save();
                        $details = $service->getSubscriptionDetails($sessionDetails['subscription']['id']);
                    } else {
                        $details = $sessionDetails;
                    }
                }

                $stripeStatus = $details['status'] ?? 'unknown';
                if (in_array($stripeStatus, ['active', 'trialing'])) {
                    $shouldBeActive = true;
                }
            }

            if ($shouldBeActive && !$subscription->is_active) {
                $subscription->update([
                    'is_active' => true,
                    'external_status' => $stripeStatus
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
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error verifying subscription: ' . $e->getMessage()
            ], 500);
        }
    }

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
            'data' => ['subscriptions' => $subscriptions],
            'message' => 'Subscriptions retrieved successfully'
        ]);
    }
}
