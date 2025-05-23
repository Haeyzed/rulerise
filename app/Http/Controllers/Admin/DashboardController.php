<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminAclService;
use App\Services\AdminService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * Controller for admin dashboard
 */
class DashboardController extends Controller implements HasMiddleware
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
     * Get dashboard overview
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

            $metrics = $this->adminService->getDashboardMetrics();

            return response()->success($metrics, 'Dashboard metrics retrieved successfully');
        } catch (Exception $e) {
            return response()->serverError($e->getMessage());
        }
    }
}
