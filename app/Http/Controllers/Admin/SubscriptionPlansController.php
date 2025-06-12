<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SubscriptionPlanRequest;
use App\Models\SubscriptionPlan;
use App\Services\AdminAclService;
use App\Services\AdminService;
use Exception;
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
     * The Admin ACL service instance.
     *
     * @var AdminAclService
     */
    protected AdminAclService $adminAclService;

    /**
     * Create a new controller instance.
     *
     * @param AdminService $adminService
     * @param AdminAclService $adminAclService
     * @return void
     */
    public function __construct(AdminService $adminService, AdminAclService $adminAclService)
    {
        $this->adminService = $adminService;
        $this->adminAclService = $adminAclService;
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
        try {
            // Check permission using AdminAclService
            [$hasPermission, $errorMessage] = $this->adminAclService->hasPermission('view');
            if (!$hasPermission) {
                return response()->forbidden($errorMessage);
            }

            $plans = SubscriptionPlan::all();

            return response()->success($plans, 'OldSubscription Plans retrieved successfully.');
        } catch (Exception $e) {
            return response()->serverError($e->getMessage());
        }
    }

    /**
     * Create a new subscription plan
     *
     * @param SubscriptionPlanRequest $request
     * @return JsonResponse
     */
    public function store(SubscriptionPlanRequest $request): JsonResponse
    {
        try {
            // Check permission using AdminAclService
            [$hasPermission, $errorMessage] = $this->adminAclService->hasPermission('create');
            if (!$hasPermission) {
                return response()->forbidden($errorMessage);
            }

            $data = $request->validated();

            $plan = $this->adminService->createSubscriptionPlan($data);

            return response()->created($plan, 'OldSubscription plan created successfully');
        } catch (Exception $e) {
            return response()->serverError($e->getMessage());
        }
    }

    /**
     * Get subscription plan details
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        try {
            // Check permission using AdminAclService
            [$hasPermission, $errorMessage] = $this->adminAclService->hasPermission('view');
            if (!$hasPermission) {
                return response()->forbidden($errorMessage);
            }

            $plan = SubscriptionPlan::query()->findOrFail($id);

            return response()->success($plan, 'OldSubscription plan retrieved successfully');
        } catch (Exception $e) {
            return response()->serverError($e->getMessage());
        }
    }

    /**
     * Update subscription plan
     *
     * @param SubscriptionPlanRequest $request
     * @return JsonResponse
     */
    public function update(int $id, SubscriptionPlanRequest $request): JsonResponse
    {
        try {
            // Check permission using AdminAclService
            [$hasPermission, $errorMessage] = $this->adminAclService->hasPermission('update');
            if (!$hasPermission) {
                return response()->forbidden($errorMessage);
            }

            $plan = SubscriptionPlan::query()->findOrFail($id);
            $plan = $this->adminService->updateSubscriptionPlan($plan, $request->validated());
            return response()->success($plan, 'OldSubscription plan updated successfully');
        } catch (Exception $e) {
            return response()->serverError($e->getMessage());
        }
    }

    /**
     * Delete subscription plan
     *
     * @param int $id
     * @return JsonResponse
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            // Check permission using AdminAclService
            [$hasPermission, $errorMessage] = $this->adminAclService->hasPermission('delete');
            if (!$hasPermission) {
                return response()->forbidden($errorMessage);
            }

            $plan = SubscriptionPlan::query()->findOrFail($id);
            $plan->delete();

            return response()->success(null, 'OldSubscription plan deleted successfully');
        } catch (Exception $e) {
            return response()->serverError($e->getMessage());
        }
    }

    /**
     * Set subscription plan active status
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function setActive(Request $request): JsonResponse
    {
        try {
            // Check permission using AdminAclService
            [$hasPermission, $errorMessage] = $this->adminAclService->hasPermission('update');
            if (!$hasPermission) {
                return response()->forbidden($errorMessage);
            }

            $id = $request->input('id');
            $isActive = $request->input('is_active', true);

            $plan = SubscriptionPlan::query()->findOrFail($id);

            $plan = $this->adminService->setSubscriptionPlanStatus($plan, $isActive);

            $status = $isActive ? 'activated' : 'deactivated';

            return response()->success($plan,"OldSubscription plan {$status} successfully");
        } catch (Exception $e) {
            return response()->serverError($e->getMessage());
        }
    }
}
