<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\GeneralSettingRequest;
use App\Models\GeneralSetting;
use App\Services\AdminAclService;
use App\Services\AdminService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * Controller for admin general settings
 */
class GeneralSettingsController extends Controller implements HasMiddleware
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
     * Get general settings
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        try {
            // Check permission using AdminAclService
            [$hasPermission, $errorMessage] = $this->adminAclService->hasPermission('view');
            if (!$hasPermission) {
                return response()->forbidden($errorMessage);
            }

            $settings = GeneralSetting::all();

            return response()->success($settings,'Settings retrieved successfully');
        } catch (Exception $e) {
            return response()->serverError($e->getMessage());
        }
    }

    /**
     * Save general setting
     *
     * @param GeneralSettingRequest $request
     * @return JsonResponse
     */
    public function store(GeneralSettingRequest $request): JsonResponse
    {
        try {
            // Check permission using AdminAclService
            [$hasPermission, $errorMessage] = $this->adminAclService->hasPermission('update');
            if (!$hasPermission) {
                return response()->forbidden($errorMessage);
            }

            $data = $request->validated();

            $setting = $this->adminService->saveGeneralSetting(
                $data['key'],
                $data['value']
            );

            return response()->success($setting,'Setting updated successfully');
        } catch (Exception $e) {
            return response()->serverError($e->getMessage());
        }
    }
}
