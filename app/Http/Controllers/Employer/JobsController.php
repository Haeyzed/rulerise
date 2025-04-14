<?php

namespace App\Http\Controllers\Employer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Employer\JobRequest;
use App\Http\Resources\JobResource;
use App\Models\Job;
use App\Services\JobService;
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
            new Middleware(['auth:api','role:employer']),
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

        $query = $employer->jobs();

        // Apply filters
        if ($request->has('status')) {
            $status = $request->input('status');
            if ($status === 'open') {
                $query->where('is_active', true);
            } elseif ($status === 'close') {
                $query->where('is_active', false);
            }
        }

        if ($request->has('featured')) {
            $featured = $request->input('featured');
            $query->where('is_featured', $featured === 'true');
        }

        // Sort
        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $perPage = $request->input('per_page', 10);
        $jobs = $query->paginate($perPage);

        return response()->paginatedSuccess(new JobResource($jobs), 'Jobs retrieved successfully.');
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
            $job = $this->jobService->createJob($employer, $data);

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

        $job = $employer->jobs()->with(['category', 'applications.candidate.user'])->findOrFail($id);

        return response()->success(new JobResource($job), 'Job retrieved successfully.');
    }

    /**
     * Update job
     *
     * @param JobRequest $request
     * @return JsonResponse
     */
    public function update(int $id, JobRequest $request): JsonResponse
    {
        try {
            $user = auth()->user();
            $employer = $user->employer;
            $data = $request->validated();
            $job = $employer->jobs()->findOrFail($id);
            $job = $this->jobService->updateJob($job, $data);
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
    public function delete(int $id): JsonResponse
    {
        try {
            $user = auth()->user();
            $employer = $user->employer;
            $job = $employer->jobs()->findOrFail($id);
            $this->jobService->deleteJob($job);
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
        $user = auth()->user();
        $employer = $user->employer;

        $job = $employer->jobs()->findOrFail($id);

        $isActive = $request->input('is_active', true);

        $job = $this->jobService->setJobStatus($job, $isActive);

        $status = $isActive ? 'opened' : 'closed';

        return response()->success(new JsonResponse($job), "Job {$status} successfully");
    }

    /**
     * Publish job as featured
     *
     * @param int $id
     * @return JsonResponse
     */
    public function publishJob(int $id): JsonResponse
    {
        $user = auth()->user();
        $employer = $user->employer;

        $job = $employer->jobs()->findOrFail($id);

        try {
            $job = $this->jobService->setJobAsFeatured($job);

            return response()->success(new JsonResponse($job),'Job published as featured successfully');
        } catch (Exception $e) {
            return response()->badRequest($e->getMessage());
        }
    }
}
