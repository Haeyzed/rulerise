<?php

namespace App\Http\Controllers\Public;

use App\Http\Requests\Admin\BlogPostRequest;
use App\Http\Requests\Admin\ListBlogPostRequest;
use App\Http\Controllers\Controller;
use App\Http\Resources\BlogPostResource;
use App\Models\BlogPost;
use App\Services\ACLService;
use App\Services\BlogPostService;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * Blog Post Controller
 *
 * Handles CRUD operations for Blog Posts.
 * Includes methods to list, create, update, delete, restore for Blog records.
 *
 * @package App\Http\Controllers
 * @tags Blog Post
 */
class BlogPostController extends Controller
{
    /**
     * @var BlogPostService
     */
    protected BlogPostService $blogPostService;

    /**
     * @var bool
     */
    protected bool $isPublicRoute = false;

    /**
     * BlogPostController constructor.
     *
     * @param BlogPostService $blogPostService
     * @param ACLService $ACLService
     * @param Request $request
     */
    public function __construct(BlogPostService $blogPostService)
    {
        $this->blogPostService = $blogPostService;
    }

    /**
     * Fetch a paginated list of blog posts based on search and filter parameters.
     *
     * @param ListBlogPostRequest $request
     * @return JsonResponse
     * @response array{
     *      status: boolean,
     *      message: string,
     *      data: BlogPostResource[],
     *      meta: array{
     *          current_page: int,
     *          last_page: int,
     *          per_page: int,
     *          total: int
     *      }
     *  }
     */
    public function index(ListBlogPostRequest $request): JsonResponse
    {
        $blogPosts = $this->blogPostService->list($request);
        return response()->paginatedSuccess(BlogPostResource::collection($blogPosts), 'Blog posts retrieved successfully');
    }

    /**
     * Retrieve and display a specific blog post.
     *
     * @param BlogPost $blogPost
     * @return JsonResponse
     * @response array{
     *       status: boolean,
     *       message: string,
     *       data: BlogPostResource
     *   }
     */
    public function show(BlogPost $blogPost): JsonResponse
    {
        return response()->success(new BlogPostResource($blogPost->load(['user', 'images'])), 'Blog post retrieved successfully');
    }
}
