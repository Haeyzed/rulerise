<?php

namespace App\Http\Controllers\Candidate;

use App\Http\Controllers\Controller;
use App\Http\Resources\JobResource;
use App\Services\CandidateDashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * Controller for candidate dashboard
 */
class DashboardsController extends Controller implements HasMiddleware
{
    /**
     * Dashboard service instance
     *
     * @var CandidateDashboardService
     */
    protected CandidateDashboardService $dashboardService;

    /**
     * Create a new controller instance.
     *
     * @param CandidateDashboardService $dashboardService
     * @return void
     */
    public function __construct(CandidateDashboardService $dashboardService)
    {
        $this->dashboardService = $dashboardService;
    }

    /**
     * Get the middleware that should be assigned to the controller.
     */
    public static function middleware(): array
    {
        return [
            new Middleware(['auth:api', 'role:candidate']),
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
        $candidate = $user->candidate;

        if (!$candidate) {
            return response()->error('Candidate profile not found');
        }

        // Get days parameter from request or use default (30)
        $days = $request->input('days', 30);

        // Get dashboard metrics
        $metrics = $this->dashboardService->getDashboardMetrics($candidate, $days);

        // Transform job collections to resources
        $metrics['newest_jobs'] = JobResource::collection($metrics['newest_jobs']);
        $metrics['recommended_jobs'] = JobResource::collection($metrics['recommended_jobs']);
        $metrics['saved_jobs'] = JobResource::collection($metrics['saved_jobs']);
        $metrics['applied_jobs'] = JobResource::collection($metrics['applied_jobs']);

        // Get latest blog posts
        $latestBlogPosts = $this->dashboardService->getLatestBlogPosts();

        return response()->success([
            'metrics' => $metrics,
            'latest_blog_posts' => $latestBlogPosts,
            'user' => [
                'name' => $user->first_name . ' ' . $user->last_name,
                'profile_picture_url' => $user->profile_picture_url,
            ],
        ], 'Dashboard data retrieved successfully');
    }

    /**
     * Get paginated jobs by type
     *
     * @param Request $request
     * @param string $type
     * @return JsonResponse
     */
    public function getJobs(Request $request, string $type): JsonResponse
    {
        $user = auth()->user();
        $candidate = $user->candidate;

        if (!$candidate) {
            return response()->error('Candidate profile not found');
        }

        // Get per_page parameter from request or use default (10)
        $perPage = $request->input('per_page', 10);

        // Get paginated jobs
        $jobs = $this->dashboardService->getPaginatedJobs($candidate, $type, $perPage);

        return response()->paginatedSuccess(JobResource::collection($jobs), 'Jobs retrieved successfully');
    }
}
