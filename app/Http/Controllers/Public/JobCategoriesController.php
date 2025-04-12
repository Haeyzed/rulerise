<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\JobCategoryResource;
use App\Services\JobCategoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controller for job category operations
 */
class JobCategoriesController extends Controller
{
    /**
     * Job category service instance
     *
     * @var JobCategoryService
     */
    protected JobCategoryService $jobCategoryService;

    /**
     * Create a new controller instance.
     *
     * @param JobCategoryService $jobCategoryService
     * @return void
     */
    public function __construct(JobCategoryService $jobCategoryService)
    {
        $this->jobCategoryService = $jobCategoryService;
    }

    /**
     * Get all job categories
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $withJobCount = $request->boolean('with_job_count', true);
        $perPage = $request->input('per_page', config('app.pagination.per_page'));

        $categories = $this->jobCategoryService->getAllCategories($withJobCount, $perPage);

        return response()->paginatedSuccess(
            JobCategoryResource::collection($categories),
            'Job categories retrieved successfully'
        );
    }

    /**
     * Get a specific job category with its jobs
     *
     * @param int|string $idOrSlug
     * @param Request $request
     * @return JsonResponse
     */
    public function show($idOrSlug, Request $request): JsonResponse
    {
        $withJobs = $request->boolean('with_jobs', true);
        $withEmployers = $request->boolean('with_employers', true);
        $jobsPerPage = $request->input('per_page', config('app.pagination.per_page'));

        $category = $this->jobCategoryService->getCategory($idOrSlug, $withJobs, $withEmployers, $jobsPerPage);

        return response()->success(
            new JobCategoryResource($category),
            'Job category retrieved successfully'
        );
    }

    /**
     * Get featured job categories
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function featured(Request $request): JsonResponse
    {
        $limit = $request->input('per_page', config('app.pagination.per_page'));
        $withJobCount = $request->boolean('with_job_count', true);

        $categories = $this->jobCategoryService->getFeaturedCategories($limit, $withJobCount);

        return response()->success(
            JobCategoryResource::collection($categories),
            'Featured job categories retrieved successfully'
        );
    }

    /**
     * Get popular job categories
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function popular(Request $request): JsonResponse
    {
        $limit = $request->input('per_page', config('app.pagination.per_page'));
        $categories = $this->jobCategoryService->getPopularCategories($limit);

        return response()->success(
            JobCategoryResource::collection($categories),
            'Popular job categories retrieved successfully'
        );
    }
}
