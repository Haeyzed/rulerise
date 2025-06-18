<?php

namespace App\Http\Controllers\Employer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Employer\CreatePaymentRequest;
use App\Http\Requests\Employer\CreateSubscriptionRequest;
use App\Models\Plan;
use App\Models\Subscription;
use App\Notifications\SubscriptionCreated;
use App\Services\Payment\PayPalPaymentService;
use App\Services\Payment\Exceptions\PaymentException;
use App\Services\Payment\StripePaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Payment Controller
 *
 * Handles all payment-related operations for employers including
 * subscription management, upgrades, and payment processing.
 */
class PaymentController extends Controller
{
    public function __construct(
        private PayPalPaymentService $paypalService,
        private StripePaymentService $stripeService
    ) {}

    // ========================================
    // PLAN MANAGEMENT
    // ========================================

    /**
     * Get available plans with enhanced filtering
     */
    public function getPlans(Request $request): JsonResponse
    {
        try {
            $query = Plan::active()->with(['subscriptions' => function ($query) {
                $query->active()->limit(1);
            }]);

            // Apply filters
            if ($request->has('billing_cycle')) {
                $query->byBillingCycle($request->billing_cycle);
            }

            if ($request->boolean('with_trial')) {
                $query->withTrial();
            }

            $plans = $query->orderBy('price')->get();

            // Add computed fields
            $plans->each(function ($plan) {
                $plan->formatted_price = $plan->getFormattedPrice();
                $plan->billing_cycle_label = $plan->getBillingCycleLabel();
                $plan->features_list = $plan->getFeaturesList();
            });

            return response()->json([
                'success' => true,
                'data' => $plans,
                'message' => 'Plans retrieved successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve plans', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve plans'
            ], 500);
        }
    }

