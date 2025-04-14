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
     * Get jobs list
     *
     * @param SearchJobsRequest $request
     * @return JsonResponse
     */
    public function searchJobs(SearchJobsRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $perPage = $request->input('per_page', config('app.pagination.per_page'));
        $jobs = $this->jobService->searchJobs($filters, $perPage);

        return response()->paginatedSuccess(
            JobResource::collection($jobs),
            'Jobs retrieved successfully'
        );
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
        $job = Job::with(['employer.user', 'category'])->findOrFail($id);

        // Record job view
        $this->jobService->recordJobView(
            $job,
            $request->ip(),
            $request->userAgent(),
            auth()->check() && auth()->user()->isCandidate() ? auth()->user()->candidate->id : null
        );

        return response()->success(new JobResource($job), 'Job details retrieved successfully');
    }

    /**
     * Get similar jobs
     *
     * @param int $id
     * @return JsonResponse
     */
    public function similarJobs(int $id, Request $request): JsonResponse
    {
        $perPage = $request->input('per_page', config('app.pagination.per_page'));
        $job = Job::query()->findOrFail($id);
        $similarJobs = $this->jobService->getSimilarJobs($job, $perPage);

        return response()->success(JobResource::collection($similarJobs), 'Similar jobs retrieved successfully');
    }

    /**
     * Get latest jobs
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function latestJobs(Request $request): JsonResponse
    {
        $perPage = $request->input('per_page', config('app.pagination.per_page'));
        $jobs = $this->jobService->getLatestJobs($perPage);

        return response()->success(
            JobResource::collection($jobs),
            'Latest jobs retrieved successfully'
        );
    }
}
