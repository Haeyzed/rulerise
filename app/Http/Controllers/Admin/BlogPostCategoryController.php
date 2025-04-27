<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BlogPostCategoryRequest;
use App\Http\Requests\Admin\ListBlogPostCategoryRequest;
use App\Http\Resources\BlogPostCategoryResource;
use App\Models\BlogPostCategory;
use App\Services\BlogPostCategoryService;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * Blog Post Category Controller
 *
 * Handles CRUD operations for Blog Post Categories.
 * Includes methods to list, create, update, delete, restore for Blog Post Category records.
 *
 * @package App\Http\Controllers
 * @tags Blog Post Category
 */
class BlogPostCategoryController extends Controller implements HasMiddleware
{
    /**
     * @var BlogPostCategoryService
     */
    protected BlogPostCategoryService $categoryService;

    /**
     * BlogPostCategoryController constructor.
     *
     * @param BlogPostCategoryService $categoryService
     */
    public function __construct(BlogPostCategoryService $categoryService)
    {
        $this->categoryService = $categoryService;
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
     * Fetch a paginated list of blog post categories based on search and filter parameters.
     *
     * @param ListBlogPostCategoryRequest $request
     * @return JsonResponse
     * @response array{
     *      status: boolean,
     *      message: string,
     *      data: BlogPostCategoryResource[],
     *      meta: array{
     *          current_page: int,
     *          last_page: int,
     *          per_page: int,
     *          total: int
     *      }
     *  }
     */
    public function index(ListBlogPostCategoryRequest $request): JsonResponse
    {
        $categories = $this->categoryService->list($request);
        return response()->paginatedSuccess(BlogPostCategoryResource::collection($categories), 'Blog post categories retrieved successfully');
    }

    /**
     * Create and store a new blog post category.
     *
     * @param BlogPostCategoryRequest $request
     * @return JsonResponse
     * @response array{
     *       status: boolean,
     *       message: string,
     *       data: BlogPostCategoryResource
     *   }
     */
    public function store(BlogPostCategoryRequest $request): JsonResponse
    {
        try {
            $category = $this->categoryService->create($request->validated());
            return response()->success(new BlogPostCategoryResource($category), 'Blog post category created successfully');
        } catch (Exception $e) {
            return response()->serverError('Failed to create blog post category: ' . $e->getMessage());
        }
    }

    /**
     * Retrieve and display a specific blog post category.
     *
     * @param BlogPostCategory $blogPostCategory
     * @return JsonResponse
     * @response array{
     *       status: boolean,
     *       message: string,
     *       data: BlogPostCategoryResource
     *   }
     */
    public function show(BlogPostCategory $blogPostCategory): JsonResponse
    {
        return response()->success(new BlogPostCategoryResource($blogPostCategory->loadCount('blogPosts')), 'Blog post category retrieved successfully');
    }

    /**
     * Update an existing blog post category.
     *
     * @param BlogPostCategoryRequest $request
     * @param BlogPostCategory $blogPostCategory
     * @return JsonResponse
     * @response array{
     *       status: boolean,
     *       message: string,
     *       data: BlogPostCategoryResource
     *   }
     */
    public function update(BlogPostCategoryRequest $request, BlogPostCategory $blogPostCategory): JsonResponse
    {
        try {
            $updatedCategory = $this->categoryService->update($blogPostCategory, $request->validated());
            return response()->success(new BlogPostCategoryResource($updatedCategory), 'Blog post category updated successfully');
        } catch (Exception $e) {
            return response()->serverError('Failed to update blog post category: ' . $e->getMessage());
        }
    }

    /**
     * Delete a blog post category.
     *
     * @param BlogPostCategory $blogPostCategory
     * @return JsonResponse
     * @response array{
     *       status: boolean,
     *       message: string
     *   }
     */
    public function destroy(BlogPostCategory $blogPostCategory): JsonResponse
    {
        try {
            $this->categoryService->delete($blogPostCategory);
            return response()->success(null, 'Blog post category deleted successfully');
        } catch (Exception $e) {
            return response()->serverError('Failed to delete blog post category: ' . $e->getMessage());
        }
    }

    /**
     * Force delete a blog post category.
     *
     * @param BlogPostCategory $blogPostCategory
     * @return JsonResponse
     * @response array{
     *       status: boolean,
     *       message: string
     *   }
     */
    public function forceDestroy(BlogPostCategory $blogPostCategory): JsonResponse
    {
        try {
            $this->categoryService->forceDelete($blogPostCategory);
            return response()->success(null, 'Blog post category force deleted successfully');
        } catch (Exception $e) {
            return response()->serverError('Failed to force delete blog post category: ' . $e->getMessage());
        }
    }

    /**
     * Restore a soft-deleted blog post category.
     *
     * @param BlogPostCategory $blogPostCategory
     * @return JsonResponse
     * @response array{
     *       status: boolean,
     *       message: string,
     *       data: BlogPostCategoryResource
     *   }
     */
    public function restore(BlogPostCategory $blogPostCategory): JsonResponse
    {
        try {
            $restoredCategory = $this->categoryService->restore($blogPostCategory);
            return response()->success(new BlogPostCategoryResource($restoredCategory), 'Blog post category restored successfully');
        } catch (ModelNotFoundException $e) {
            return response()->notFound('Blog post category not found');
        } catch (Exception $e) {
            return response()->serverError('Failed to restore blog post category: ' . $e->getMessage());
        }
    }

    /**
     * Reorder blog post categories.
     *
     * @param Request $request
     * @return JsonResponse
     * @response array{
     *       status: boolean,
     *       message: string
     *   }
     */
    public function reorder(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'ordered_ids' => 'required|array',
                'ordered_ids.*' => 'required|integer|exists:blog_post_categories,id',
            ]);

            $this->categoryService->reorder($request->input('ordered_ids'));
            return response()->success(null, 'Blog post categories reordered successfully');
        } catch (Exception $e) {
            return response()->serverError('Failed to reorder blog post categories: ' . $e->getMessage());
        }
    }
}