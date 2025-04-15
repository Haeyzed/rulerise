<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\Candidate\ApplyJobRequest;
use App\Http\Requests\Candidate\ReportJobRequest;
use App\Http\Requests\Candidate\SearchJobsRequest;
use App\Http\Resources\JobResource;
use App\Models\Job;
use App\Models\Resume;
use App\Services\JobService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * Controller for job-related operations for candidates
 */
class JobsController extends Controller
{
    /**
     * Job service instance
     *
     * @var JobService
     */
    protected JobService $jobService;

    /**
     * Create a new controller instance.
     *
     * @param JobService $jobService
     * @return void
     */
    public function __construct(JobService $jobService)
    {
        $this->jobService = $jobService;
    }

    /**
     * Search jobs
     *
     * @param SearchJobsRequest $request
     * @return JsonResponse
     */
    public function searchJobs(SearchJobsRequest $request): JsonResponse
    {
        try {
            // Get all request parameters as filters
            $filters = $request->validated();
            $perPage = $request->input('per_page', config('app.pagination.per_page'));

            $jobs = $this->jobService->searchJobs($filters, $perPage);

            return response()->paginatedSuccess(
                JobResource::collection($jobs),
                'Jobs retrieved successfully'
            );
        } catch (Exception $e) {
            return response()->serverError('Failed to search jobs', $e->getMessage());
        }
    }

    /**
     * Get job details
     *
     * @param int $id
     * @param Request $request
     * @return JsonResponse
     */
    public function show(int $id, Request $request): JsonResponse
    {
        try {
            $job = Job::with(['employer.user', 'category'])->findOrFail($id);

            // Record job view
            $this->jobService->recordJobView(
                $job,
                $request->ip(),
                $request->userAgent(),
                auth()->check() && auth()->user()->isCandidate() ? auth()->user()->candidate->id : null
            );

            return response()->success(new JobResource($job), 'Job details retrieved successfully');
        } catch (Exception $e) {
            return response()->error('Job not found or error retrieving job details', 404);
        }
    }

    /**
     * Get similar jobs
     *
     * @param int $id
     * @param Request $request
     * @return JsonResponse
     */
    public function similarJobs(int $id, Request $request): JsonResponse
    {
        try {
            $perPage = $request->input('per_page', 5);
            $job = Job::query()->findOrFail($id);
            $similarJobs = $this->jobService->getSimilarJobs($job, $perPage);

            return response()->success(
                JobResource::collection($similarJobs),
                'Similar jobs retrieved successfully'
            );
        } catch (Exception $e) {
            return response()->error('Error retrieving similar jobs', 404);
        }
    }

    /**
     * Get latest jobs
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function latestJobs(Request $request): JsonResponse
    {
        try {
            $perPage = $request->input('per_page', config('app.pagination.per_page'));
            $jobs = $this->jobService->getLatestJobs($perPage);

            return response()->paginatedSuccess(
                JobResource::collection($jobs),
                'Latest jobs retrieved successfully'
            );
        } catch (Exception $e) {
            return response()->serverError('Failed to retrieve latest jobs', $e->getMessage());
        }
    }

    /**
     * Get featured jobs
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function featuredJobs(Request $request): JsonResponse
    {
        try {
            $perPage = $request->input('per_page', config('app.pagination.per_page'));
            $jobs = $this->jobService->getFeaturedJobs($perPage);

            return response()->paginatedSuccess(
                JobResource::collection($jobs),
                'Featured jobs retrieved successfully'
            );
        } catch (Exception $e) {
            return response()->serverError('Failed to retrieve featured jobs', $e->getMessage());
        }
    }
}
