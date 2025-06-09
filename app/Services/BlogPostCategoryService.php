<?php

namespace App\Services;

use App\Models\BlogPostCategory;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class BlogPostCategoryService
{
    /**
     * List blog post categories based on given criteria.
     *
     * @param object $request The request object containing filter and pagination parameters.
     * @return LengthAwarePaginator The paginated list of blog post categories.
     */
    public function list(object $request): LengthAwarePaginator
    {
        return BlogPostCategory::query()
            ->when($request->filled('search'), function ($query) use ($request) {
                $query->where(function ($q) use ($request) {
                    $q->where('name', 'like', "%{$request->search}%")
                        ->orWhere('description', 'like', "%{$request->search}%");
                });
            })
            ->when($request->boolean('active_only'), function ($query) {
                $query->where('is_active', true);
            })
            ->when($request->boolean('trashed_only'), function ($query) {
                $query->onlyTrashed();
            })
            ->when(
                $request->filled('order_by') && $request->filled('order_direction'),
                function ($query) use ($request) {
                    $query->orderBy($request->order_by, $request->order_direction);
                },
                function ($query) {
                    $query->orderBy('order', 'asc')->orderBy('name', 'asc');
                }
            )
            ->filterByDateRange(
                $request->input('start_date'),
                $request->input('end_date')
            )
            ->withCount('blogPosts')
            ->paginate($request->integer('per_page', config('app.pagination.per_page', 15)));
    }

    /**
     * Create a new blog post category.
     *
     * @param array $data The validated data for creating a new blog post category.
     * @return BlogPostCategory The newly created blog post category.
     */
    public function create(array $data): BlogPostCategory
    {
        return DB::transaction(function () use ($data) {
            // Set default order if not provided
            if (!isset($data['order'])) {
                $data['order'] = BlogPostCategory::max('order') + 1;
            }

            // Create blog post category
            return BlogPostCategory::create($data);
        });
    }

    /**
     * Update an existing blog post category.
     *
     * @param BlogPostCategory $category The blog post category to update.
     * @param array $data The validated data for updating the blog post category.
     * @return BlogPostCategory The updated blog post category.
     */
    public function update(BlogPostCategory $category, array $data): BlogPostCategory
    {
        return DB::transaction(function () use ($category, $data) {
            // Update blog post category
            $category->update($data);

            return $category->fresh();
        });
    }

    /**
     * Delete a blog post category.
     *
     * @param BlogPostCategory $category The blog post category to delete.
     * @return bool|null The result of the delete operation.
     */
    public function delete(BlogPostCategory $category): ?bool
    {
        return DB::transaction(function () use ($category) {
            return $category->delete();
        });
    }

    /**
     * Permanently delete a blog post category.
     *
     * @param BlogPostCategory $category The blog post category to force delete.
     * @return bool|null The result of the force delete operation.
     */
    public function forceDelete(BlogPostCategory $category): ?bool
    {
        return DB::transaction(function () use ($category) {
            return $category->forceDelete();
        });
    }

    /**
     * Restore a soft-deleted blog post category.
     *
     * @param BlogPostCategory $category The blog post category to restore.
     * @return BlogPostCategory The restored blog post category.
     */
    public function restore(BlogPostCategory $category): BlogPostCategory
    {
        return DB::transaction(function () use ($category) {
            $category->restore();
            return $category->fresh();
        });
    }

    /**
     * Reorder blog post categories.
     *
     * @param array $orderedIds The ordered array of category IDs.
     * @return bool Whether the reordering was successful.
     */
    public function reorder(array $orderedIds): bool
    {
        return DB::transaction(function () use ($orderedIds) {
            foreach ($orderedIds as $index => $id) {
                BlogPostCategory::where('id', $id)->update(['order' => $index + 1]);
            }
            return true;
        });
    }
}
