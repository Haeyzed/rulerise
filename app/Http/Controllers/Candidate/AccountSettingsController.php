<?php

namespace App\Http\Controllers\Candidate;

use App\Http\Controllers\Controller;
use App\Http\Requests\Candidate\UpdateAccountSettingRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

/**
 * Controller for candidate account settings
 */
class AccountSettingsController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth:api');
        $this->middleware('role:candidate');
    }

    /**
     * Get account settings
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        $user = auth()->user();

        return response()->json([
            'success' => true,
            'data' => [
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'profile_picture' => $user->profile_picture,
                'is_active' => $user->is_active,
            ],
        ]);
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
        if (isset($data['name'])) {
            $user->name = $data['name'];
        }

        if (isset($data['phone'])) {
            $user->phone = $data['phone'];
        }

        if (isset($data['email']) && $data['email'] !== $user->email) {
            // Check if email is already taken
            $existingUser = User::where('email', $data['email'])->first();
            if ($existingUser && $existingUser->id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Email is already taken',
                ], 400);
            }

            $user->email = $data['email'];
            $user->email_verified_at = null; // Require re-verification
        }

        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Account settings updated successfully',
            'data' => $user,
        ]);
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

        return response()->json([
            'success' => true,
            'message' => 'Account deleted successfully',
        ]);
    }
}
