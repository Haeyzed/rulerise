<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\JobCategoryRequest;
use App\Models\JobCategory;
use App\Services\AdminAclService;
use App\Services\AdminService;
use App\Services\JobCategoryService;
use Exception;
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
     * Job category service instance
     *
     * @var JobCategoryService
     */
    protected JobCategoryService $jobCategoryService;

    /**
     * The Admin ACL service instance.
     *
     * @var AdminAclService
     */
    protected AdminAclService $adminAclService;

    /**
     * Create a new controller instance.
     *
     * @param AdminService $adminService
     * @param JobCategoryService $jobCategoryService
     * @param AdminAclService $adminAclService
     * @return void
     */
    public function __construct(
        AdminService $adminService,
        JobCategoryService $jobCategoryService,
        AdminAclService $adminAclService
    ) {
        $this->adminService = $adminService;
        $this->jobCategoryService = $jobCategoryService;
        $this->adminAclService = $adminAclService;
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
        try {
            // Check permission using AdminAclService
            [$hasPermission, $errorMessage] = $this->adminAclService->hasPermission('view');
            if (!$hasPermission) {
                return response()->forbidden($errorMessage);
            }

            $categories = JobCategory::all();

            return response()->success($categories, 'Job Categories retrieved successfully.');
        } catch (Exception $e) {
            return response()->serverError($e->getMessage());
        }
    }

    /**
     * Create a new job category
     *
     * @param JobCategoryRequest $request
     * @return JsonResponse
     */
    public function store(JobCategoryRequest $request): JsonResponse
    {
        try {
            // Check permission using AdminAclService
            [$hasPermission, $errorMessage] = $this->adminAclService->hasPermission('create');
            if (!$hasPermission) {
                return response()->forbidden($errorMessage);
            }

            $data = $request->validated();

            $category = $this->jobCategoryService->createCategory($data);

            return response()->success($category, 'Job category created successfully');
        } catch (Exception $e) {
            return response()->serverError($e->getMessage());
        }
    }

    /**
     * Get job category details
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        try {
            // Check permission using AdminAclService
            [$hasPermission, $errorMessage] = $this->adminAclService->hasPermission('view');
            if (!$hasPermission) {
                return response()->forbidden($errorMessage);
            }

            $category = JobCategory::query()->findOrFail($id);

            return response()->success($category, 'Job category retrieved successfully.');
        } catch (Exception $e) {
            return response()->serverError($e->getMessage());
        }
    }

    /**
     * Update job category
     *
     * @param JobCategoryRequest $request
     * @param JobCategory $jobCategory
     * @return JsonResponse
     */
    public function update(JobCategoryRequest $request, JobCategory $jobCategory): JsonResponse
    {
        try {
            // Check permission using AdminAclService
            [$hasPermission, $errorMessage] = $this->adminAclService->hasPermission('update');
            if (!$hasPermission) {
                return response()->forbidden($errorMessage);
            }

            $data = $request->validated();

            $category = $this->jobCategoryService->updateCategory($jobCategory, $data);

            return response()->success($category, 'Job category updated successfully');
        } catch (Exception $e) {
            return response()->serverError($e->getMessage());
        }
    }

    /**
     * Delete job category
     *
     * @param JobCategory $jobCategory
     * @return JsonResponse
     */
    public function destroy(JobCategory $jobCategory): JsonResponse
    {
        try {
            // Check permission using AdminAclService
            [$hasPermission, $errorMessage] = $this->adminAclService->hasPermission('delete');
            if (!$hasPermission) {
                return response()->forbidden($errorMessage);
            }

            $this->jobCategoryService->deleteCategory($jobCategory);

            return response()->success(null, 'Job category deleted successfully');
        } catch (Exception $e) {
            return response()->serverError($e->getMessage());
        }
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
        try {
            // Check permission using AdminAclService
            [$hasPermission, $errorMessage] = $this->adminAclService->hasPermission('update');
            if (!$hasPermission) {
                return response()->forbidden($errorMessage);
            }

            $isActive = $request->input('is_active', true);

            $category = JobCategory::query()->findOrFail($id);

            $category = $this->adminService->setJobCategoryStatus($category, $isActive);

            $status = $isActive ? 'activated' : 'deactivated';

            return response()->success($category, "Job category {$status} successfully");
        } catch (Exception $e) {
            return response()->serverError($e->getMessage());
        }
    }
}
