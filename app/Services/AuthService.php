<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

/**
 * Service class for authentication related operations
 */
class AuthService
{
    /**
     * Register a new user
     *
     * @param array $data
     * @param string $userType
     * @return array
     */
    public function register(array $data, string $userType = 'candidate'): array
    {
        // Create user
        $user = User::query()->create([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'user_type' => $userType,
        ]);

        // Assign role based on user type
        $user->assignRole($userType);

        // Create profile based on user type
        if ($userType === 'candidate') {
            $user->candidate()->create([
                'title' => $data['title'] ?? null,
            ]);
        } elseif ($userType === 'employer') {
            $user->employer()->create([
                'company_name' => $data['company_name'],
                'industry' => $data['industry'] ?? null,
            ]);
        }

        // Generate token
        $token = JWTAuth::fromUser($user);

        return [
            'user' => $user,
            'token' => $token,
        ];
    }

    /**
     * Authenticate a user
     *
     * @param string $email
     * @param string $password
     * @return array|null
     */
    public function login(string $email, string $password): ?array
    {
        $credentials = ['email' => $email, 'password' => $password];

        if (!$token = JWTAuth::attempt($credentials)) {
            return null;
        }

        $user = JWTAuth::user();

        // Check if user is active
        if (!$user->is_active) {
            return null;
        }

        return [
            'user' => $user,
            'token' => $token,
        ];
    }

    /**
     * Send password reset link
     *
     * @param string $email
     * @return bool
     */
    public function sendPasswordResetLink(string $email): bool
    {
        $user = User::query()->where('email', $email)->first();

        if (!$user) {
            return false;
        }

        $status = Password::sendResetLink(['email' => $email]);

        return $status === Password::RESET_LINK_SENT;
    }

    /**
     * Reset user password
     *
     * @param array $data
     * @return bool
     */
    public function resetPassword(array $data): bool
    {
        $status = Password::reset(
            $data,
            function ($user, $password) {
                $user->password = Hash::make($password);
                $user->save();
            }
        );

        return $status === Password::PASSWORD_RESET;
    }

    /**
     * Change user password
     *
     * @param User $user
     * @param string $currentPassword
     * @param string $newPassword
     * @return bool
     */
    public function changePassword(User $user, string $currentPassword, string $newPassword): bool
    {
        if (!Hash::check($currentPassword, $user->password)) {
            return false;
        }

        $user->password = Hash::make($newPassword);
        $user->save();

        return true;
    }

    /**
     * Logout a user
     *
     * @return bool
     */
    public function logout(): bool
    {
        try {
            JWTAuth::invalidate(JWTAuth::getToken());
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}
