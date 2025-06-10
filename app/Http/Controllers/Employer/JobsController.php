<?php

namespace App\Http\Controllers\Employer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Employer\JobRequest;
use App\Http\Resources\JobResource;
use App\Services\EmployerService;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Controller for employer job management
 */
class JobsController extends Controller implements HasMiddleware
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
     * Get employer jobs
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $user = auth()->user();
        $employer = $user->employer;

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

        $result = $this->employerService->getEmployerJobs(
            $employer,
            $filters,
            $sortBy,
            $sortOrder,
            $perPage
        );

        // Extract jobs and counts from the result
        $jobs = $result['jobs'];
        $counts = $result['counts'];

        // Add pagination data
        $paginationData = [
            'current_page' => $jobs->currentPage(),
            'last_page' => $jobs->lastPage(),
            'per_page' => $jobs->perPage(),
            'total' => $jobs->total(),
        ];

        return response()->json([
            'success' => true,
            'message' => 'Jobs retrieved successfully.',
            'data' => [
                'counts' => $counts,
                'jobs' => $jobs,
                'meta' => $paginationData,
            ]
        ]);
    }

    /**
     * Create a new job
     *
     * @param JobRequest $request
     * @return JsonResponse
     */
    public function store(JobRequest $request): JsonResponse
    {
        $user = auth()->user();
        $employer = $user->employer;
        $data = $request->validated();

        try {
            $job = $this->employerService->createEmployerJob($employer, $data);

            return response()->created(new JobResource($job), 'Job created successfully');
        } catch (Exception $e) {
            return response()->badRequest($e->getMessage());
        }
    }

    /**
     * Get job details
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        $user = auth()->user();
        $employer = $user->employer;

        try {
            $job = $this->employerService->getEmployerJob($employer, $id);
            return response()->success($job, 'Job retrieved successfully.');
        } catch (ModelNotFoundException|NotFoundHttpException $e) {
            return response()->notFound('Job not found');
        }
    }

    /**
     * Update job
     *
     * @param int $id
     * @param JobRequest $request
     * @return JsonResponse
     */
    public function update(int $id, JobRequest $request): JsonResponse
    {
        try {
            $user = auth()->user();
            $employer = $user->employer;
            $data = $request->validated();

            $job = $this->employerService->updateEmployerJob($employer, $id, $data);
            return response()->success(new JobResource($job), 'Job updated successfully');
        } catch (ModelNotFoundException|NotFoundHttpException $e) {
            return response()->notFound('Job not found');
        } catch (Exception $exception) {
            return response()->serverError($exception->getMessage());
        }
    }

    /**
     * Delete job
     *
     * @param int $id
     * @return JsonResponse
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $user = auth()->user();
            $employer = $user->employer;

            $this->employerService->deleteEmployerJob($employer, $id);
            return response()->success(null, 'Job deleted successfully');
        } catch (ModelNotFoundException|NotFoundHttpException $e) {
            return response()->notFound('Job not found');
        } catch (Exception $exception) {
            return response()->serverError($exception->getMessage());
        }
    }

    /**
     * Set job open/close status
     *
     * @param int $id
     * @param Request $request
     * @return JsonResponse
     */
    public function setOpenClose(int $id, Request $request): JsonResponse
    {
        try {
            $user = auth()->user();
            $employer = $user->employer;
            $isActive = $request->input('is_active', true);

            $job = $this->employerService->setJobStatus($employer, $id, $isActive);
            $status = $isActive ? 'opened' : 'closed';

            return response()->success(new JobResource($job), "Job {$status} successfully");
        } catch (ModelNotFoundException|NotFoundHttpException $e) {
            return response()->notFound('Job not found');
        } catch (Exception $exception) {
            return response()->serverError($exception->getMessage());
        }
    }

    /**
     * Publish job as featured
     *
     * @param int $id
     * @return JsonResponse
     */
    public function publishJob(int $id): JsonResponse
    {
        try {
            $user = auth()->user();
            $employer = $user->employer;

            $job = $this->employerService->publishJobAsFeatured($employer, $id);
            return response()->success(new JobResource($job), 'Job published as featured successfully');
        } catch (ModelNotFoundException|NotFoundHttpException $e) {
            return response()->notFound('Job not found');
        } catch (Exception $e) {
            return response()->badRequest($e->getMessage());
        }
    }
}
