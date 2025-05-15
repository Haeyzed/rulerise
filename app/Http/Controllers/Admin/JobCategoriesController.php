<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\JobCategoryRequest;
use App\Models\JobCategory;
use App\Services\AdminService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * Controller for admin job category management
 */
class JobCategoriesController extends Controller implements HasMiddleware
{
    /**
     * Admin service instance
     *
     * @var AdminService
     */
    protected AdminService $adminService;

    /**
     * Create a new controller instance.
     *
     * @param AdminService $adminService
     * @return void
     */
    public function __construct(AdminService $adminService)
    {
        $this->adminService = $adminService;
    }

    /**
     * Get the middleware that should be assigned to the controller.
     */
    public static function middleware(): array
    {
        return [
            new Middleware(['auth:api','role:admin']),
        ];
    }

    /**
     * Get job categories list
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        $categories = JobCategory::all();

        return response()->success($categories, 'Job Categories retrieved successfully.');
    }

    /**
     * Create a new job category
     *
     * @param JobCategoryRequest $request
     * @return JsonResponse
     */
    public function store(JobCategoryRequest $request): JsonResponse
    {
        $data = $request->validated();

        $category = $this->adminService->saveJobCategory($data);

        return response()->success($category, 'Job category created successfully');
    }

    /**
     * Get job category details
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        $category = JobCategory::query()->findOrFail($id);

        return response()->success($category, 'Job category retrieved successfully.');
    }

    /**
     * Update job category
     *
     * @param JobCategoryRequest $request
     * @return JsonResponse
     */
    public function update(JobCategoryRequest $request, JobCategory $jobCategory): JsonResponse
    {
        $data = $request->validated();

        $category = $this->adminService->saveJobCategory($data, $jobCategory);

        return response()->success($category, 'Job category updated successfully');
    }

    /**
     * Delete job category
     *
     * @param int $id
     * @return JsonResponse
     */
    public function delete(int $id): JsonResponse
    {
        $category = JobCategory::query()->findOrFail($id);
        $category->delete();

        return response()->success(null, 'Job category deleted successfully');
    }

    /**
     * Set job category active status
     *
     * @param int $id
     * @param Request $request
     * @return JsonResponse
     */
    public function setActive(int $id, Request $request): JsonResponse
    {
        $isActive = $request->input('is_active', true);

        $category = JobCategory::query()->findOrFail($id);

        $category = $this->adminService->setJobCategoryStatus($category, $isActive);

        $status = $isActive ? 'activated' : 'deactivated';

        return response()->success($category, "Job category {$status} successfully");
    }
}
