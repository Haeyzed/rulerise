<?php

namespace App\Http\Controllers\Candidate;

use App\Http\Controllers\Controller;
use App\Http\Requests\Candidate\ApplyJobRequest;
use App\Http\Requests\Candidate\ReportJobRequest;
use App\Http\Requests\Candidate\SearchJobsRequest;
use App\Http\Resources\JobApplicationResource;
use App\Http\Resources\JobResource;
use App\Http\Resources\SavedJobResource;
use App\Models\Job;
use App\Models\Resume;
use App\Services\JobService;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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
        try {
            $filters = $request->validated();
            $perPage = $request->input('per_page', config('app.pagination.per_page'));
            $jobs = $this->jobService->searchJobs($filters, $perPage);
            return response()->paginatedSuccess($jobs, 'Jobs retrieved successfully');
        } catch (NotFoundHttpException|ModelNotFoundException $exception) {
            return response()->notFound('Jobs not found.');
        } catch (Exception $exception) {
            return response()->serverError('failed to retrieve jobs.', $exception->getMessage());
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
            $this->jobService->recordJobView(
                $job,
                $request->ip(),
                $request->userAgent(),
                auth()->check() && auth()->user()->isCandidate() ? auth()->user()->candidate->id : null
            );
            return response()->success($job, 'Job details retrieved successfully');
        } catch (NotFoundHttpException|ModelNotFoundException $exception) {
            return response()->notFound('Jobs not found.');
        } catch (Exception $exception) {
            return response()->serverError('failed to retrieve jobs.', $exception->getMessage());
        }
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

            return response()->created(new SavedJobResource($savedJob), $savedJob['is_saved'] ? 'Job saved successfully' : 'Job unsaved successfully');
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
                $data['cover_letter'] ?? null,
                $data['apply_via']
            );

            return response()->success(new JobApplicationResource($application), 'Job application submitted successfully');
        } catch (Exception $e) {
            return response()->badRequest($e->getMessage());
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
        $perPage = $request->input('per_page', config('app.pagination.per_page'));
        $job = Job::query()->findOrFail($id);
        $similarJobs = $this->jobService->getSimilarJobs($job, $perPage);

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

    /**
     * Get saved jobs for the authenticated candidate
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function savedJobs(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();
            $perPage = $request->input('per_page', config('app.pagination.per_page'));
            $savedJobs = $this->jobService->getSavedJobs($user->candidate, $perPage);

            return response()->paginatedSuccess(
                SavedJobResource::collection($savedJobs),
                'Saved jobs retrieved successfully'
            );
        } catch (Exception $e) {
            return response()->serverError('Failed to retrieve saved jobs', $e->getMessage());
        }
    }

    /**
     * Get applied jobs for the authenticated candidate
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function appliedJobs(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();
            $filters = $request->only(['status', 'date_from', 'date_to']);
            $perPage = $request->input('per_page', config('app.pagination.per_page'));
            $appliedJobs = $this->jobService->getAppliedJobs($user->candidate, $filters, $perPage);

            return response()->paginatedSuccess(
                JobApplicationResource::collection($appliedJobs),
                'Applied jobs retrieved successfully'
            );
        } catch (Exception $e) {
            return response()->serverError('Failed to retrieve applied jobs', $e->getMessage());
        }
    }

    /**
     * Get recommended jobs for the authenticated candidate
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function recommendedJobs(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();
            $perPage = $request->input('per_page', config('app.pagination.per_page'));
            $recommendedJobs = $this->jobService->getRecommendedJobs($user->candidate, $perPage);

            return response()->paginatedSuccess(
                JobResource::collection($recommendedJobs),
                'Recommended jobs retrieved successfully'
            );
        } catch (Exception $e) {
            return response()->serverError('Failed to retrieve recommended jobs', $e->getMessage());
        }
    }

    /**
     * Withdraw a job application
     *
     * @param int $id
     * @param WithdrawApplicationRequest $request
     * @return JsonResponse
     */
    public function withdrawApplication(int $id, WithdrawApplicationRequest $request): JsonResponse
    {
        $user = auth()->user();

        try {
            // Find the application and ensure it belongs to the authenticated candidate
            $application = JobApplication::where('id', $id)
                ->where('candidate_id', $user->candidate->id)
                ->firstOrFail();

            $data = $request->validated();
            $reason = $data['reason'] ?? null;

            $application = $this->jobService->withdrawApplication($application, $reason);

            return response()->success(
                new JobApplicationResource($application),
                'Your application has been successfully withdrawn'
            );
        } catch (ModelNotFoundException $e) {
            return response()->notFound('Application not found or you do not have permission to withdraw it.');
        } catch (Exception $e) {
            return response()->badRequest($e->getMessage());
        }
    }
}
