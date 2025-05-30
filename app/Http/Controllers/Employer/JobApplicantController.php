<?php

namespace App\Http\Controllers\Employer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Employer\BatchChangeHiringStageRequest;
use App\Http\Requests\Employer\ChangeHiringStageRequest;
use App\Models\Job;
use App\Models\JobApplication;
use App\Services\JobService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * Controller for managing job applicants
 */
class JobApplicantController extends Controller implements HasMiddleware
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
            new Middleware(['auth:api','role:employer,employer_staff']),
        ];
    }



    /**
     * Filter applicants by job
     *
     * @param int $id
     * @param Request $request
     * @return JsonResponse
     */
    public function filterApplicantsByJob(int $id, Request $request): JsonResponse
    {
        $user = auth()->user();
        $employer = $user->employer;

        $job = $employer->jobs()->findOrFail($id);

        $query = $job->applications()->with([
            'candidate.user',
            'resume'
        ]);

        // Prepare filters
        $filters = [];
        if ($request->has('status')) {
            $filters['status'] = $request->input('status');
        }
        if ($request->has('featured')) {
            $filters['featured'] = $request->input('featured');
        }

        // Get sort parameters
        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');
        $perPage = $request->input('per_page', config('app.pagination.per_page'));

        $result = $this->jobService->getApplicantByJobs(
            $employer,
            $job->id,
            $filters,
            $sortBy,
            $sortOrder,
            $perPage
        );

        // Extract jobs and counts from the result
        $jobApplications = $result['job_applications'];
        $counts = $result['counts'];

        // Add pagination data
        $paginationData = [
            'current_page' => $jobApplications->currentPage(),
            'last_page' => $jobApplications->lastPage(),
            'per_page' => $jobApplications->perPage(),
            'total' => $jobApplications->total(),
        ];

        return response()->json([
            'success' => true,
            'message' => 'Applicants Jobs retrieved successfully.',
            'data' => [
                'counts' => $counts,
                'jobs' => $jobApplications,
                'meta' => $paginationData,
            ]
        ]);
    }

    /**
     * Change hiring stage
     *
     * @param ChangeHiringStageRequest $request
     * @return JsonResponse
     */
    public function changeHiringStage(ChangeHiringStageRequest $request): JsonResponse
    {
        $user = auth()->user();
        $employer = $user->employer;
        $data = $request->validated();

        $application = JobApplication::query()->findOrFail($data['application_id']);

        // Check if the application belongs to a job owned by this employer
        $job = Job::query()->findOrFail($application->job_id);
        if ($job->employer_id !== $employer->id) {
            return response()->forbidden('This application does not belong to a job owned by this employer');
        }

        $application = $this->jobService->changeApplicationStatus(
            $application,
            $data['status'],
            $data['notes'] ?? null
        );

        return response()->success($application, 'Hiring stage updated successfully');
    }

    /**
     * Change hiring stage for multiple applications
     *
     * @param BatchChangeHiringStageRequest $request
     * @return JsonResponse
     */
    public function batchChangeHiringStage(BatchChangeHiringStageRequest $request): JsonResponse
    {
        $user = auth()->user();
        $employer = $user->employer;
        $data = $request->validated();

        $applicationIds = $data['application_ids'];
        $status = $data['status'];
        $notes = $data['notes'] ?? null;

        $results = $this->jobService->batchChangeApplicationStatus($applicationIds, $status, $notes);

        return response()->success([
            'results' => $results,
            'total' => count($applicationIds),
            'successful' => count($results['success']),
            'failed' => count($results['failed'])
        ], 'Batch hiring stage update processed');
    }

    /**
     * View application
     *
     * @param int $id
     * @return JsonResponse
     */
    public function viewApplication(int $id): JsonResponse
    {
        $user = auth()->user();
        $employer = $user->employer;

        $application = JobApplication::with(['candidate.user', 'resume', 'job'])->findOrFail($id);

        // Check if the application belongs to a job owned by this employer
        $job = Job::query()->findOrFail($application->job_id);
        if ($job->employer_id !== $employer->id) {
            return response()->forbidden('This application does not belong to a job owned by this employer');
        }

        return response()->success($application, 'Application viewed successfully');
    }
}
