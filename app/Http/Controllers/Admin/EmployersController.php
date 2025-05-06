<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\EmployerResource;
use App\Http\Resources\JobApplicationResource;
use App\Http\Resources\JobResource;
use App\Http\Resources\SubscriptionResource;
use App\Models\Employer;
use App\Services\AdminService;
use App\Services\EmployerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * Controller for admin employer management
 */
class EmployersController extends Controller implements HasMiddleware
{
    /**
     * Admin service instance
     *
     * @var AdminService
     */
    protected AdminService $adminService;

    /**
     * Employer service instance
     *
     * @var EmployerService
     */
    protected EmployerService $employerService;

    /**
     * Create a new controller instance.
     *
     * @param AdminService $adminService
     * @param EmployerService $employerService
     * @return void
     */
    public function __construct(AdminService $adminService, EmployerService $employerService)
    {
        $this->adminService = $adminService;
        $this->employerService = $employerService;
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
     * Get employers list
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $query = Employer::with(['user', 'activeSubscription.plan'])
            ->withCount('jobs');

        // Apply filters
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function($q) use ($search) {
                $q->whereLike('company_name', "%{$search}%")
                    ->orWhereHas('user', function($q) use ($search) {
                        $q->whereLike('first_name', "%{$search}%")
                            ->orWhereLike('last_name', "%{$search}%")
                            ->orWhereLike('email', "%{$search}%");
                    });
            });
        }

        if ($request->has('is_featured')) {
            $isFeatured = $request->input('is_featured');
            $query->where('is_featured', $isFeatured === 'true');
        }

        if ($request->has('is_verified')) {
            $isVerified = $request->input('is_verified');
            $query->where('is_verified', $isVerified === 'true');
        }

        // Sort
        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $perPage = $request->input('per_page', config('app.pagination.per_page'));
        $employers = $query->paginate($perPage);

        return response()->paginatedSuccess(
//            EmployerResource::collection($employers),
            $employers,
            'Employers retrieved successfully'
        );
    }

    /**
     * Get employer details
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        $employer = Employer::with([
            'user',
            'jobs' => function($query) {
                $query->withCount('applications');
            },
            'jobs.applications' => function($query) {
                $query->with(['candidate.user']);
            },
            'activeSubscription.plan',
            'subscriptions.plan',
            'candidatePools',
            'notificationTemplates',
            // Get all applications across all jobs with candidate information
            'applications' => function($query) {
                $query->with(['candidate.user', 'job']);
            },
        ])
            ->withCount('jobs')
            ->findOrFail($id);

        // Add additional job statistics
        $employer->active_jobs_count = $employer->jobs()->where('is_active', true)->count();
        $employer->featured_jobs_count = $employer->jobs()->where('is_featured', true)->count();
        $employer->draft_jobs_count = $employer->jobs()->where('is_draft', true)->count();
        $employer->expired_jobs_count = $employer->jobs()
            ->where('deadline', '<', now())
            ->where('deadline', '!=', null)
            ->count();

        // Get application statistics
        $employer->total_applications_count = $employer->applications()->count();

        // Get application status counts
        $applicationStatusCounts = $employer->applications()
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $employer->application_status_counts = $applicationStatusCounts;

        // Get hired candidates (applications with status 'hired')
        $employer->hired_candidates = $employer->applications()
            ->where('status', 'hired')
            ->with(['candidate.user', 'job'])
            ->get();

        return response()->success(
            new EmployerResource($employer),
            'Employer retrieved successfully'
        );
    }

    /**
     * Get employers list
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getEmployers(Request $request): JsonResponse
    {
        $filters = $request->all();
        $employers = $this->employerService->getEmployers($filters);

        return response()->paginatedSuccess(
            EmployerResource::collection($employers),
            'Employers retrieved successfully'
        );
    }

    /**
     * Get employer profile details
     *
     * @param int $id
     * @return JsonResponse
     */
    public function getProfileDetails(int $id): JsonResponse
    {
        $employer = $this->employerService->getEmployerProfile($id);
        $statistics = $this->employerService->getEmployerStatistics($id);

        return response()->success([
            'employer' => new EmployerResource($employer),
            'statistics' => $statistics,
        ], 'Employer profile details retrieved successfully');
    }

    /**
     * Get employer job listings
     *
     * @param int $id
     * @param Request $request
     * @return JsonResponse
     */
    public function getJobListings(int $id, Request $request): JsonResponse
    {
        $filters = $request->all();
        $jobs = $this->employerService->getEmployerJobsForAdmin($id, $filters);

        return response()->paginatedSuccess(
            JobResource::collection($jobs),
            'Employer job listings retrieved successfully'
        );
    }

    /**
     * Get hired candidates for an employer
     *
     * @param int $id
     * @param Request $request
     * @return JsonResponse
     */
    public function getHiredCandidates(int $id, Request $request): JsonResponse
    {
        $filters = $request->all();
        $hiredCandidates = $this->employerService->getHiredCandidates($id, $filters);

        return response()->paginatedSuccess(
            JobApplicationResource::collection($hiredCandidates),
            'Hired candidates retrieved successfully'
        );
    }

    /**
     * Get employer transactions
     *
     * @param int $id
     * @param Request $request
     * @return JsonResponse
     */
    public function getTransactions(int $id, Request $request): JsonResponse
    {
        $filters = $request->all();
        $transactions = $this->employerService->getEmployerTransactions($id, $filters);

        return response()->paginatedSuccess(
            SubscriptionResource::collection($transactions),
            'Employer transactions retrieved successfully'
        );
    }

    /**
     * Delete employer
     *
     * @param int $id
     * @return JsonResponse
     */
    public function delete(int $id): JsonResponse
    {
        $this->employerService->deleteEmployer($id);

        return response()->success('Employer deleted successfully');
    }

    /**
     * Moderate account status
     *
     * @param int $id
     * @param Request $request
     * @return JsonResponse
     */
    public function moderateAccountStatus(int $id, Request $request): JsonResponse
    {
        $isActive = $request->input('is_active', true);
        $user = $this->employerService->moderateEmployerAccountStatus($id, $isActive);

        $status = $isActive ? 'activated' : 'deactivated';
        return response()->success($user, "Employer account {$status} successfully");
    }

    /**
     * Set shadow-ban status
     *
     * @param int $id
     * @param Request $request
     * @return JsonResponse
     */
    public function setShadowBan(int $id, Request $request): JsonResponse
    {
        $isShadowBanned = $request->input('is_shadow_banned', false);
        $user = $this->employerService->setShadowBanForEmployer($id, $isShadowBanned);

        $status = $isShadowBanned ? 'shadow banned' : 'removed from shadow ban';
        return response()->success($user, "Employer account {$status} successfully");
    }
}
