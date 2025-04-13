<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Requests\Auth\DeleteAccountRequest;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Requests\Auth\VerifyResetTokenRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\AuthService;
use Exception;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Hash;

/**
 * Controller for user authentication
 */
class AuthController extends Controller implements HasMiddleware
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
     * Get the middleware that should be assigned to the controller.
     */
    public static function middleware(): array
    {
        return [
            new Middleware('auth:api', only: ['me', 'changePassword', 'logout', 'deleteAccount']),
        ];
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

            // Check if user is trying to register as admin
            if ($userType === 'admin') {
                // Only existing admins can create new admins
                if (!auth()->check() || !auth()->user()->hasRole('admin')) {
                    return response()->forbidden('You do not have permission to create admin accounts');
                }
            }

            $result = $this->authService->register($data, $userType);
            $user = $result['user'];

            // Send verification email
            $user->sendEmailVerificationNotification();

            // Load relationships based on user type
            if ($user->isCandidate()) {
                $user->load(['candidate']);
            } elseif ($user->isEmployer()) {
                $user->load(['employer', 'employer.notificationTemplates']);
            } elseif ($user->isAdmin()) {
                // No specific relationships to load for admin
            }

            return response()->created(
                [
                    'user' => new UserResource($user),
                ],
                'Registration successful. Please check your email to verify your account.'
            );
        } catch (Exception $e) {
            return response()->error('Registration failed: ' . $e->getMessage(), 500);
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
        $userType = $credentials['user_type'] ?? null;

        $result = $this->authService->login(
            $credentials['email'],
            $credentials['password'],
            $credentials['remember_me'] ?? false,
            $userType
        );

        $user = $result['user'];

        // Check if email is verified
        if (!$user->hasVerifiedEmail()) {
            // Resend verification email
            $user->sendEmailVerificationNotification();

            return response()->error(
                'Email not verified. A new verification link has been sent to your email address.',
                403
            );
        }

        // Load relationships based on user type
        if ($user->isCandidate()) {
            $user->load(['candidate']);
        } elseif ($user->isEmployer()) {
            $user->load(['employer']);
        } elseif ($user->isAdmin()) {
            // No specific relationships to load for admin
        }

        return response()->success(
            [
                'token' => $result['token'],
                'user' => new UserResource($user),
            ],
            'Login successful'
        );
    }

    /**
     * Get the authenticated user
     *
     * @return JsonResponse
     */
    public function me(): JsonResponse
    {
        $user = auth()->user();

        // Load relationships based on user type
        if ($user->isCandidate()) {
            $user->load(['candidate']);
        } elseif ($user->isEmployer()) {
            $user->load(['employer']);
        }

        return response()->success(new UserResource($user), 'User profile retrieved successfully');
    }


    /**
     * Send password reset link
     *
     * @param ForgotPasswordRequest $request
     * @return JsonResponse
     */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $data = $request->validated();
        $userType = $data['user_type'] ?? null;

        $userQuery = User::query()->where('email', $data['email']);

        if ($userType) {
            $userQuery->where('user_type', $userType);
        }

        $user = $userQuery->first();

        if (!$user) {
            return response()->badRequest('No user found with this email and user type.');
        }

        $result = $this->authService->sendPasswordResetLink($data['email'], $userType);

        if (!$result) {
            return response()->badRequest('Failed to send reset link');
        }

        return response()->success(null, 'Password reset link sent successfully');
    }


    /**
     * Verify reset password token
     *
     * @param VerifyResetTokenRequest $request
     * @return JsonResponse
     */
    public function verifyResetToken(VerifyResetTokenRequest $request): JsonResponse
    {
        $data = $request->validated();

        return response()->success(
            [
                'email' => $data['email'],
                'token' => $data['token'],
                'user_type' => $data['user_type'] ?? null,
            ],
            'Token is valid'
        );
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
            return response()->error('Failed to reset password', 400);
        }

        return response()->success(null, 'Password has been reset successfully');
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
            return response()->error('Current password is incorrect', 400);
        }

        return response()->success(null, 'Password changed successfully');
    }

    /**
     * Delete user account
     *
     * @param DeleteAccountRequest $request
     * @return JsonResponse
     */
    public function deleteAccount(DeleteAccountRequest $request): JsonResponse
    {
        $user = auth()->user();
        $password = $request->input('password');
        $permanent = $request->input('permanent', false);

        // Verify password
        if (!Hash::check($password, $user->password)) {
            return response()->badRequest('Password is incorrect');
        }

        // Check if user is an admin trying to permanently delete
        if ($permanent && !$user->isAdmin()) {
            return response()->forbidden('You do not have permission to permanently delete accounts');
        }

        // Delete the user
        $result = $this->authService->deleteUser($user, $permanent);

        if (!$result) {
            return response()->serverError('Failed to delete account');
        }

        return response()->success(null, 'Account deleted successfully');
    }

    /**
     * Resend email verification link
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function resendVerificationEmail(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
            'user_type' => 'nullable|string|in:candidate,employer,admin',
        ]);

        $email = $request->input('email');
        $userType = $request->input('user_type');

        $query = User::query()->where('email', $email);

        if ($userType) {
            $query->where('user_type', $userType);
        }

        $user = $query->first();

        if (!$user) {
            return response()->notFound('User not found');
        }

        if ($user->hasVerifiedEmail()) {
            return response()->error('Email already verified', 400);
        }

        $user->sendEmailVerificationNotification();

        return response()->success(null, 'Verification link sent successfully');
    }

    /**
     * Verify email
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function verifyEmail(Request $request): JsonResponse
    {
        $userEmail = $request->input('user');
        $hash = $request->input('hash');
        $userType = $request->input('user_type');

        if (!$userEmail || !$hash) {
            return response()->error('Invalid verification link', 400);
        }

        $query = User::query()->where('email', $userEmail);

        if ($userType) {
            $query->where('user_type', $userType);
        }

        $user = $query->firstOrFail();

        if (!hash_equals((string)$hash, sha1($user->getEmailForVerification()))) {
            return response()->error('Invalid verification link', 400);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->success(null, 'Email already verified');
        }

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        return response()->success(null, 'Email verified successfully');
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
            return response()->error('Failed to logout', 500);
        }

        return response()->success(null, 'Successfully logged out');
    }
}
