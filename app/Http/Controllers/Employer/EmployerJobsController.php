<?php

namespace App\Http\Controllers\Employer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Employer\JobRequest;
use App\Models\Job;
use App\Services\JobService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controller for employer job management
 */
class EmployerJobsController extends Controller
{
    /**
     * Job service instance
     *
     * @var JobService
     */
    protected $jobService;

    /**
     * Create a new controller instance.
     *
     * @param JobService $jobService
     * @return void
     */
    public function __construct(JobService $jobService)
    {
        $this->jobService = $jobService;
        $this->middleware('auth:api');
        $this->middleware('role:employer');
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
            if ($status === 'active') {
                $query->where('is_active', true);
            } elseif ($status === 'inactive') {
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
        
        return response()->json([
            'success' => true,
            'data' => $jobs,
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
            $job = $this->jobService->createJob($employer, $data);
            
            return response()->json([
                'success' => true,
                'message' => 'Job created successfully',
                'data' => $job,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get job details
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show($id): JsonResponse
    {
        $user = auth()->user();
        $employer = $user->employer;
        
        $job = $employer->jobs()->with(['category', 'applications.candidate.user'])->findOrFail($id);
        
        return response()->json([
            'success' => true,
            'data' => $job,
        ]);
    }

    /**
     * Update job
     *
     * @param JobRequest $request
     * @return JsonResponse
     */
    public function update(JobRequest $request): JsonResponse
    {
        $user = auth()->user();
        $employer = $user->employer;
        $data = $request->validated();
        
        $job = $employer->jobs()->findOrFail($data['id']);
        
        $job = $this->jobService->updateJob($job, $data);
        
        return response()->json([
            'success' => true,
            'message' => 'Job updated successfully',
            'data' => $job,
        ]);
    }

    /**
     * Delete job
     *
     * @param int $id
     * @return JsonResponse
     */
    public function delete($id): JsonResponse
    {
        $user = auth()->user();
        $employer = $user->employer;
        
        $job = $employer->jobs()->findOrFail($id);
        
        $this->jobService->deleteJob($job);
        
        return response()->json([
            'success' => true,
            'message' => 'Job deleted successfully',
        ]);
    }

    /**
     * Set job open/close status
     *
     * @param int $id
     * @param Request $request
     * @return JsonResponse
     */
    public function setOpenClose($id, Request $request): JsonResponse
    {
        $user = auth()->user();
        $employer = $user->employer;
        
        $job = $employer->jobs()->findOrFail($id);
        
        $isActive = $request->input('is_active', true);
        
        $job = $this->jobService->setJobStatus($job, $isActive);
        
        $status = $isActive ? 'opened' : 'closed';
        
        return response()->json([
            'success' => true,
            'message' => "Job {$status} successfully",
            'data' => $job,
        ]);
    }

    /**
     * Publish job as featured
     *
     * @param int $id
     * @return JsonResponse
     */
    public function publishJob($id): JsonResponse
    {
        $user = auth()->user();
        $employer = $user->employer;
        
        $job = $employer->jobs()->findOrFail($id);
        
        try {
            $job = $this->jobService->setJobAsFeatured($job);
            
            return response()->json([
                'success' => true,
                'message' => 'Job published as featured successfully',
                'data' => $job,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}