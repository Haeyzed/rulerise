<?php

namespace App\Services;

use App\Models\Faq;
use App\Models\FaqCategory;
use Exception;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

/**
 * Service class for FAQ related operations
 */
class FaqService
{
    /**
     * Get all FAQ categories with their FAQs
     *
     * @param bool $activeOnly Only include active categories and FAQs
     * @return Collection
     */
    public function getAllCategoriesWithFaqs(bool $activeOnly = true): Collection
    {
        $query = FaqCategory::query()->ordered();

        if ($activeOnly) {
            $query->active();
        }

        return $query->with(['faqs' => function ($query) use ($activeOnly) {
            $query->ordered();
            if ($activeOnly) {
                $query->active();
            }
        }])->get();
    }

    /**
     * Get a specific FAQ category with its FAQs
     *
     * @param int|string $categoryIdOrSlug Category ID or slug
     * @param bool $activeOnly Only include active FAQs
     * @return FaqCategory
     * @throws Exception
     */
    public function getCategoryWithFaqs($categoryIdOrSlug, bool $activeOnly = true): FaqCategory
    {
        $query = FaqCategory::query();

        if (is_numeric($categoryIdOrSlug)) {
            $query->where('id', $categoryIdOrSlug);
        } else {
            $query->where('slug', $categoryIdOrSlug);
        }

        if ($activeOnly) {
            $query->active();
        }

        $category = $query->with(['faqs' => function ($query) use ($activeOnly) {
            $query->ordered();
            if ($activeOnly) {
                $query->active();
            }
        }])->first();

        if (!$category) {
            throw new Exception('FAQ category not found');
        }

        return $category;
    }

    /**
     * Get all FAQs
     *
     * @param array $filters Filters to apply
     * @param int $perPage Number of items per page
     * @return LengthAwarePaginator
     */
    public function getAllFaqs(array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        $query = Faq::query()->with('category')->ordered();

        // Apply filters
        if (!empty($filters['category_id'])) {
            $query->where('faq_category_id', $filters['category_id']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('question', 'like', "%{$search}%")
                    ->orWhere('answer', 'like', "%{$search}%");
            });
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        } else {
            $query->active();
        }

        return $query->paginate($perPage);
    }

    /**
     * Get a specific FAQ
     *
     * @param int $id FAQ ID
     * @return Faq
     * @throws Exception
     */
    public function getFaq(int $id): Faq
    {
        $faq = Faq::with('category')->find($id);

        if (!$faq) {
            throw new Exception('FAQ not found');
        }

        return $faq;
    }

    /**
     * Create a new FAQ
     *
     * @param array $data FAQ data
     * @return Faq
     */
    public function createFaq(array $data): Faq
    {
        return Faq::query()->create($data);
    }

    /**
     * Update an existing FAQ
     *
     * @param int $id FAQ ID
     * @param array $data FAQ data
     * @return Faq
     * @throws Exception
     */
    public function updateFaq(int $id, array $data): Faq
    {
        $faq = Faq::query()->find($id);

        if (!$faq) {
            throw new Exception('FAQ not found');
        }

        $faq->update($data);

        return $faq->fresh();
    }

    /**
     * Delete an FAQ
     *
     * @param int $id FAQ ID
     * @return bool
     * @throws Exception
     */
    public function deleteFaq(int $id): bool
    {
        $faq = Faq::query()->find($id);

        if (!$faq) {
            throw new Exception('FAQ not found');
        }

        return $faq->delete();
    }

    /**
     * Create a new FAQ category
     *
     * @param array $data Category data
     * @return FaqCategory
     */
    public function createCategory(array $data): FaqCategory
    {
        if (empty($data['slug']) && !empty($data['name'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        return FaqCategory::query()->create($data);
    }

    /**
     * Update an existing FAQ category
     *
     * @param int $id Category ID
     * @param array $data Category data
     * @return FaqCategory
     * @throws Exception
     */
    public function updateCategory(int $id, array $data): FaqCategory
    {
        $category = FaqCategory::query()->find($id);

        if (!$category) {
            throw new Exception('FAQ category not found');
        }

        if (empty($data['slug']) && !empty($data['name']) && $data['name'] !== $category->name) {
            $data['slug'] = Str::slug($data['name']);
        }

        $category->update($data);

        return $category->fresh();
    }

    /**
     * Delete an FAQ category
     *
     * @param int $id Category ID
     * @return bool
     * @throws Exception
     */
    public function deleteCategory(int $id): bool
    {
        $category = FaqCategory::query()->find($id);

        if (!$category) {
            throw new Exception('FAQ category not found');
        }

        return $category->delete();
    }

    /**
     * Search FAQs
     *
     * @param string $query Search query
     * @param int $perPage Number of items per page
     * @return LengthAwarePaginator
     */
    public function searchFaqs(string $query, int $perPage = 10): LengthAwarePaginator
    {
        return Faq::query()
            ->with('category')
            ->active()
            ->where(function ($q) use ($query) {
                $q->where('question', 'like', "%{$query}%")
                    ->orWhere('answer', 'like', "%{$query}%");
            })
            ->ordered()
            ->paginate($perPage);
    }

    /**
     * Reorder FAQs
     *
     * @param array $orderedIds Array of FAQ IDs in the desired order
     * @return bool
     */
    public function reorderFaqs(array $orderedIds): bool
    {
        foreach ($orderedIds as $order => $id) {
            Faq::query()->where('id', $id)->update(['order' => $order]);
        }

        return true;
    }

    /**
     * Reorder FAQ categories
     *
     * @param array $orderedIds Array of category IDs in the desired order
     * @return bool
     */
    public function reorderCategories(array $orderedIds): bool
    {
        foreach ($orderedIds as $order => $id) {
            FaqCategory::query()->where('id', $id)->update(['order' => $order]);
        }

        return true;
    }
}
