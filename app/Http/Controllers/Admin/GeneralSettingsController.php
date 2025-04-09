<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\GeneralSettingRequest;
use App\Models\GeneralSetting;
use App\Services\AdminService;
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
     * Get general settings
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        $settings = GeneralSetting::all();

        return response()->json([
            'success' => true,
            'data' => $settings,
        ]);
    }

    /**
     * Save general setting
     *
     * @param GeneralSettingRequest $request
     * @return JsonResponse
     */
    public function store(GeneralSettingRequest $request): JsonResponse
    {
        $data = $request->validated();

        $setting = $this->adminService->saveGeneralSetting(
            $data['key'],
            $data['value']
        );

        return response()->json([
            'success' => true,
            'message' => 'General setting saved successfully',
            'data' => $setting,
        ]);
    }
}
