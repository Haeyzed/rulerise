<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AccountSettingRequest;
use App\Models\GeneralSetting;
use App\Services\AdminService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * Controller for managing account settings.
 *
 * @package App\Http\Controllers\Admin
 */
class AccountSettingsController extends Controller implements HasMiddleware
{
    /**
     * The admin service instance.
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
            new Middleware(['auth:api', 'role:admin']),
        ];
    }

    /**
     * Get all account settings.
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        try {
            $settings = GeneralSetting::where('key', 'LIKE', 'account_%')->get();
            
            // Transform to key-value format
            $formattedSettings = $settings->mapWithKeys(function ($item) {
                $key = str_replace('account_', '', $item->key);
                return [$key => $item->value];
            });

            return response()->success($formattedSettings, 'Account settings retrieved successfully');
        } catch (Exception $e) {
            return response()->internalServerError($e->getMessage());
        }
    }

    /**
     * Update account settings.
     *
     * @param AccountSettingRequest $request
     * @return JsonResponse
     */
    public function update(AccountSettingRequest $request): JsonResponse
    {
        try {
            $settings = $request->validated();
            
            foreach ($settings as $key => $value) {
                $this->adminService->saveGeneralSetting('account_' . $key, $value);
            }

            return response()->success(null, 'Account settings updated successfully');
        } catch (Exception $e) {
            return response()->internalServerError($e->getMessage());
        }
    }

    /**
     * Get user configuration settings.
     *
     * @return JsonResponse
     */
    public function getUserConfiguration(): JsonResponse
    {
        try {
            $settings = [
                'delete_candidate_account' => (bool) GeneralSetting::where('key', 'account_delete_candidate_account')->value('value') ?? false,
                'delete_employer_account' => (bool) GeneralSetting::where('key', 'account_delete_employer_account')->value('value') ?? false,
                'email_notification' => (bool) GeneralSetting::where('key', 'account_email_notification')->value('value') ?? true,
                'email_verification' => (bool) GeneralSetting::where('key', 'account_email_verification')->value('value') ?? true,
            ];

            return response()->success($settings, 'User configuration settings retrieved successfully');
        } catch (Exception $e) {
            return response()->internalServerError($e->getMessage());
        }
    }

    /**
     * Update user configuration settings.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function updateUserConfiguration(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'delete_candidate_account' => 'boolean',
                'delete_employer_account' => 'boolean',
                'email_notification' => 'boolean',
                'email_verification' => 'boolean',
            ]);

            if ($request->has('delete_candidate_account')) {
                $this->adminService->saveGeneralSetting('account_delete_candidate_account', $request->delete_candidate_account);
            }

            if ($request->has('delete_employer_account')) {
                $this->adminService->saveGeneralSetting('account_delete_employer_account', $request->delete_employer_account);
            }

            if ($request->has('email_notification')) {
                $this->adminService->saveGeneralSetting('account_email_notification', $request->email_notification);
            }

            if ($request->has('email_verification')) {
                $this->adminService->saveGeneralSetting('account_email_verification', $request->email_verification);
            }

            return response()->success(null, 'User configuration settings updated successfully');
        } catch (Exception $e) {
            return response()->internalServerError($e->getMessage());
        }
    }

    /**
     * Get currency configuration.
     *
     * @return JsonResponse
     */
    public function getCurrencyConfiguration(): JsonResponse
    {
        try {
            $defaultCurrency = GeneralSetting::where('key', 'default_currency')->value('value') ?? 'USD';
            
            return response()->success(['default_currency' => $defaultCurrency], 'Currency configuration retrieved successfully');
        } catch (Exception $e) {
            return response()->internalServerError($e->getMessage());
        }
    }

    /**
     * Update currency configuration.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function updateCurrencyConfiguration(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'default_currency' => 'required|string|max:10',
            ]);

            $this->adminService->saveGeneralSetting('default_currency', $request->default_currency);

            return response()->success(null, 'Currency configuration updated successfully');
        } catch (Exception $e) {
            return response()->internalServerError($e->getMessage());
        }
    }
}
