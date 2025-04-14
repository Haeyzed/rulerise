<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\FaqCategoryResource;
use App\Http\Resources\FaqResource;
use App\Services\FaqService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controller for public FAQ operations
 */
class FaqController extends Controller
{
    /**
     * FAQ service instance
     *
     * @var FaqService
     */
    protected FaqService $faqService;

    /**
     * Create a new controller instance.
     *
     * @param FaqService $faqService
     * @return void
     */
    public function __construct(FaqService $faqService)
    {
        $this->faqService = $faqService;
    }

    /**
     * Get all FAQ categories with their FAQs
     *
     * @return JsonResponse
     */
    public function getAllCategories(): JsonResponse
    {
        $categories = $this->faqService->getAllCategoriesWithFaqs(true);

        return response()->success(
            FaqCategoryResource::collection($categories),
            'FAQ categories retrieved successfully'
        );
    }

    /**
     * Get a specific FAQ category with its FAQs
     *
     * @param string $slugOrId Category slug or ID
     * @return JsonResponse
     */
    public function getCategory(string $slugOrId): JsonResponse
    {
        try {
            $category = $this->faqService->getCategoryWithFaqs($slugOrId, true);

            return response()->success(
                new FaqCategoryResource($category),
                'FAQ category retrieved successfully'
            );
        } catch (Exception $e) {
            return response()->error($e->getMessage(), 404);
        }
    }

    /**
     * Search FAQs
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'query' => 'required|string|min:2',
            'per_page' => 'nullable|integer|min:1|max:50',
        ]);

        $perPage = $request->input('per_page', 10);
        $query = $request->input('query');

        $faqs = $this->faqService->searchFaqs($query, $perPage);

        return response()->paginatedSuccess(
            FaqResource::collection($faqs),
            'FAQs retrieved successfully'
        );
    }

    /**
     * Get all FAQs
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getAllFaqs(Request $request): JsonResponse
    {
        $filters = $request->only(['category_id', 'search']);
        $perPage = $request->input('per_page', 10);

        $faqs = $this->faqService->getAllFaqs($filters, $perPage);

        return response()->paginatedSuccess(
            FaqResource::collection($faqs),
            'FAQs retrieved successfully'
        );
    }
}
