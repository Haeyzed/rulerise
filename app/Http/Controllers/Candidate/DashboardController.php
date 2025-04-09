<?php

namespace App\Http\Controllers\Candidate;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * Controller for candidate dashboard
 */
class DashboardController extends Controller implements HasMiddleware
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
    }

    /**
     * Get the middleware that should be assigned to the controller.
     */
    public static function middleware(): array
    {
        return [
            new Middleware(['auth:api','role:candidate']),
        ];
    }

    /**
     * Get dashboard metrics
     *
     * @return JsonResponse
     */
    public function metrics(): JsonResponse
    {
        $user = auth()->user();
        $candidate = $user->candidate;

        // Get metrics
        $totalApplications = $candidate->jobApplications()->count();
        $pendingApplications = $candidate->jobApplications()->whereIn('status', ['applied', 'screening'])->count();
        $interviewApplications = $candidate->jobApplications()->where('status', 'interview')->count();
        $offerApplications = $candidate->jobApplications()->where('status', 'offer')->count();
        $rejectedApplications = $candidate->jobApplications()->where('status', 'rejected')->count();
        $savedJobs = $candidate->savedJobs()->count();
        $profileViews = $candidate->profileViewCounts()->count();

        // Get recent applications
        $recentApplications = $candidate->jobApplications()
            ->with(['job.employer'])
            ->latest()
            ->limit(5)
            ->get();

        return response()->success([
                'totalApplications' => $totalApplications,
                'pendingApplications' => $pendingApplications,
                'interviewApplications' => $interviewApplications,
                'offerApplications' => $offerApplications,
                'rejectedApplications' => $rejectedApplications,
                'savedJobs' => $savedJobs,
                'profileViews' => $profileViews,
                'recentApplications' => $recentApplications,
            ], 'Dashboard metrics retrieved successfully');
    }
}