    /**
     * Get active subscription with detailed information
     */
    public function getActiveSubscription(): JsonResponse
    {
        try {
            $employer = Auth::user()->employer;
            $subscription = $employer->activeSubscription;

            if (!$subscription) {
                return response()->json([
                    'success' => true,
                    'data' => null,
                    'message' => 'No active subscription found'
                ]);
            }

            // Enrich subscription data
            $subscriptionData = [
                'subscription' => $subscription,
                'plan' => $subscription->plan,
                'remaining_trial_days' => $subscription->getRemainingTrialDays(),
                'days_until_next_billing' => $subscription->getDaysUntilNextBilling(),
                'formatted_amount' => $subscription->getFormattedAmount(),
                'is_trial_expiring_soon' => $subscription->getRemainingTrialDays() <= 3 && $subscription->isInTrial(),
                'usage_stats' => [
                    'remaining_job_posts' => $employer->getRemainingJobPosts(),
                    'remaining_resume_views' => $employer->getRemainingResumeViews(),
                ]
            ];

            return response()->json([
                'success' => true,
                'data' => $subscriptionData,
                'message' => 'Active subscription retrieved successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve active subscription', [
                'error' => $e->getMessage(),
                'employer_id' => Auth::user()->employer->id ?? null
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve subscription information'
            ], 500);
        }
    }

    // ========================================
    // PAYMENT CREATION
    // ========================================

    /**
     * Create one-time payment with comprehensive validation
     */
    public function createOneTimePayment(CreatePaymentRequest $request): JsonResponse
    {
        return DB::transaction(function () use ($request) {
            try {
                $employer = Auth::user()->employer;
                $plan = Plan::findOrFail($request->plan_id);

                // Validate plan type
                if (!$plan->isOneTime()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'This plan is not available for one-time payment'
                    ], 400);
                }

                // Handle upgrade validation
                if ($request->is_upgrade) {
                    $validationResult = $this->validateUpgrade($employer, $plan);
                    if (!$validationResult['valid']) {
                        return response()->json([
                            'success' => false,
                            'message' => $validationResult['message']
                        ], 400);
                    }
                }

                // Determine payment flow based on provider
                $result = match ($request->payment_provider) {
                    'stripe' => $this->stripeService->createOneTimePayment($employer, $plan),
                    'paypal' => $this->paypalService->createOneTimePayment($employer, $plan),
                    default => throw new PaymentException('Unsupported payment provider')
                };

                if (!$result['success']) {
                    return response()->json([
                        'success' => false,
                        'message' => $result['error']
                    ], 400);
                }

                // Handle upgrade if applicable
                if ($request->is_upgrade) {
                    $this->handleUpgrade($employer);
                }

                Log::info('One-time payment created successfully', [
                    'employer_id' => $employer->id,
                    'plan_id' => $plan->id,
                    'payment_provider' => $request->payment_provider,
                    'is_upgrade' => $request->is_upgrade
                ]);

                return response()->json([
                    'success' => true,
                    'data' => $result,
                    'message' => 'Payment created successfully'
                ]);

            } catch (PaymentException $e) {
                Log::error('Payment creation failed', [
                    'error' => $e->getMessage(),
                    'employer_id' => Auth::user()->employer->id ?? null
                ]);

                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage()
                ], 400);

            } catch (\Exception $e) {
                Log::error('Unexpected error during payment creation', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'An unexpected error occurred. Please try again.'
                ], 500);
            }
        });
    }

    /**
     * Create subscription with enhanced error handling
     */
    public function createSubscription(CreateSubscriptionRequest $request): JsonResponse
    {
        return DB::transaction(function () use ($request) {
            try {
                $employer = Auth::user()->employer;
                $plan = Plan::findOrFail($request->plan_id);

                // Validate plan type
                if (!$plan->isRecurring()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'This plan is not available for subscription'
                    ], 400);
                }

                // Handle upgrade validation
                if ($request->is_upgrade) {
                    $validationResult = $this->validateUpgrade($employer, $plan);
                    if (!$validationResult['valid']) {
                        return response()->json([
                            'success' => false,
                            'message' => $validationResult['message']
                        ], 400);
                    }
                } else {
                    // Check for existing active subscription
                    if ($employer->hasActiveSubscription()) {
                        return response()->json([
                            'success' => false,
                            'message' => 'You already have an active subscription. Use upgrade option to change plans.'
                        ], 400);
                    }
                }

                // Create subscription based on provider
                $result = match ($request->payment_provider) {
                    'stripe' => $this->stripeService->createSubscription($employer, $plan),
                    'paypal' => $this->paypalService->createSubscription($employer, $plan),
                    default => throw new PaymentException('Unsupported payment provider')
                };

                if (!$result['success']) {
                    return response()->json([
                        'success' => false,
                        'message' => $result['error']
                    ], 400);
                }

                // Handle upgrade if applicable
                if ($request->is_upgrade) {
                    $this->handleUpgrade($employer);
                }

                // Send notification
                if (isset($result['subscription']) && $result['subscription'] instanceof Subscription) {
                    $employer->notify(new SubscriptionCreated($result['subscription']));
                }

                // Format response
                $responseData = [
                    'subscription_id' => $result['subscription']->id,
                    'paypal_subscription_id' => $result['subscription_id'] ?? null,
                    'approval_url' => $result['approval_url'] ?? null,
                    'is_trial' => $result['is_trial'] ?? false,
                    'trial_end_date' => $result['trial_end_date'] ?? null,
                ];

                Log::info('Subscription created successfully', [
                    'employer_id' => $employer->id,
                    'plan_id' => $plan->id,
                    'subscription_id' => $result['subscription']->id,
                    'is_trial' => $result['is_trial'] ?? false
                ]);

                return response()->json([
                    'success' => true,
                    'data' => $responseData,
                    'message' => 'Subscription created successfully'
                ]);

            } catch (PaymentException $e) {
                Log::error('Subscription creation failed', [
                    'error' => $e->getMessage(),
                    'employer_id' => Auth::user()->employer->id ?? null
                ]);

                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage()
                ], 400);

            } catch (\Exception $e) {
                Log::error('Unexpected error during subscription creation', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'An unexpected error occurred. Please try again.'
                ], 500);
            }
        });
    }

    /**
     * Complete Stripe checkout session
     */
    public function completeStripeCheckout(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'session_id' => 'sometimes|string'
            ]);

            $result = $this->stripeService->completeCheckoutSession($request->session_id);

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['error']
                ], 400);
            }

            Log::info('Stripe checkout completed successfully', [
                'session_id' => $request->session_id,
                'employer_id' => Auth::user()->employer->id ?? null
            ]);

            return response()->json([
                'success' => true,
                'data' => $result,
                'message' => 'Checkout completed successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to complete Stripe checkout', [
                'session_id' => $request->session_id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to complete checkout'
            ], 500);
        }
    }

    // ========================================
    // SUBSCRIPTION MANAGEMENT
    // ========================================

    /**
     * Cancel subscription with confirmation
     */
    public function cancelSubscription(Request $request): JsonResponse
    {
        try {
            $employer = Auth::user()->employer;
            $subscription = $employer->activeSubscription;

            if (!$subscription) {
                return response()->json([
                    'success' => false,
                    'message' => 'No active subscription found'
                ], 404);
            }

            $success = $this->paypalService->cancelSubscription($subscription);

            if (!$success) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to cancel subscription. Please try again or contact support.'
                ], 400);
            }

            Log::info('Subscription cancelled successfully', [
                'employer_id' => $employer->id,
                'subscription_id' => $subscription->id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Subscription cancelled successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to cancel subscription', [
                'error' => $e->getMessage(),
                'employer_id' => Auth::user()->employer->id ?? null
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel subscription'
            ], 500);
        }
    }

    /**
     * Suspend subscription
     */
    public function suspendSubscription(Request $request): JsonResponse
    {
        try {
            $employer = Auth::user()->employer;
            $subscription = $employer->activeSubscription;

            if (!$subscription) {
                return response()->json([
                    'success' => false,
                    'message' => 'No active subscription found'
                ], 404);
            }

            $success = $this->paypalService->suspendSubscription($subscription);

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

        } catch (\Exception $e) {
            Log::error('Failed to suspend subscription', [
                'error' => $e->getMessage(),
                'employer_id' => Auth::user()->employer->id ?? null
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to suspend subscription'
            ], 500);
        }
    }

    /**
     * Resume subscription
     */
    public function resumeSubscription(Request $request): JsonResponse
    {
        try {
            $employer = Auth::user()->employer;
            $subscriptionId = $request->input('subscription_id');

            $subscription = $employer->subscriptions()
                ->where('id', $subscriptionId)
                ->where('status', Subscription::STATUS_SUSPENDED)
                ->first();

            if (!$subscription) {
                return response()->json([
                    'success' => false,
                    'message' => 'No suspended subscription found'
                ], 404);
            }

            $success = $this->paypalService->resumeSubscription($subscription);

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

        } catch (\Exception $e) {
            Log::error('Failed to resume subscription', [
                'error' => $e->getMessage(),
                'employer_id' => Auth::user()->employer->id ?? null
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to resume subscription'
            ], 500);
        }
    }

    /**
     * Get employer's subscription history
     */
    public function getSubscriptions(): JsonResponse
    {
        try {
            $employer = Auth::user()->employer;
            $subscriptions = $employer->subscriptions()
                ->with('plan')
                ->orderBy('created_at', 'desc')
                ->get();

            // Enrich subscription data
            $subscriptions->each(function ($subscription) {
                $subscription->formatted_amount = $subscription->getFormattedAmount();
                $subscription->remaining_trial_days = $subscription->getRemainingTrialDays();
                $subscription->days_until_next_billing = $subscription->getDaysUntilNextBilling();
            });

            return response()->json([
                'success' => true,
                'data' => $subscriptions,
                'message' => 'Subscriptions retrieved successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve subscriptions', [
                'error' => $e->getMessage(),
                'employer_id' => Auth::user()->employer->id ?? null
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve subscriptions'
            ], 500);
        }
    }

    /**
     * Capture PayPal payment
     */
    public function capturePayPalPayment(Request $request): JsonResponse
    {
        try {
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

            Log::info('PayPal payment captured successfully', [
                'order_id' => $request->order_id,
                'employer_id' => Auth::user()->employer->id ?? null
            ]);

            return response()->json([
                'success' => true,
                'data' => $result,
                'message' => 'Payment captured successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to capture PayPal payment', [
                'order_id' => $request->order_id ?? 'unknown',
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to capture payment'
            ], 500);
        }
    }

    // ========================================
    // PRIVATE HELPER METHODS
    // ========================================

    /**
     * Process one-time payment with trial logic
     */
    private function processOneTimePayment(Employer $employer, Plan $plan, CreatePaymentRequest $request): array
    {
        // Check if employer is eligible for trial
        if (!$employer->hasUsedTrial() && $plan->hasTrial()) {
            return $this->createTrialSubscription($employer, $plan, $request->payment_provider);
        }

        // Proceed with regular one-time payment
        return match ($request->payment_provider) {
            'stripe' => $this->stripeService->createOneTimePayment($employer, $plan),
            'paypal' => $this->paypalService->createOneTimePayment($employer, $plan),
            default => throw new PaymentException('Unsupported payment provider')
        };
    }

    /**
     * Create trial subscription for one-time payment plans
     */
    private function createTrialSubscription(Employer $employer, Plan $plan, string $paymentProvider): array
    {
        // Mark trial as used
        $employer->markTrialAsUsed();

        // Create trial subscription
        $result = match ($paymentProvider) {
            'stripe' => $this->stripeService->createSubscription($employer, $plan),
            'paypal' => $this->paypalService->createSubscription($employer, $plan),
            default => throw new PaymentException('Unsupported payment provider')
        };

        if ($result['success']) {
            Log::info('Trial subscription created for one-time plan', [
                'employer_id' => $employer->id,
                'plan_id' => $plan->id,
                'trial_days' => $plan->getTrialPeriodDays()
            ]);
        }

        return $result;
    }

    /**
     * Validate upgrade request
     */
    private function validateUpgrade(Employer $employer, Plan $newPlan): array
    {
        $activeSubscription = $employer->activeSubscription;

        if (!$activeSubscription) {
            return [
                'valid' => false,
                'message' => 'No active subscription found to upgrade from'
            ];
        }

        // Check if trying to upgrade to the same plan
        if ($activeSubscription->plan_id === $newPlan->id) {
            return [
                'valid' => false,
                'message' => 'You are already subscribed to this plan. Please select a different plan to upgrade.'
            ];
        }

        // Additional validation: Check if it's actually an upgrade (higher price)
        if ($newPlan->price <= $activeSubscription->plan->price) {
            return [
                'valid' => false,
                'message' => 'You can only upgrade to a higher-tier plan. For downgrades, please contact support.'
            ];
        }

        return ['valid' => true];
    }

    /**
     * Handle upgrade by managing existing subscription
     */
    private function handleUpgrade(Employer $employer): void
    {
        $activeSubscription = $employer->activeSubscription;

        if ($activeSubscription) {
            // TODO: Implement upgrade strategy based on business requirements
            // Option 1: Cancel existing subscription immediately
            // $this->cancelExistingSubscription($activeSubscription);

            // Option 2: Suspend existing subscription
            // $this->suspendExistingSubscription($activeSubscription);

            // Option 3: Let both run until the old one expires naturally
            // (Current implementation - no action taken)

            Log::info('Upgrade processed - existing subscription management', [
                'employer_id' => $employer->id,
                'existing_subscription_id' => $activeSubscription->id,
                'existing_plan_id' => $activeSubscription->plan_id,
                'strategy' => 'no_action' // Update this based on chosen strategy
            ]);
        }
    }

    /**
     * Cancel existing subscription during upgrade
     */
    private function cancelExistingSubscription(Subscription $subscription): void
    {
        $success = $this->paypalService->cancelSubscription($subscription);

        if ($success) {
            Log::info('Existing subscription cancelled during upgrade', [
                'subscription_id' => $subscription->id,
                'employer_id' => $subscription->employer_id
            ]);
        } else {
            Log::error('Failed to cancel existing subscription during upgrade', [
                'subscription_id' => $subscription->id,
                'employer_id' => $subscription->employer_id
            ]);
        }
    }

    /**
     * Suspend existing subscription during upgrade
     */
    private function suspendExistingSubscription(Subscription $subscription): void
    {
        $success = $this->paypalService->suspendSubscription($subscription);

        if ($success) {
            Log::info('Existing subscription suspended during upgrade', [
                'subscription_id' => $subscription->id,
                'employer_id' => $subscription->employer_id
            ]);
        } else {
            Log::error('Failed to suspend existing subscription during upgrade', [
                'subscription_id' => $subscription->id,
                'employer_id' => $subscription->employer_id
            ]);
        }
    }
}
