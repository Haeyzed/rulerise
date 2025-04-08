<?php

namespace App\Services;

use App\Models\CompanyBenefit;
use App\Models\Employer;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
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
     * @throws \Exception
     */
    public function register(array $data, string $userType = 'candidate'): array
    {
        DB::beginTransaction();

        try {
            // Create user
            $userData = [
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'other_name' => $data['other_name'] ?? null,
                'title' => $data['title'] ?? null,
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'phone' => $data['phone'] ?? null,
                'phone_country_code' => $data['phone_country_code'] ?? null,
                'country' => $data['country'] ?? null,
                'state' => $data['state'] ?? null,
                'city' => $data['city'] ?? null,
                'profile_picture' => $data['profile_picture'] ?? null,
                'user_type' => $userType,
            ];

            $user = User::query()->create($userData);

            // Assign role based on user type
            $user->assignRole($userType);

            // Create profile based on user type
            if ($userType === 'candidate') {
                $candidate = $user->candidate()->create([
                    'year_of_experience' => $data['year_of_experience'] ?? null,
                    'highest_qualification' => $data['highest_qualification'] ?? null,
                    'prefer_job_industry' => $data['prefer_job_industry'] ?? null,
                    'available_to_work' => $data['available_to_work'] ?? true,
                    'is_available' => $data['available_to_work'] ?? true,
                ]);

                // Handle skills
                if (!empty($data['skills']) && is_array($data['skills'])) {
                    foreach ($data['skills'] as $skillName) {
                        $skill = Skill::firstOrCreate(['name' => $skillName]);
                        $candidate->skills()->attach($skill->id);
                    }
                }
            } elseif ($userType === 'employer') {
                $employer = $user->employer()->create([
                    'company_name' => $data['company_name'],
                    'company_email' => $data['company_email'] ?? null,
                    'company_description' => $data['company_description'] ?? null,
                    'company_industry' => $data['company_industry'] ?? null,
                    'number_of_employees' => $data['number_of_employees'] ?? null,
                    'company_founded' => $data['company_founded'] ?? null,
                    'company_country' => $data['company_country'] ?? null,
                    'company_state' => $data['company_state'] ?? null,
                    'company_address' => $data['company_address'] ?? null,
                    'company_phone_number' => $data['company_phone_number'] ?? null,
                    'company_website' => $data['company_website'] ?? null,
                ]);

                // Handle company logo
                if (!empty($data['company_logo']) && !empty($data['company_logo'])) {
                    $imageData = base64_decode($data['company_logo']);
                    $extension = $data['company_logo']['image_extension'];
                    $filename = 'company_logos/' . $employer->id . '_' . time() . '.' . $extension;

                    Storage::disk('public')->put($filename, $imageData);

                    $employer->update([
                        'company_logo' => $filename
                    ]);
                }

                // Handle company benefits
                if (!empty($data['company_benefit_offered']) && is_array($data['company_benefit_offered'])) {
                    foreach ($data['company_benefit_offered'] as $benefit) {
                        CompanyBenefit::create([
                            'employer_id' => $employer->id,
                            'benefit' => $benefit
                        ]);
                    }
                }
            }

            // Generate token
            $token = JWTAuth::fromUser($user);

            DB::commit();

            return [
                'user' => $user,
                'token' => $token,
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
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
