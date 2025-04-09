<?php

namespace App\Http\Controllers\Employer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Employer\VerifySubscriptionRequest;
use App\Models\SubscriptionPlan;
use App\Services\EmployerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * Controller for subscription payments
 */
class SubscriptionPaymentController extends Controller implements HasMiddleware
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
        $plans = SubscriptionPlan::query()->where('is_active', true)->get();

        return response()->success($plans, 'Subscription list retrieved successfully.');
    }

    /**
     * Create payment link for subscription
     *
     * @param int $id
     * @return JsonResponse
     */
    public function createPaymentLink(int $id): JsonResponse
    {
        $user = auth()->user();
        $employer = $user->employer;

        $plan = SubscriptionPlan::query()->findOrFail($id);

        // This would integrate with a payment gateway to create a payment link
        $paymentLink = "https://payment-gateway.com/pay/" . uniqid();

        return response()->success([
                'payment_link' => $paymentLink,
                'plan' => $plan,
        ], 'Payment link created successfully.');
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

        $plan = SubscriptionPlan::query()->findOrFail($data['plan_id']);

        try {
            $subscription = $this->employerService->subscribeToPlan(
                $employer,
                $plan,
                $data
            );

            return response()->success($subscription, 'Subscription activated successfully');
        } catch (\Exception $e) {
            return response()->badRequest($e->getMessage());
        }
    }
}
