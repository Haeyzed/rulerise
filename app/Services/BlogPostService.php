<?php

namespace App\Services;

use App\Models\BlogPost;
use App\Services\Storage\StorageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class BlogPostService
{
    /**
     * @var StorageService
     */
    protected StorageService $storageService;

    /**
     * BlogPostService constructor.
     *
     * @param StorageService $storageService
     */
    public function __construct(StorageService $storageService)
    {
        $this->storageService = $storageService;
    }

    /**
     * List blog posts based on given criteria.
     *
     * @param object $request The request object containing filter and pagination parameters.
     * @return LengthAwarePaginator The paginated list of blog posts.
     */
    public function list(object $request): LengthAwarePaginator
    {
        return BlogPost::query()
            ->when($request->filled('search'), function ($query) use ($request) {
                $query->search(['title', 'subtitle', 'body'], $request->search);
            })
            ->when($request->filled('status'), function ($query) use ($request) {
                $query->where('status', $request->status);
            })
            ->when(
                $request->filled('order_by') && $request->filled('order_direction'),
                function ($query) use ($request) {
                    $query->orderBy($request->order_by, $request->order_direction);
                },
                function ($query) {
                    $query->latest();
                }
            )
            ->when($request->boolean('trashed_only'), function ($query) {
                $query->onlyTrashed();
            })
            ->filterByDateRange(
                $request->input('start_date'),
                $request->input('end_date')
            )
            ->with(['user', 'images'])
            ->paginate($request->integer('per_page', config('app.pagination.per_page', 15)));
    }

    /**
     * Create a new blog post.
     *
     * @param array $data The validated data for creating a new blog post.
     * @return BlogPost The newly created blog post.
     */
    public function create(array $data): BlogPost
    {
        return DB::transaction(function () use ($data) {
            // Set user_id if not provided
            if (!isset($data['user_id'])) {
                $data['user_id'] = auth()->id();
            }

            // Handle banner image
            if (isset($data['banner_image']) && $data['banner_image'] instanceof UploadedFile) {
                $data['banner_image'] = $this->uploadImage(
                    $data['banner_image'],
                    config('filestorage.paths.blog_banners')
                );
            }

            // Create blog post
            $blogPost = BlogPost::query()->create($data);

            // Handle related images
            if (isset($data['related_images']) && is_array($data['related_images'])) {
                $this->handleRelatedImages($blogPost, $data['related_images']);
            }

            return $blogPost->load(['user', 'images']);
        });
    }

    /**
     * Upload an image to storage.
     *
     * @param UploadedFile $image The image file to upload.
     * @param string $path The storage path.
     * @param array $options Additional options for the upload.
     * @return string The path to the uploaded image.
     */
    private function uploadImage(UploadedFile $image, string $path, array $options = []): string
    {
        return $this->storageService->upload($image, $path, $options);
    }

    /**
     * Handle related images for a blog post.
     *
     * @param BlogPost $blogPost The blog post.
     * @param array $images The array of image files.
     * @return void
     */
    private function handleRelatedImages(BlogPost $blogPost, array $images): void
    {
        $order = $blogPost->images()->count() + 1;

        foreach ($images as $image) {
            if ($image instanceof UploadedFile) {
                $blogPost->images()->create([
                    'image_path' => $this->uploadImage(
                        $image,
                        config('filestorage.paths.blog_images')
                    ),
                    'order' => $order++,
                ]);
            }
        }
    }

    /**
     * Update an existing blog post.
     *
     * @param BlogPost $blogPost The blog post to update.
     * @param array $data The validated data for updating the blog post.
     * @return BlogPost The updated blog post.
     */
    public function update(BlogPost $blogPost, array $data): BlogPost
    {
        return DB::transaction(function () use ($blogPost, $data) {
            // Handle banner image
            if (isset($data['banner_image']) && $data['banner_image'] instanceof UploadedFile) {
                // Delete old image if exists
                if ($blogPost->banner_image) {
                    $this->storageService->delete($blogPost->banner_image);
                }

                $data['banner_image'] = $this->uploadImage(
                    $data['banner_image'],
                    config('filestorage.paths.blog_banners')
                );
            }

            // Update blog post
            $blogPost->update($data);

            // Handle related images
            if (isset($data['related_images']) && is_array($data['related_images'])) {
                $this->handleRelatedImages($blogPost, $data['related_images']);
            }

            return $blogPost->load(['user', 'images']);
        });
    }

    /**
     * Delete a blog post.
     *
     * @param BlogPost $blogPost The blog post to delete.
     * @return bool|null The result of the delete operation.
     */
    public function delete(BlogPost $blogPost): ?bool
    {
        return DB::transaction(function () use ($blogPost) {
            return $blogPost->delete();
        });
    }

    /**
     * Permanently delete a blog post and its related files.
     *
     * @param BlogPost $blogPost The blog post to force delete.
     * @return bool|null The result of the force delete operation.
     */
    public function forceDelete(BlogPost $blogPost): ?bool
    {
        return DB::transaction(function () use ($blogPost) {
            // Delete banner image
            if ($blogPost->banner_image) {
                $this->storageService->delete($blogPost->banner_image);
            }

            // Delete related images
            foreach ($blogPost->images as $image) {
                $this->storageService->delete($image->image_path);
                $image->delete();
            }

            return $blogPost->forceDelete();
        });
    }

    /**
     * Restore a soft-deleted blog post.
     *
     * @param BlogPost $blogPost The blog post to restore.
     * @return BlogPost The restored blog post.
     */
    public function restore(BlogPost $blogPost): BlogPost
    {
        return DB::transaction(function () use ($blogPost) {
            $blogPost->restore();
            return $blogPost->load(['user', 'images']);
        });
    }
}
