<?php

namespace App\Http\Controllers\Candidate;

use App\Http\Controllers\Controller;
use App\Http\Requests\Candidate\ApplyJobRequest;
use App\Http\Requests\Candidate\ReportJobRequest;
use App\Http\Requests\Candidate\SearchJobsRequest;
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
class JobsController extends Controller implements HasMiddleware
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
     * Get the middleware that should be assigned to the controller.
     */
    public static function middleware(): array
    {
        return [
            new Middleware('auth:api', ['except' => ['index', 'show', 'similarJobs']]),
            new Middleware('role:candidate', ['except' => ['index', 'show', 'similarJobs']]),
        ];
    }

    /**
     * Get jobs list
     *
     * @param SearchJobsRequest $request
     * @return JsonResponse
     */
    public function index(SearchJobsRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $perPage = $request->input('per_page', 10);

        $jobs = $this->jobService->searchJobs($filters, $perPage);

        return response()->paginatedSuccess($jobs, 'Jobs retrieved successfully');
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

        return response()->success($job, 'Job details retrieved successfully');
    }

    /**
     * Save job
     *
     * @param int $id
     * @return JsonResponse
     */
    public function saveJob(int $id): JsonResponse
    {
        $user = auth()->user();
        $job = Job::query()->findOrFail($id);

        try {
            $savedJob = $this->jobService->saveJob($job, $user->candidate);

            return response()->created($savedJob, 'Job saved successfully');
        } catch (Exception $e) {
            return response()->badRequest($e->getMessage());
        }
    }

    /**
     * Apply for job
     *
     * @param ApplyJobRequest $request
     * @return JsonResponse
     */
    public function applyJob(ApplyJobRequest $request): JsonResponse
    {
        $user = auth()->user();
        $data = $request->validated();

        $job = Job::query()->findOrFail($data['job_id']);

        // Check if resume is provided
        $resume = null;
        if (!empty($data['resume_id'])) {
            $resume = Resume::query()->findOrFail($data['resume_id']);

            // Check if the resume belongs to the authenticated user
            if ($resume->candidate_id !== $user->candidate->id) {
                return response()->forbidden('Unauthorized resume');
            }
        }

        try {
            $application = $this->jobService->applyForJob(
                $job,
                $user->candidate,
                $resume,
                $data['cover_letter'] ?? null
            );

            return response()->success($application, 'Job application submitted successfully');
        } catch (Exception $e) {
            return response()->badRequest($e->getMessage());
        }
    }

    /**
     * Get similar jobs
     *
     * @param int $id
     * @return JsonResponse
     */
    public function similarJobs(int $id): JsonResponse
    {
        $job = Job::query()->findOrFail($id);
        $similarJobs = $this->jobService->getSimilarJobs($job);

        return response()->success($similarJobs, 'Similar jobs retrieved successfully');
    }

    /**
     * Report job
     *
     * @param int $id
     * @param ReportJobRequest $request
     * @return JsonResponse
     */
    public function reportJob(int $id, ReportJobRequest $request): JsonResponse
    {
        $user = auth()->user();
        $job = Job::query()->findOrFail($id);
        $data = $request->validated();

        try {
            $report = $this->jobService->reportJob(
                $job,
                $user->candidate,
                $data['reason'],
                $data['description'] ?? null
            );

            return response()->created($report, 'Report submitted successfully');
        } catch (Exception $e) {
            return response()->badRequest($e->getMessage());
        }
    }
}
