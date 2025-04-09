<?php

namespace App\Http\Controllers\Candidate;

use App\Http\Controllers\Controller;
use App\Http\Requests\Candidate\ApplyJobRequest;
use App\Http\Requests\Candidate\ReportJobRequest;
use App\Http\Requests\Candidate\SearchJobsRequest;
use App\Models\Job;
use App\Models\Resume;
use App\Services\JobService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
        $this->middleware('auth:api')->except(['index', 'show', 'similarJobs']);
        $this->middleware('role:candidate')->except(['index', 'show', 'similarJobs']);
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

        return response()->json([
            'success' => true,
            'data' => $jobs,
        ]);
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

        return response()->json([
            'success' => true,
            'data' => $job,
        ]);
    }

    /**
     * Save job
     *
     * @param int $id
     * @return JsonResponse
     */
    public function saveJob($id): JsonResponse
    {
        $user = auth()->user();
        $job = Job::findOrFail($id);

        try {
            $savedJob = $this->jobService->saveJob($job, $user->candidate);

            return response()->json([
                'success' => true,
                'message' => 'Job saved successfully',
                'data' => $savedJob,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
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

        $job = Job::findOrFail($data['job_id']);

        // Check if resume is provided
        $resume = null;
        if (!empty($data['resume_id'])) {
            $resume = Resume::findOrFail($data['resume_id']);

            // Check if the resume belongs to the authenticated user
            if ($resume->candidate_id !== $user->candidate->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized resume',
                ], 403);
            }
        }

        try {
            $application = $this->jobService->applyForJob(
                $job,
                $user->candidate,
                $resume,
                $data['cover_letter'] ?? null
            );

            return response()->json([
                'success' => true,
                'message' => 'Job application submitted successfully',
                'data' => $application,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get similar jobs
     *
     * @param int $id
     * @return JsonResponse
     */
    public function similarJobs($id): JsonResponse
    {
        $job = Job::findOrFail($id);
        $similarJobs = $this->jobService->getSimilarJobs($job);

        return response()->json([
            'success' => true,
            'data' => $similarJobs,
        ]);
    }

    /**
     * Report job
     *
     * @param int $id
     * @param ReportJobRequest $request
     * @return JsonResponse
     */
    public function reportJob($id, ReportJobRequest $request): JsonResponse
    {
        $user = auth()->user();
        $job = Job::findOrFail($id);
        $data = $request->validated();

        try {
            $report = $this->jobService->reportJob(
                $job,
                $user->candidate,
                $data['reason'],
                $data['description'] ?? null
            );

            return response()->json([
                'success' => true,
                'message' => 'Job reported successfully',
                'data' => $report,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}
