<?php

namespace App\Http\Controllers\Employer;

use App\Http\Controllers\Controller;
use App\Models\Employer;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Services\Payment\PaymentService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class PaymentController extends Controller
{
    /**
     * @var PaymentService
     */
    protected PaymentService $paymentService;

    /**
     * Constructor
     *
     * @param PaymentService $paymentService
     */
    public function __construct(PaymentService $paymentService)
    {
        $this->paymentService = $paymentService;
    }

    /**
     * Get available payment providers
     *
     * @return JsonResponse
     */
    public function getProviders(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'providers' => $this->paymentService->getAvailableProviders(),
            ]
        ]);
    }

    /**
     * Get available subscription plans
     *
     * @return JsonResponse
     */
    public function getSubscriptionPlans(): JsonResponse
    {
        $plans = SubscriptionPlan::where('is_active', true)->get();

        return response()->json([
            'success' => true,
            'data' => [
                'plans' => $plans
            ]
        ]);
    }

    /**
     * Get employer's subscriptions
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getEmployerSubscriptions(Request $request): JsonResponse
    {
        $user = Auth::user();
        $employer = $user->employer;

        if (!$employer) {
            return response()->json([
                'success' => false,
                'message' => 'Employer profile not found',
            ], 404);
        }

        $subscriptions = $employer->subscriptions()
            ->with('plan')
            ->latest()
            ->get();

        $activeSubscription = $employer->activeSubscription;

        return response()->json([
            'success' => true,
            'data' => [
                'subscriptions' => $subscriptions,
                'active_subscription' => $activeSubscription ? $activeSubscription->load('plan') : null,
            ]
        ]);
    }

    /**
     * Create payment intent/order
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function createPaymentIntent(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'plan_id' => 'required|exists:subscription_plans,id',
                'provider' => 'required|string|in:stripe,paypal',
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
            $provider = $request->provider;

            $result = $this->paymentService->createPaymentIntent(
                $employer,
                $plan,
                $provider,
                $request->except(['plan_id', 'provider'])
            );

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (Exception $e) {
            Log::error('Payment intent creation failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to create payment: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Process a successful payment
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function processPayment(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'provider' => 'required|string|in:stripe,paypal',
                // Other fields depend on the provider
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $provider = $request->provider;

            $subscription = $this->paymentService->processPayment(
                $provider,
                $request->except('provider')
            );

            return response()->success($subscription->load('plan'), 'Payment processed successfully');
        } catch (Exception $e) {
            Log::error('Payment processing failed: ' . $e->getMessage());

            return response()->serverError('Failed to process payment: ' . $e->getMessage());
        }
    }

    /**
     * Handle PayPal payment success
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function handlePayPalSuccess(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'token' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $subscription = $this->paymentService->processPayment('paypal', [
                'order_id' => $request->token,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Payment processed successfully',
                'data' => [
                    'subscription' => $subscription->load('plan'),
                ],
            ]);
        } catch (Exception $e) {
            Log::error('PayPal payment processing failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to process payment: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Handle PayPal payment cancellation
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function handlePayPalCancel(Request $request): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Payment was cancelled by the user',
        ]);
    }

    /**
     * Handle Stripe webhook
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function handleStripeWebhook(Request $request): JsonResponse
    {
        try {
            $payload = $request->all();
            $success = $this->paymentService->handleWebhook('stripe', $payload);

            if ($success) {
                return response()->json(['success' => true]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to process webhook',
            ], 500);
        } catch (Exception $e) {
            Log::error('Stripe webhook handling failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to process webhook: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Handle PayPal webhook
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function handlePayPalWebhook(Request $request): JsonResponse
    {
        try {
            $payload = $request->all();
            $success = $this->paymentService->handleWebhook('paypal', $payload);

            if ($success) {
                return response()->json(['success' => true]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to process webhook',
            ], 500);
        } catch (Exception $e) {
            Log::error('PayPal webhook handling failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to process webhook: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Cancel a subscription
     *
     * @param Request $request
     * @param int $subscriptionId
     * @return JsonResponse
     */
    public function cancelSubscription(Request $request, int $subscriptionId): JsonResponse
    {
        try {
            $user = Auth::user();
            $employer = $user->employer;

            if (!$employer) {
                return response()->json([
                    'success' => false,
                    'message' => 'Employer profile not found',
                ], 404);
            }

            $subscription = $employer->subscriptions()->findOrFail($subscriptionId);

            $success = $this->paymentService->cancelSubscription($subscription);

            if ($success) {
                return response()->json([
                    'success' => true,
                    'message' => 'Subscription cancelled successfully',
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel subscription',
            ], 500);
        } catch (Exception $e) {
            Log::error('Subscription cancellation failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel subscription: ' . $e->getMessage(),
            ], 500);
        }
    }
}
