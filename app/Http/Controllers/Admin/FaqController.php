<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\FaqCategoryRequest;
use App\Http\Requests\FaqRequest;
use App\Http\Resources\FaqCategoryResource;
use App\Http\Resources\FaqResource;
use App\Services\AdminAclService;
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
     * The Admin ACL service instance.
     *
     * @var AdminAclService
     */
    protected AdminAclService $adminAclService;

    /**
     * Create a new controller instance.
     *
     * @param FaqService $faqService
     * @param AdminAclService $adminAclService
     * @return void
     */
    public function __construct(FaqService $faqService, AdminAclService $adminAclService)
    {
        $this->faqService = $faqService;
        $this->adminAclService = $adminAclService;
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
        try {
            // Check permission using AdminAclService
            [$hasPermission, $errorMessage] = $this->adminAclService->hasPermission('view');
            if (!$hasPermission) {
                return response()->forbidden($errorMessage);
            }

            $categories = $this->faqService->getAllCategoriesWithFaqs(false);

            return response()->success(
                FaqCategoryResource::collection($categories),
                'FAQ categories retrieved successfully'
            );
        } catch (Exception $e) {
            return response()->serverError($e->getMessage());
        }
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
            // Check permission using AdminAclService
            [$hasPermission, $errorMessage] = $this->adminAclService->hasPermission('view');
            if (!$hasPermission) {
                return response()->forbidden($errorMessage);
            }

            $category = $this->faqService->getCategoryWithFaqs($id, false);

            return response()->success(
                new FaqCategoryResource($category),
                'FAQ category retrieved successfully'
            );
        } catch (Exception $e) {
            return response()->serverError($e->getMessage());
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
        try {
            // Check permission using AdminAclService
            [$hasPermission, $errorMessage] = $this->adminAclService->hasPermission('create');
            if (!$hasPermission) {
                return response()->forbidden($errorMessage);
            }

            $category = $this->faqService->createCategory($request->validated());

            return response()->created(
                new FaqCategoryResource($category),
                'FAQ category created successfully'
            );
        } catch (Exception $e) {
            return response()->serverError($e->getMessage());
        }
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
            // Check permission using AdminAclService
            [$hasPermission, $errorMessage] = $this->adminAclService->hasPermission('update');
            if (!$hasPermission) {
                return response()->forbidden($errorMessage);
            }

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
            // Check permission using AdminAclService
            [$hasPermission, $errorMessage] = $this->adminAclService->hasPermission('delete');
            if (!$hasPermission) {
                return response()->forbidden($errorMessage);
            }

            $this->faqService->deleteCategory($id);

            return response()->success(null, 'FAQ category deleted successfully');
        } catch (Exception $e) {
            return response()->serverError($e->getMessage());
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
        try {
            // Check permission using AdminAclService
            [$hasPermission, $errorMessage] = $this->adminAclService->hasPermission('view');
            if (!$hasPermission) {
                return response()->forbidden($errorMessage);
            }

            $filters = $request->only(['category_id', 'search', 'is_active']);
            $perPage = $request->input('per_page', 10);

            $faqs = $this->faqService->getAllFaqs($filters, $perPage);

            return response()->paginatedSuccess(
                FaqResource::collection($faqs),
                'FAQs retrieved successfully'
            );
        } catch (Exception $e) {
            return response()->serverError($e->getMessage());
        }
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
            // Check permission using AdminAclService
            [$hasPermission, $errorMessage] = $this->adminAclService->hasPermission('view');
            if (!$hasPermission) {
                return response()->forbidden($errorMessage);
            }

            $faq = $this->faqService->getFaq($id);

            return response()->success(
                new FaqResource($faq),
                'FAQ retrieved successfully'
            );
        } catch (Exception $e) {
            return response()->serverError($e->getMessage());
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
        try {
            // Check permission using AdminAclService
            [$hasPermission, $errorMessage] = $this->adminAclService->hasPermission('create');
            if (!$hasPermission) {
                return response()->forbidden($errorMessage);
            }

            $data = $request->validated();
            $faq = $this->faqService->createFaq($data);

            return response()->created(
                new FaqResource($faq),
                'FAQ created successfully'
            );
        } catch (Exception $e) {
            return response()->serverError($e->getMessage());
        }
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
            // Check permission using AdminAclService
            [$hasPermission, $errorMessage] = $this->adminAclService->hasPermission('update');
            if (!$hasPermission) {
                return response()->forbidden($errorMessage);
            }

            $data = $request->validated();
            $faq = $this->faqService->updateFaq($id, $data);

            return response()->success(
                new FaqResource($faq),
                'FAQ updated successfully'
            );
        } catch (Exception $e) {
            return response()->serverError($e->getMessage());
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
            // Check permission using AdminAclService
            [$hasPermission, $errorMessage] = $this->adminAclService->hasPermission('delete');
            if (!$hasPermission) {
                return response()->forbidden($errorMessage);
            }

            $this->faqService->deleteFaq($id);

            return response()->success(null, 'FAQ deleted successfully');
        } catch (Exception $e) {
            return response()->serverError($e->getMessage());
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
        try {
            // Check permission using AdminAclService
            [$hasPermission, $errorMessage] = $this->adminAclService->hasPermission('reorder');
            if (!$hasPermission) {
                return response()->forbidden($errorMessage);
            }

            $request->validate([
                'ordered_ids' => 'required|array',
                'ordered_ids.*' => 'integer|exists:faqs,id',
            ]);

            $orderedIds = $request->input('ordered_ids');
            $this->faqService->reorderFaqs($orderedIds);

            return response()->success(null, 'FAQs reordered successfully');
        } catch (Exception $e) {
            return response()->serverError($e->getMessage());
        }
    }

    /**
     * Reorder FAQ categories
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function reorderCategories(Request $request): JsonResponse
    {
        try {
            // Check permission using AdminAclService
            [$hasPermission, $errorMessage] = $this->adminAclService->hasPermission('reorder');
            if (!$hasPermission) {
                return response()->forbidden($errorMessage);
            }

            $request->validate([
                'ordered_ids' => 'required|array',
                'ordered_ids.*' => 'integer|exists:faq_categories,id',
            ]);

            $orderedIds = $request->input('ordered_ids');
            $this->faqService->reorderCategories($orderedIds);

            return response()->success(null, 'FAQ categories reordered successfully');
        } catch (Exception $e) {
            return response()->serverError($e->getMessage());
        }
    }
}
