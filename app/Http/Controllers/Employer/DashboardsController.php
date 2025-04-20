<?php

namespace App\Http\Controllers\Employer;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use App\Services\MessageService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * Controller for employer dashboard
 */
class DashboardsController extends Controller implements HasMiddleware
{
    /**
     * Dashboard service instance
     *
     * @var DashboardService
     */
    protected DashboardService $dashboardService;

    /**
     * Message service instance
     *
     * @var MessageService
     */
    protected MessageService $messageService;

    /**
     * Create a new controller instance.
     *
     * @param DashboardService $dashboardService
     * @param MessageService $messageService
     * @return void
     */
    public function __construct(DashboardService $dashboardService, MessageService $messageService)
    {
        $this->dashboardService = $dashboardService;
        $this->messageService = $messageService;
    }

    /**
     * Get the middleware that should be assigned to the controller.
     */
    public static function middleware(): array
    {
        return [
            new Middleware(['auth:api', 'role:employer,employer_staff']),
        ];
    }

    /**
     * Get dashboard data
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $user = auth()->user();
        
        // Get employer (either directly or through relation for staff)
        $employer = $user->isEmployer() ? $user->employer : $user->employerRelation;
        
        if (!$employer) {
            return response()->error('Employer profile not found');
        }

        // Parse date range from request or use default (last 7 days)
        $endDate = Carbon::now();
        $startDate = Carbon::now()->subDays(6); // Last 7 days including today
        
        if ($request->has('start_date') && $request->has('end_date')) {
            $startDate = Carbon::parse($request->input('start_date'));
            $endDate = Carbon::parse($request->input('end_date'));
        }

        // Get dashboard metrics
        $metrics = $this->dashboardService->getEmployerDashboardMetrics($employer, [
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);

        // Get recent messages
        $recentMessages = $this->messageService->getRecentMessages($user, 5);

        return response()->success([
            'metrics' => $metrics,
            'recent_messages' => $recentMessages,
            'date_range' => [
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
            ],
        ], 'Dashboard data retrieved successfully');
    }
}
