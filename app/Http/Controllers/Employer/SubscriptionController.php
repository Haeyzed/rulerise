<?php

namespace App\Http\Controllers\Employer;

use App\Http\Controllers\Controller;
use App\Models\Employer;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Services\PayPalSubscriptionService;
use App\Services\StripeSubscriptionService;
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
            // Validate business rules
            if ($plan->isOneTime() && $plan->hasTrial() && !$employer->has_used_trial) {
                return $this->createTrialSubscription($employer, $plan);
            }

            Log::info('Creating subscription', [
                'employer_id' => $employer->id,
                'plan_id' => $plan->id,
                'provider' => $provider,
                'is_one_time' => $plan->isOneTime(),
            ]);

            // Use the appropriate service based on provider
            if ($provider === 'stripe') {
                $service = app(StripeSubscriptionService::class);
            } else {
                $service = app(PayPalSubscriptionService::class);
            }

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
                    'trial_activated' => true,
                    'trial_end_date' => $subscription->end_date
                ],
                'message' => 'Trial period activated successfully.'
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
            $success = false;

            if ($subscription->payment_method === 'stripe') {
                $service = app(StripeSubscriptionService::class);
                $success = $service->cancelSubscription($subscription->subscription_id);
            } elseif ($subscription->payment_method === 'paypal') {
                $service = app(PayPalSubscriptionService::class);
                $success = $service->cancelSubscription($subscription->subscription_id);
            }

            if ($success) {
                $subscription->update(['is_active' => false]);
            }

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
            $service = app(PayPalSubscriptionService::class);

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

            // Update next billing date if available
            if (!empty($details) && isset($details['next_billing_date'])) {
                $subscription->next_billing_date = $details['next_billing_date'];
                $subscription->save();
            }

            if (isset($details['status']) && in_array($details['status'], ['ACTIVE', 'APPROVED']) && !$subscription->is_active) {
                $subscription->update([
                    'is_active' => true,
                    'external_status' => $details['status']
                ]);

                return response()->json([
                    'success' => true,
                    'data' => [
                        'subscription' => $subscription->fresh(),
                        'plan' => $subscription->plan,
                        'next_billing_date' => $subscription->next_billing_date
                    ],
                    'message' => 'Subscription activated successfully'
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'subscription' => $subscription,
                    'plan' => $subscription->plan,
                    'paypal_status' => $details['status'] ?? 'UNKNOWN',
                    'next_billing_date' => $subscription->next_billing_date
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
        $sessionId = $request->input('subscription_id');

        if (!$sessionId) {
            return response()->json([
                'success' => false,
                'message' => 'Session ID is required'
            ], 400);
        }

        try {
            $employer = Auth::user()->employer;
            $service = app(StripeSubscriptionService::class);

            $subscription = Subscription::where('payment_reference', $sessionId)
                ->where('employer_id', $employer->id)
                ->where('payment_method', 'stripe')
                ->first();

            if (!$subscription) {
                return response()->json([
                    'success' => false,
                    'message' => 'Subscription not found'
                ], 404);
            }

            // Check if subscription is already active
            if ($subscription->is_active) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'subscription' => $subscription,
                        'plan' => $subscription->plan,
                        'next_billing_date' => $subscription->next_billing_date
                    ],
                    'message' => 'Subscription is already active'
                ]);
            }

            // Get subscription details if we have a subscription ID
            if ($subscription->subscription_id) {
                $details = $service->getSubscriptionDetails($subscription->subscription_id);

                if (isset($details['status']) && in_array($details['status'], ['active', 'trialing'])) {
                    $subscription->update([
                        'is_active' => true,
                        'external_status' => $details['status'],
                        'next_billing_date' => isset($details['next_billing_date']) ? $details['next_billing_date'] : null
                    ]);

                    return response()->json([
                        'success' => true,
                        'data' => [
                            'subscription' => $subscription->fresh(),
                            'plan' => $subscription->plan,
                            'next_billing_date' => $subscription->next_billing_date
                        ],
                        'message' => 'Subscription activated successfully'
                    ]);
                }
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'subscription' => $subscription,
                    'plan' => $subscription->plan,
                    'stripe_status' => $subscription->external_status ?? 'pending'
                ],
                'message' => 'Subscription status checked'
            ]);
        } catch (Exception $e) {
            Log::error('Error verifying Stripe subscription', [
                'session_id' => $sessionId,
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
