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
            return response()->notFound('Employer profile not found');
        }

        // Get time period from request (week, month, year) or default to week
        $period = $request->input('period', 'week');

        // Parse date range based on period
        $endDate = Carbon::now()->endOfDay();
        $startDate = $this->getStartDateForPeriod($period);

        // Override with custom date range if provided
        if ($request->has('start_date') && $request->has('end_date')) {
            $startDate = Carbon::parse($request->input('start_date'))->startOfDay();
            $endDate = Carbon::parse($request->input('end_date'))->endOfDay();
            // Determine period from custom date range
            $period = $this->determinePeriodFromDateRange($startDate, $endDate);
        }

        // Get dashboard metrics
        $metrics = $this->dashboardService->getEmployerDashboardMetrics($employer, [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'period' => $period,
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
            'period' => $period,
        ], 'Dashboard data retrieved successfully');
    }

    /**
     * Get start date based on the selected period
     *
     * @param string $period
     * @return Carbon
     */
    private function getStartDateForPeriod(string $period): Carbon
    {
        $now = Carbon::now();

        return match ($period) {
            'week' => $now->copy()->subDays(6)->startOfDay(), // Last 7 days including today
            'month' => $now->copy()->startOfMonth()->startOfDay(), // Current month
            'year' => $now->copy()->startOfYear()->startOfDay(), // Current year
            default => $now->copy()->subDays(6)->startOfDay(), // Default to week
        };
    }

    /**
     * Determine the period based on date range
     *
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @return string
     */
    private function determinePeriodFromDateRange(Carbon $startDate, Carbon $endDate): string
    {
        $diffInDays = $startDate->diffInDays($endDate);

        if ($diffInDays <= 7) {
            return 'week';
        } elseif ($diffInDays <= 31) {
            return 'month';
        } else {
            return 'year';
        }
    }
}
