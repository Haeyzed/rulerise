<?php

namespace App\Http\Controllers\Employer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Employer\VerifySubscriptionRequest;
use App\Models\SubscriptionPlan;
use App\Services\EmployerService;
use Illuminate\Http\JsonResponse;

/**
 * Controller for subscription payments
 */
class SubscriptionPaymentController extends Controller
{
    /**
     * Employer service instance
     *
     * @var EmployerService
     */
    protected EmployerService $employerService;

    /**
     * Create a new controller instance.
     *
     * @param EmployerService $employerService
     * @return void
     */
    public function __construct(EmployerService $employerService)
    {
        $this->employerService = $employerService;
        $this->middleware('auth:api');
        $this->middleware('role:employer');
    }

    /**
     * Get subscription plans list
     *
     * @return JsonResponse
     */
    public function subscriptionList(): JsonResponse
    {
        $plans = SubscriptionPlan::where('is_active', true)->get();

        return response()->json([
            'success' => true,
            'data' => $plans,
        ]);
    }

    /**
     * Create payment link for subscription
     *
     * @param int $id
     * @return JsonResponse
     */
    public function createPaymentLink($id): JsonResponse
    {
        $user = auth()->user();
        $employer = $user->employer;

        $plan = SubscriptionPlan::findOrFail($id);

        // This would integrate with a payment gateway to create a payment link
        $paymentLink = "https://payment-gateway.com/pay/" . uniqid();

        return response()->json([
            'success' => true,
            'data' => [
                'payment_link' => $paymentLink,
                'plan' => $plan,
            ],
        ]);
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

        $plan = SubscriptionPlan::findOrFail($data['plan_id']);

        try {
            $subscription = $this->employerService->subscribeToPlan(
                $employer,
                $plan,
                $data
            );

            return response()->json([
                'success' => true,
                'message' => 'Subscription activated successfully',
                'data' => $subscription,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}
