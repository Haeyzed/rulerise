<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\FaqCategoryRequest;
use App\Http\Requests\FaqRequest;
use App\Http\Resources\FaqCategoryResource;
use App\Http\Resources\FaqResource;
use App\Services\FaqService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * Controller for admin FAQ operations
 */
class FaqController extends Controller implements HasMiddleware
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
     * Get the middleware that should be assigned to the controller.
     */
    public static function middleware(): array
    {
        return [
            new Middleware(['auth:api', 'role:admin']),
        ];
    }

    /**
     * Get all FAQ categories with their FAQs
     *
     * @return JsonResponse
     */
    public function getAllCategories(): JsonResponse
    {
        $categories = $this->faqService->getAllCategoriesWithFaqs(false);

        return response()->success(
            FaqCategoryResource::collection($categories),
            'FAQ categories retrieved successfully'
        );
    }

    /**
     * Get a specific FAQ category with its FAQs
     *
     * @param int $id Category ID
     * @return JsonResponse
     */
    public function getCategory(int $id): JsonResponse
    {
        try {
            $category = $this->faqService->getCategoryWithFaqs($id, false);

            return response()->success(
                new FaqCategoryResource($category),
                'FAQ category retrieved successfully'
            );
        } catch (Exception $e) {
            return response()->error($e->getMessage(), 404);
        }
    }

    /**
     * Create a new FAQ category
     *
     * @param FaqCategoryRequest $request
     * @return JsonResponse
     */
    public function createCategory(FaqCategoryRequest $request): JsonResponse
    {
        $data = $request->validated();
        $category = $this->faqService->createCategory($data);

        return response()->created(
            new FaqCategoryResource($category),
            'FAQ category created successfully'
        );
    }

    /**
     * Update an existing FAQ category
     *
     * @param int $id Category ID
     * @param FaqCategoryRequest $request
     * @return JsonResponse
     */
    public function updateCategory(int $id, FaqCategoryRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();
            $category = $this->faqService->updateCategory($id, $data);

            return response()->success(
                new FaqCategoryResource($category),
                'FAQ category updated successfully'
            );
        } catch (Exception $e) {
            return response()->serverError($e->getMessage());
        }
    }

    /**
     * Delete an FAQ category
     *
     * @param int $id Category ID
     * @return JsonResponse
     */
    public function deleteCategory(int $id): JsonResponse
    {
        try {
            $this->faqService->deleteCategory($id);

            return response()->success(null, 'FAQ category deleted successfully');
        } catch (Exception $e) {
            return response()->error($e->getMessage(), 404);
        }
    }

    /**
     * Get all FAQs
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getAllFaqs(Request $request): JsonResponse
    {
        $filters = $request->only(['category_id', 'search', 'is_active']);
        $perPage = $request->input('per_page', 10);

        $faqs = $this->faqService->getAllFaqs($filters, $perPage);

        return response()->paginatedSuccess(
            FaqResource::collection($faqs),
            'FAQs retrieved successfully'
        );
    }

    /**
     * Get a specific FAQ
     *
     * @param int $id FAQ ID
     * @return JsonResponse
     */
    public function getFaq(int $id): JsonResponse
    {
        try {
            $faq = $this->faqService->getFaq($id);

            return response()->success(
                new FaqResource($faq),
                'FAQ retrieved successfully'
            );
        } catch (Exception $e) {
            return response()->error($e->getMessage(), 404);
        }
    }

    /**
     * Create a new FAQ
     *
     * @param FaqRequest $request
     * @return JsonResponse
     */
    public function createFaq(FaqRequest $request): JsonResponse
    {
        $data = $request->validated();
        $faq = $this->faqService->createFaq($data);

        return response()->created(
            new FaqResource($faq),
            'FAQ created successfully'
        );
    }

    /**
     * Update an existing FAQ
     *
     * @param int $id FAQ ID
     * @param FaqRequest $request
     * @return JsonResponse
     */
    public function updateFaq(int $id, FaqRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();
            $faq = $this->faqService->updateFaq($id, $data);

            return response()->success(
                new FaqResource($faq),
                'FAQ updated successfully'
            );
        } catch (Exception $e) {
            return response()->error($e->getMessage(), 404);
        }
    }

    /**
     * Delete an FAQ
     *
     * @param int $id FAQ ID
     * @return JsonResponse
     */
    public function deleteFaq(int $id): JsonResponse
    {
        try {
            $this->faqService->deleteFaq($id);

            return response()->success(null, 'FAQ deleted successfully');
        } catch (Exception $e) {
            return response()->error($e->getMessage(), 404);
        }
    }

    /**
     * Reorder FAQs
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function reorderFaqs(Request $request): JsonResponse
    {
        $request->validate([
            'ordered_ids' => 'required|array',
            'ordered_ids.*' => 'integer|exists:faqs,id',
        ]);

        $orderedIds = $request->input('ordered_ids');
        $this->faqService->reorderFaqs($orderedIds);

        return response()->success(null, 'FAQs reordered successfully');
    }

    /**
     * Reorder FAQ categories
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function reorderCategories(Request $request): JsonResponse
    {
        $request->validate([
            'ordered_ids' => 'required|array',
            'ordered_ids.*' => 'integer|exists:faq_categories,id',
        ]);

        $orderedIds = $request->input('ordered_ids');
        $this->faqService->reorderCategories($orderedIds);

        return response()->success(null, 'FAQ categories reordered successfully');
    }
}
