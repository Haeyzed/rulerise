<?php

namespace App\Http\Controllers\Employer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Employer\VerifySubscriptionRequest;
use App\Models\SubscriptionPlan;
use App\Services\SubscriptionPlanService;
use App\Services\SubscriptionService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Controller for subscription payments
 */
class SubscriptionPaymentController extends Controller implements HasMiddleware
{
    /**
     * Subscription service instance
     *
     * @var SubscriptionService
     */
    protected SubscriptionService $subscriptionService;

    /**
     * Subscription plan service instance
     *
     * @var SubscriptionPlanService
     */
    protected SubscriptionPlanService $subscriptionPlanService;

    /**
     * Create a new controller instance.
     *
     * @param SubscriptionService $subscriptionService
     * @param SubscriptionPlanService $subscriptionPlanService
     * @return void
     */
    public function __construct(
        SubscriptionService $subscriptionService,
        SubscriptionPlanService $subscriptionPlanService
    ) {
        $this->subscriptionService = $subscriptionService;
        $this->subscriptionPlanService = $subscriptionPlanService;
    }

    /**
     * Get the middleware that should be assigned to the controller.
     */
    public static function middleware(): array
    {
        return [
            new Middleware(['auth:api','role:employer']),
        ];
    }

    /**
     * Get subscription plans list
     *
     * @return JsonResponse
     */
    public function subscriptionList(): JsonResponse
    {
        $plans = $this->subscriptionPlanService->getActivePlans();
        return response()->success($plans, 'Subscription plans retrieved successfully.');
    }

    /**
     * Create payment link for subscription
     *
     * @param int $id
     * @param Request $request
     * @return JsonResponse
     */
    public function createPaymentLink(int $id, Request $request): JsonResponse
    {
        $user = auth()->user();
        $employer = $user->employer;
        $gateway = $request->input('gateway', SubscriptionService::GATEWAY_STRIPE);
        $callbackUrl = $request->input('callback_url', route('employer.subscription.verify'));

        try {
            $plan = $this->subscriptionPlanService->getPlan($id);

            $result = $this->subscriptionService->generatePaymentLink(
                $employer,
                $plan,
                $gateway,
                $callbackUrl
            );

            if (!$result['success']) {
                return response()->badRequest($result['message']);
            }

            return response()->success($result, 'Payment link created successfully.');
        } catch (\Exception $e) {
            return response()->badRequest($e->getMessage());
        }
    }

    /**
     * Verify subscription payment
     *
     * @param VerifySubscriptionRequest $request
     * @return JsonResponse
     */
    public function verifySubscription(VerifySubscriptionRequest $request): JsonResponse
    {
        $user = auth()->user();
        $employer = $user->employer;
        $data = $request->validated();

        try {
            $plan = $this->subscriptionPlanService->getPlan($data['plan_id']);
            $gateway = $data['gateway'] ?? SubscriptionService::GATEWAY_STRIPE;

            // Determine the reference based on the gateway
            $reference = match($gateway) {
                SubscriptionService::GATEWAY_STRIPE => $data['session_id'] ?? '',
                SubscriptionService::GATEWAY_PAYPAL => $data['paypal_order_id'] ?? '',
                default => $data['reference'] ?? '',
            };

            if (!$reference) {
                return response()->badRequest('Payment reference is required');
            }

            // Verify the payment first
            $verificationResult = $this->subscriptionService->verifyPayment($reference, $gateway);

            if (!$verificationResult['success']) {
                return response()->badRequest($verificationResult['message']);
            }

            // Create the subscription
            $subscription = $this->subscriptionService->subscribeToPlan(
                $employer,
                $plan,
                $data,
                $gateway,
                $request->file('receipt') ?? null
            );

            return response()->success($subscription, 'Subscription activated successfully');
        } catch (\Exception $e) {
            return response()->badRequest($e->getMessage());
        }
    }

    /**
     * Update subscription
     *
     * @param int $id
     * @param Request $request
     * @return JsonResponse
     */
    public function updateSubscription(int $id, Request $request): JsonResponse
    {
        $user = auth()->user();
        $employer = $user->employer;

        try {
            $subscription = $employer->subscriptions()->findOrFail($id);
            $plan = $this->subscriptionPlanService->getPlan($request->input('plan_id'));
            $gateway = $request->input('gateway', SubscriptionService::GATEWAY_STRIPE);

            $updatedSubscription = $this->subscriptionService->updateSubscription(
                $subscription,
                $plan,
                $request->all(),
                $gateway,
                $request->file('receipt') ?? null
            );

            return response()->success($updatedSubscription, 'Subscription updated successfully');
        } catch (\Exception $e) {
            return response()->badRequest($e->getMessage());
        }
    }

    /**
     * Activate a free trial subscription
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function activateFreeTrial(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'plan_id' => 'required|exists:subscription_plans,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $user = Auth::user();
            $employer = $user->employer;

            if (!$employer) {
                return response()->json([
                    'success' => false,
                    'message' => 'Employer profile not found',
                ], 404);
            }

            $plan = SubscriptionPlan::findOrFail($request->plan_id);

            // Verify this is actually a free plan
            if ($plan->price > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'This is not a free plan and requires payment',
                ], 400);
            }

            // Check if user already has an active subscription
            if ($employer->hasActiveSubscription()) {
                return response()->json([
                    'success' => false,
                    'message' => 'You already have an active subscription',
                ], 400);
            }

            // Calculate dates
            $startDate = now();
            $endDate = $startDate->copy()->addDays($plan->duration_days);

            // Create subscription
            $subscription = $employer->subscriptions()->create([
                'subscription_plan_id' => $plan->id,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'amount_paid' => 0,
                'currency' => $plan->currency,
                'payment_method' => 'free',
                'transaction_id' => 'free_trial_' . $employer->id . '_' . time(),
                'payment_reference' => 'free_trial',
                'job_posts_left' => $plan->job_posts_limit,
                'featured_jobs_left' => $plan->featured_jobs_limit,
                'cv_downloads_left' => $plan->resume_views_limit,
                'is_active' => true,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Free trial activated successfully',
                'data' => [
                    'subscription' => $subscription->load('plan'),
                ],
            ]);
        } catch (Exception $e) {
            Log::error('Free trial activation failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to activate free trial: ' . $e->getMessage(),
            ], 500);
        }
    }
}
