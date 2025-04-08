<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\AuthService;
use Exception;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\URL;

/**
 * Controller for user registration
 */
class AuthController extends Controller
{
    /**
     * Auth service instance
     *
     * @var AuthService
     */
    protected AuthService $authService;

    /**
     * Create a new controller instance.
     *
     * @param AuthService $authService
     * @return void
     */
    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    /**
     * Register a new user
     *
     * @param RegisterRequest $request
     * @return JsonResponse
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();
            $userType = $data['user_type'];

            $result = $this->authService->register($data, $userType);
            $user = $result['user'];

            // Load relationships based on user type
            if ($user->isCandidate()) {
                $user->load(['candidate.skills']);
            } elseif ($user->isEmployer()) {
                $user->load(['employer.benefits']);
            }

            return response()->json([
                'success' => true,
                'message' => 'Registration successful',
                'data' => [
                    'user' => new UserResource($user),
                    'token' => $result['token'],
                ],
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Registration failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Login a user
     *
     * @param LoginRequest $request
     * @return JsonResponse
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->validated();

        $result = $this->authService->login($credentials['email'], $credentials['password']);

        if (!$result) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials or account is inactive',
            ], 401);
        }

        $user = $result['user'];

        // Load relationships based on user type
        if ($user->isCandidate()) {
            $user->load(['candidate.skills']);
        } elseif ($user->isEmployer()) {
            $user->load(['employer.benefits']);
        }

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'data' => [
                'user' => new UserResource($user),
                'token' => $result['token'],
            ],
        ]);
    }

    /**
     * Get the authenticated user
     *
     * @return JsonResponse
     */
    public function me(): JsonResponse
    {
        $user = auth()->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
            ], 401);
        }

        // Load relationships based on user type
        if ($user->isCandidate()) {
            $user->load(['candidate.skills']);
        } elseif ($user->isEmployer()) {
            $user->load(['employer.benefits']);
        }

        return response()->json([
            'success' => true,
            'data' => new UserResource($user),
        ]);
    }

    /**
     * Send password reset link
     *
     * @param string $email
     * @return JsonResponse
     */
    public function sendResetPasswordLink(string $email): JsonResponse
    {
        $result = $this->authService->sendPasswordResetLink($email);

        if (!$result) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send reset link',
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Password reset link sent successfully',
        ]);
    }

    /**
     * Verify reset password token
     *
     * @param VerifyResetTokenRequest $request
     * @return JsonResponse
     */
    public function verifyResetPasswordLink(VerifyResetTokenRequest $request): JsonResponse
    {
        $data = $request->validated();

        return response()->json([
            'success' => true,
            'message' => 'Token is valid',
            'data' => [
                'email' => $data['email'],
                'token' => $data['token'],
            ],
        ]);
    }

    /**
     * Reset password
     *
     * @param ResetPasswordRequest $request
     * @return JsonResponse
     */
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $data = $request->validated();

        $result = $this->authService->resetPassword($data);

        if (!$result) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to reset password',
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Password has been reset successfully',
        ]);
    }

    /**
     * Change password
     *
     * @param ChangePasswordRequest $request
     * @return JsonResponse
     */
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $data = $request->validated();
        $user = auth()->user();

        $result = $this->authService->changePassword(
            $user,
            $data['current_password'],
            $data['new_password']
        );

        if (!$result) {
            return response()->json([
                'success' => false,
                'message' => 'Current password is incorrect',
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Password changed successfully',
        ]);
    }

    /**
     * Resend email verification link
     *
     * @param string $email
     * @return JsonResponse
     */
    public function resendEmailVerification(string $email): JsonResponse
    {
        $user = User::query()->where('email', $email)->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found',
            ], 404);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'success' => false,
                'message' => 'Email already verified',
            ], 400);
        }

        $user->sendEmailVerificationNotification();

        return response()->json([
            'success' => true,
            'message' => 'Verification link sent successfully',
        ]);
    }

    /**
     * Verify email
     *
     * @param VerifyEmailRequest $request
     * @return JsonResponse
     */
    public function verifyEmail(VerifyEmailRequest $request): JsonResponse
    {
        $user = User::query()->find($request->id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found',
            ], 404);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'success' => true,
                'message' => 'Email already verified',
            ]);
        }

        if (!URL::hasValidSignature($request)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid verification link',
            ], 400);
        }

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        return response()->json([
            'success' => true,
            'message' => 'Email verified successfully',
        ]);
    }

    /**
     * Logout the user
     *
     * @return JsonResponse
     */
    public function logout(): JsonResponse
    {
        $result = $this->authService->logout();

        if (!$result) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to logout',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Successfully logged out',
        ]);
    }
}
