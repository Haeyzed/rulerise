<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SubscriptionPlanRequest;
use App\Models\SubscriptionPlan;
use App\Services\AdminService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * Controller for admin subscription plan management
 */
class SubscriptionPlansController extends Controller implements HasMiddleware
{
    /**
     * Admin service instance
     *
     * @var AdminService
     */
    protected AdminService $adminService;

    /**
     * Create a new controller instance.
     *
     * @param AdminService $adminService
     * @return void
     */
    public function __construct(AdminService $adminService)
    {
        $this->adminService = $adminService;
    }

    /**
     * Get the middleware that should be assigned to the controller.
     */
    public static function middleware(): array
    {
        return [
            new Middleware(['auth:api','role:admin']),
        ];
    }

    /**
     * Get subscription plans list
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        $plans = SubscriptionPlan::all();

        return response()->success($plans, 'Subscription Plans retrieved successfully.');
    }

    /**
     * Create a new subscription plan
     *
     * @param SubscriptionPlanRequest $request
     * @return JsonResponse
     */
    public function store(SubscriptionPlanRequest $request): JsonResponse
    {
        $data = $request->validated();

        $plan = $this->adminService->createSubscriptionPlan($data);

        return response()->created($plan, 'Subscription plan created successfully');
    }

    /**
     * Get subscription plan details
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        $plan = SubscriptionPlan::query()->findOrFail($id);

        return response()->success($plan, 'Subscription plan retrieved successfully');
    }

    /**
     * Update subscription plan
     *
     * @param SubscriptionPlanRequest $request
     * @return JsonResponse
     */
    public function update(SubscriptionPlanRequest $request, SubscriptionPlan $subscriptionPlan): JsonResponse
    {
        $data = $request->validated();

        $plan = $this->adminService->updateSubscriptionPlan($subscriptionPlan, $data);

        return response()->success($plan, 'Subscription plan updated successfully');
    }

    /**
     * Delete subscription plan
     *
     * @param int $id
     * @return JsonResponse
     */
    public function destroy(int $id): JsonResponse
    {
        $plan = SubscriptionPlan::query()->findOrFail($id);
        $plan->delete();

        return response()->success(null, 'Subscription plan deleted successfully');
    }

    /**
     * Set subscription plan active status
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function setActive(Request $request): JsonResponse
    {
        $id = $request->input('id');
        $isActive = $request->input('is_active', true);

        $plan = SubscriptionPlan::query()->findOrFail($id);

        $plan = $this->adminService->setSubscriptionPlanStatus($plan, $isActive);

        $status = $isActive ? 'activated' : 'deactivated';

        return response()->success($plan,"Subscription plan {$status} successfully");
    }
}
