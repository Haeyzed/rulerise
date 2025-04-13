<?php

namespace App\Services;

use App\Models\JobCategory;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Service class for job category related operations
 */
class JobCategoryService
{
    /**
     * Get all active job categories with open job counts
     *
     * @param bool $withJobCount Include count of open jobs
     * @param int|null $perPage Number of items per page, null for all
     * @return Collection|LengthAwarePaginator
     */
    public function getAllCategories(bool $withJobCount = true, ?int $perPage = null): Collection|LengthAwarePaginator
    {
        $query = JobCategory::query()
            ->where('is_active', true)
            ->orderBy('name');

        if ($withJobCount) {
            $query->withCount([
                'jobs' => function ($query) {
                    $query->publiclyAvailable();
                }
            ]);
        }

        if ($perPage) {
            return $query->paginate($perPage);
        }

        return $query->get();
    }

    /**
     * Get a specific job category with its jobs
     *
     * @param int|string $idOrSlug ID or slug of the category
     * @param bool $withJobs Include jobs in the category
     * @param bool $withEmployers Include employers of the jobs
     * @param int|null $jobsPerPage Number of jobs per page, null for all
     * @return JobCategory
     */
    public function getCategory(int|string $idOrSlug, bool $withJobs = true, bool $withEmployers = true, ?int $jobsPerPage = null): JobCategory
    {
        $query = JobCategory::query();

        // Determine if the parameter is an ID or slug
        if (is_numeric($idOrSlug)) {
            $query->where('id', $idOrSlug);
        } else {
            $query->where('slug', $idOrSlug);
        }

        // Include jobs if requested
        if ($withJobs) {
            $query->with(['jobs' => function ($query) use ($withEmployers, $jobsPerPage) {
                $query->publiclyAvailable()
                    ->latest();

                if ($withEmployers) {
                    $query->with('employer');
                }

                if ($jobsPerPage) {
                    $query->limit($jobsPerPage);
                }
            }]);
        }

        // Get job count regardless of pagination
        $query->withCount([
            'jobs' => function ($query) {
                $query->publiclyAvailable();
            }
        ]);

        return $query->firstOrFail();
    }

    /**
     * Get featured job categories
     *
     * @param int $limit Maximum number of categories to return
     * @param bool $withJobCount Include count of open jobs
     * @return Collection
     */
    public function getFeaturedCategories(int $limit = 6, bool $withJobCount = true): Collection
    {
        $cacheKey = "featured_categories_{$limit}_{$withJobCount}";

        return Cache::remember($cacheKey, now()->addHours(6), function () use ($limit, $withJobCount) {
            $query = JobCategory::query()
                ->where('is_active', true)
                ->inRandomOrder()
                ->limit($limit);

            if ($withJobCount) {
                $query->withCount([
                    'jobs' => function ($query) {
                        $query->publiclyAvailable();
                    }
                ]);
            }

            return $query->get();
        });
    }

    /**
     * Get popular job categories based on job count
     *
     * @param int $limit Maximum number of categories to return
     * @return Collection
     */
    public function getPopularCategories(int $limit = 10): Collection
    {
        $cacheKey = "popular_categories_{$limit}";

        return Cache::remember($cacheKey, now()->addHours(12), function () use ($limit) {
            return JobCategory::query()
                ->where('is_active', true)
                ->withCount([
                    'jobs' => function ($query) {
                        $query->publiclyAvailable();
                    }
                ])
                ->orderByDesc('jobs_count')
                ->limit($limit)
                ->get();
        });
    }

    /**
     * Create a new job category
     *
     * @param array $data Category data
     * @return JobCategory
     */
    public function createCategory(array $data): JobCategory
    {
        return JobCategory::query()->create($data);
    }

    /**
     * Update a job category
     *
     * @param JobCategory $category
     * @param array $data
     * @return JobCategory
     */
    public function updateCategory(JobCategory $category, array $data): JobCategory
    {
        $category->update($data);
        return $category;
    }

    /**
     * Delete a job category
     *
     * @param JobCategory $category
     * @return bool
     */
    public function deleteCategory(JobCategory $category): bool
    {
        return $category->delete();
    }
}
