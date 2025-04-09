<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\WebsiteCustomizationRequest;
use App\Http\Requests\Admin\UploadImageRequest;
use App\Models\WebsiteCustomization;
use App\Services\AdminService;
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
     * Create a new controller instance.
     *
     * @param AdminService $adminService
     * @return void
     */
    public function __construct(AdminService $adminService)
    {
        $this->adminService = $adminService;
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
        $customizations = WebsiteCustomization::query()->where('type', $type)->get();

        return response()->success($customizations, 'Website customization list retrieved successfully');
    }

    /**
     * Save website customization
     *
     * @param WebsiteCustomizationRequest $request
     * @return JsonResponse
     */
    public function store(WebsiteCustomizationRequest $request): JsonResponse
    {
        $data = $request->validated();

        $customization = $this->adminService->saveWebsiteCustomization(
            $data['type'],
            $data['key'],
            $data['value'],
            $data['is_active'] ?? true
        );

        return response()->success($customization,'Website customization saved successfully');
    }

    /**
     * Add new contact
     *
     * @param WebsiteCustomizationRequest $request
     * @return JsonResponse
     */
    public function addNewContact(WebsiteCustomizationRequest $request): JsonResponse
    {
        $data = $request->validated();

        $customization = $this->adminService->saveWebsiteCustomization(
            'contact',
            $data['key'],
            $data['value'],
            $data['is_active'] ?? true
        );

        return response()->success($customization,'Contact added successfully');
    }

    /**
     * Upload image
     *
     * @param UploadImageRequest $request
     * @return JsonResponse
     */
    public function uploadImage(UploadImageRequest $request): JsonResponse
    {
        $data = $request->validated();

        $customization = $this->adminService->uploadWebsiteImage(
            $data['type'],
            $data['key'],
            $request->file('file')
        );

        return response()->success($customization,'Image uploaded successfully');
    }
}
