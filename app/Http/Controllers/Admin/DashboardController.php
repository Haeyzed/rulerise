<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminService;
use Illuminate\Http\JsonResponse;

/**
 * Controller for admin dashboard
 */
class DashboardController extends Controller
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
        $this->middleware('auth:api');
        $this->middleware('role:admin');
    }

    /**
     * Get dashboard overview
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        $metrics = $this->adminService->getDashboardMetrics();

        return response()->json([
            'success' => true,
            'data' => $metrics,
        ]);
    }
}
