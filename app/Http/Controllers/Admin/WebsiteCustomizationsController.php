<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\WebsiteCustomizationRequest;
use App\Http\Requests\Admin\UploadImageRequest;
use App\Models\WebsiteCustomization;
use App\Services\AdminAclService;
use App\Services\AdminService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * Controller for admin website customization
 */
class WebsiteCustomizationsController extends Controller implements HasMiddleware
{
    /**
     * Admin service instance
     *
     * @var AdminService
     */
    protected AdminService $adminService;

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
     * @param AdminAclService $adminAclService
     * @return void
     */
    public function __construct(AdminService $adminService, AdminAclService $adminAclService)
    {
        $this->adminService = $adminService;
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
     * Get website customizations by type
     *
     * @param string $type
     * @return JsonResponse
     */
    public function index(string $type): JsonResponse
    {
        try {
            // Check permission using AdminAclService
            [$hasPermission, $errorMessage] = $this->adminAclService->hasPermission('view');
            if (!$hasPermission) {
                return response()->forbidden($errorMessage);
            }

            $customizations = WebsiteCustomization::query()->where('type', $type)->get();

            return response()->success($customizations, 'Website customization list retrieved successfully');
        } catch (Exception $e) {
            return response()->serverError($e->getMessage());
        }
    }

    /**
     * Save website customization
     *
     * @param WebsiteCustomizationRequest $request
     * @return JsonResponse
     */
    public function store(WebsiteCustomizationRequest $request): JsonResponse
    {
        try {
            // Check permission using AdminAclService
            [$hasPermission, $errorMessage] = $this->adminAclService->hasPermission('update');
            if (!$hasPermission) {
                return response()->forbidden($errorMessage);
            }

            $data = $request->validated();

            $customization = $this->adminService->saveWebsiteCustomization(
                $data['type'],
                $data['key'],
                $data['value'],
                $data['is_active'] ?? true
            );

            return response()->success($customization,'Website customization saved successfully');
        } catch (Exception $e) {
            return response()->serverError($e->getMessage());
        }
    }

    /**
     * Add new contact
     *
     * @param WebsiteCustomizationRequest $request
     * @return JsonResponse
     */
    public function addNewContact(WebsiteCustomizationRequest $request): JsonResponse
    {
        try {
            // Check permission using AdminAclService
            [$hasPermission, $errorMessage] = $this->adminAclService->hasPermission('create');
            if (!$hasPermission) {
                return response()->forbidden($errorMessage);
            }

            $data = $request->validated();

            $customization = $this->adminService->saveWebsiteCustomization(
                'contact',
                $data['key'],
                $data['value'],
                $data['is_active'] ?? true
            );

            return response()->success($customization,'Contact added successfully');
        } catch (Exception $e) {
            return response()->serverError($e->getMessage());
        }
    }

    /**
     * Upload image
     *
     * @param UploadImageRequest $request
     * @return JsonResponse
     */
    public function uploadImage(UploadImageRequest $request): JsonResponse
    {
        try {
            // Check permission using AdminAclService
            [$hasPermission, $errorMessage] = $this->adminAclService->hasPermission('update');
            if (!$hasPermission) {
                return response()->forbidden($errorMessage);
            }

            $data = $request->validated();

            $customization = $this->adminService->uploadWebsiteImage(
                $data['type'],
                $data['key'],
                $request->file('file')
            );

            return response()->success($customization,'Image uploaded successfully');
        } catch (Exception $e) {
            return response()->serverError($e->getMessage());
        }
    }
}
