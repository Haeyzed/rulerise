<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\Employer\UpdateProfileRequest;
use App\Http\Requests\Employer\UploadLogoRequest;
use App\Http\Resources\EmployerResource;
use App\Http\Resources\JobResource;
use App\Models\Employer;
use App\Services\EmployerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * Controller for employer profile management
 */
class EmployersController extends Controller// implements HasMiddleware
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

//    /**
//     * Get the middleware that should be assigned to the controller.
//     */
//    public static function middleware(): array
//    {
//        return [
//            new Middleware(['auth:api','role:employer']),
//        ];
//    }

    /**
     * Get employer details
     *
     * @param int $id
     * @param Request $request
     * @return JsonResponse
     */
    public function show(int $id, Request $request): JsonResponse
    {
        $perPage = $request->input('per_page', config('app.pagination.per_page'));
        $employerData = $this->employerService->getEmployerDetails($id, $perPage);

        return response()->success([
            'employer' => new EmployerResource($employerData['employer']),
            'jobs' => JobResource::collection($employerData['jobs']),
            'open_jobs_count' => $employerData['open_jobs_count'],
            'total_job_views' => $employerData['total_job_views'],
        ], 'Employer details retrieved successfully');
    }

    /**
     * Get all employers
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->input('per_page', config('app.pagination.per_page'));
        $employers = Employer::query()
            //->where('is_verified', true)
            ->withCount([
                // Count active, non-draft, non-expired jobs
                'jobs' => function ($query) {
                    $query->where('is_active', true)
                        ->where('is_draft', false)
                        ->notExpired();
                }
            ])
            // Count all jobs regardless of status
            ->withCount([
                'jobs as total_jobs_count'
            ])
            // Count active jobs
            ->withCount([
                'jobs as active_jobs_count' => function ($query) {
                    $query->where('is_active', true);
                }
            ])
            // Count draft jobs
            ->withCount([
                'jobs as draft_jobs_count' => function ($query) {
                    $query->where('is_draft', true);
                }
            ])
            ->paginate($perPage);

        return response()->paginatedSuccess(
            EmployerResource::collection($employers),
            'Employers retrieved successfully'
        );
    }

    /**
     * Get employer profile
     *
     * @return JsonResponse
     */
    public function getProfile(): JsonResponse
    {
        $user = auth()->user();
        $profile = $this->employerService->getProfile($user);

        return response()->success($profile, 'Profile retrieved successfully.');
    }
}
