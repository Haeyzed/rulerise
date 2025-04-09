<?php

namespace App\Services;

use App\Models\CompanyBenefit;
use App\Models\Employer;
use App\Models\Skill;
use App\Models\User;
use App\Services\Storage\StorageService;
use Exception;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

/**
 * Service class for authentication related operations
 */
class AuthService
{
    /**
     * @var StorageService
     */
    protected StorageService $storageService;

    /**
     * ClientSectionService constructor.
     *
     * @param StorageService $storageService
     */
    public function __construct(StorageService $storageService)
    {
        $this->storageService = $storageService;
    }

    /**
     * Register a new user
     *
     * @param array $data
     * @param string $userType
     * @return array
     * @throws Exception
     */
    public function register(array $data, string $userType = 'candidate'): array
    {
        DB::beginTransaction();

        try {
            // Handle profile picture (candidate)
            if (isset($data['profile_picture']) && $data['profile_picture'] instanceof UploadedFile) {
                $data['profile_picture_path'] = $this->uploadImage(
                    $data['profile_picture'],
                    config('filestorage.paths.profile_images')
                );
                unset($data['profile_picture']);
            }

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
                'user_type' => $userType,
            ];

            if (isset($data['profile_picture_path'])) {
                $userData['profile_picture'] = $data['profile_picture_path'];
            }

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
                        $skill = Skill::query()->firstOrCreate(['name' => $skillName]);
                        $candidate->skills()->attach($skill->id);
                    }
                }
            } elseif ($userType === 'employer') {
                // Handle company logo (employer)
                if (isset($data['company_logo']) && $data['company_logo'] instanceof UploadedFile) {
                    $data['company_logo_path'] = $this->uploadImage(
                        $data['company_logo'],
                        config('filestorage.paths.company_logos')
                    );
                    unset($data['company_logo']);
                }

                $employerData = [
                    'company_name' => $data['company_name'],
                    'company_email' => $data['company_email'] ?? null,
                    'company_description' => $data['company_description'] ?? null,
                    'company_industry' => $data['company_industry'] ?? null,
                    'company_size' => $data['company_size'] ?? null,
                    'company_founded' => $data['company_founded'] ?? null,
                    'company_country' => $data['company_country'] ?? null,
                    'company_state' => $data['company_state'] ?? null,
                    'company_address' => $data['company_address'] ?? null,
                    'company_phone_number' => $data['company_phone_number'] ?? null,
                    'company_website' => $data['company_website'] ?? null,
                ];

                if (isset($data['company_logo_path'])) {
                    $employerData['company_logo'] = $data['company_logo_path'];
                }

                $employer = $user->employer()->create($employerData);

                // Handle company benefits
                if (!empty($data['company_benefit_offered']) && is_array($data['company_benefit_offered'])) {
                    foreach ($data['company_benefit_offered'] as $benefit) {
                        CompanyBenefit::query()->create([
                            'employer_id' => $employer->id,
                            'benefit' => $benefit
                        ]);
                    }
                }
            }

            // Generate token
//            $token = Auth::login($user);

            DB::commit();

            return [
                'user' => $user,
//                'token' => $token,
            ];
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Authenticate a user
     *
     * @param string $email
     * @param string $password
     * @param string|bool $remember
     * @param string|null $userType
     * @return array|null
     */
    public function login(string $email, string $password, $remember = false, ?string $userType = null): ?array
    {
        $credentials = ['email' => $email, 'password' => $password];

        // Add user_type to credentials if provided
        if ($userType) {
            $credentials['user_type'] = $userType;
        }

        if (!$token = Auth::attempt($credentials, $remember)) {
            return null;
        }

        $user = Auth::user();

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
     * @param string|null $userType
     * @return bool
     */
    public function sendPasswordResetLink(string $email, ?string $userType = null): bool
    {
        $query = User::query()->where('email', $email);

        // Filter by user_type if provided
        if ($userType) {
            $query->where('user_type', $userType);
        }

        $user = $query->first();

        if (!$user) {
            return false;
        }

        $resetData = ['email' => $email];
        if ($userType) {
            $resetData['user_type'] = $userType;
        }

        $status = Password::sendResetLink($resetData);

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
                $user->setRememberToken(Str::random(60));
                $user->save();

                event(new PasswordReset($user));
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
        $user->setRememberToken(Str::random(60));
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
            Auth::logout();
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Upload an image to storage.
     *
     * @param UploadedFile $image The image file to upload.
     * @param string $path The storage path.
     * @param array $options Additional options for the upload.
     * @return string The path to the uploaded image.
     */
    private function uploadImage(UploadedFile $image, string $path, array $options = []): string
    {
        return $this->storageService->upload($image, $path, $options);
    }
}
