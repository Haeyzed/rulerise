<?php

namespace App\Http\Controllers\Candidate;

use App\Http\Controllers\Controller;
use App\Http\Requests\Candidate\UpdateAccountSettingRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Hash;

/**
 * Controller for candidate account settings
 */
class AccountSettingsController extends Controller implements HasMiddleware
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
    }

    /**
     * Get the middleware that should be assigned to the controller.
     */
    public static function middleware(): array
    {
        return [
            new Middleware(['auth:api','role:candidate']),
        ];
    }

    /**
     * Get account settings
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        $user = auth()->user();

        return response()->success($user, 'Account settings retrieved successfully');
    }

    /**
     * Update account settings
     *
     * @param UpdateAccountSettingRequest $request
     * @return JsonResponse
     */
    public function updateAccountSetting(UpdateAccountSettingRequest $request): JsonResponse
    {
        $user = auth()->user();
        $data = $request->validated();

        // Update user data
        if (isset($data['first_name'])) {
            $user->first_name = $data['first_name'];
        }

        if (isset($data['last_name'])) {
            $user->last_name = $data['last_name'];
        }

        if (isset($data['phone'])) {
            $user->phone = $data['phone'];
        }

        if (isset($data['email']) && $data['email'] !== $user->email) {
            // Check if email is already taken
            $existingUser = User::query()->where('email', $data['email'])->first();
            if ($existingUser && $existingUser->id !== $user->id) {
                return response()->badRequest('Email is already taken');
            }

            $user->email = $data['email'];
            $user->email_verified_at = null; // Require re-verification
        }

        $user->save();

        return response()->success($user, 'Account settings updated successfully');
    }

    /**
     * Delete account
     *
     * @return JsonResponse
     */
    public function deleteAccount(): JsonResponse
    {
        $user = auth()->user();

        // Soft delete the user
        $user->delete();

        return response()->success($user, 'Account deleted successfully');
    }
}
