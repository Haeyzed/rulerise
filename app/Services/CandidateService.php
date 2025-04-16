<?php

namespace App\Services;

use App\Models\Candidate;
use App\Models\CandidateCredential;
use App\Models\EducationHistory;
use App\Models\Language;
use App\Models\ProfileViewCount;
use App\Models\Qualification;
use App\Models\Resume;
use App\Models\User;
use App\Models\WorkExperience;
use App\Services\Storage\StorageService;
use DateTime;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Service class for candidate related operations
 */
class CandidateService
{
    /**
     * @var StorageService
     */
    protected StorageService $storageService;

    /**
     * BlogPostService constructor.
     *
     * @param StorageService $storageService
     */
    public function __construct(StorageService $storageService)
    {
        $this->storageService = $storageService;
    }


    /**
     * Get candidate profile
     *
     * @param User $user
     * @return array
     */
    public function getProfile(User $user): array
    {
        $candidate = $user->candidate()->with([
            'qualification',
            'workExperiences',
            'educationHistories',
            'languages',
            'portfolio',
            'credentials',
            'resumes',
        ])->first();

        return [
            'user' => $user,
            'candidate' => $candidate,
        ];
    }

    /**
     * Update candidate profile
     *
     * @param User $user
     * @param array $data
     * @return Candidate
     */
    public function updateProfile(User $user, array $data): Candidate
    {
        return DB::transaction(function () use ($user, $data) {
            // Handle profile picture
            if (isset($data['profile_picture']) && $data['profile_picture'] instanceof UploadedFile) {
                $data['profile_picture_path'] = $this->uploadImage(
                    $data['profile_picture'],
                    config('filestorage.paths.profile_images')
                );
                unset($data['profile_picture']);
            }

            // Update user data - fields that belong to the User model
            $userFields = [
                'first_name', 'last_name', 'other_name', 'email',
                'phone', 'phone_country_code', 'country', 'state', 'city'
            ];

            $userData = array_intersect_key($data, array_flip($userFields));
            if (!empty($userData)) {
                $user->update($userData);
            }

            // Update candidate data - fields that belong to the Candidate model
            $candidate = $user->candidate;
            $candidateFields = [
                'bio', 'date_of_birth', 'gender', 'job_title',
                'year_of_experience', 'experience_level', 'highest_qualification',
                'prefer_job_industry', 'available_to_work', 'github',
                'linkedin', 'twitter', 'portfolio_url'
            ];

            $candidateData = array_intersect_key($data, array_flip($candidateFields));

            // Handle skills separately as it's a JSON field
            if (isset($data['skills']) && is_array($data['skills'])) {
                $candidateData['skills'] = $data['skills'];
            }

            if (!empty($candidateData)) {
                $candidate->update($candidateData);
            }

            return $user->candidate()->with([
                'qualification',
                'workExperiences',
                'educationHistories',
                'languages',
                'portfolio',
                'credentials',
                'resumes',
            ]);
        });
    }

    /**
     * Upload profile picture
     *
     * @param User $user
     * @param UploadedFile $file
     * @return User
     */
    public function uploadProfilePicture(User $user, UploadedFile $profile_picture): User
    {
        // Delete old profile picture if exists
        if ($user->profile_picture) {
            $this->storageService->delete($user->profile_picture);
        }

        // Store new profile picture
        $path = $this->uploadImage(
            $profile_picture,
            config('filestorage.paths.profile_images')
        );

        $user->profile_picture = $path;
        $user->save();

        return $user;
    }

    /**
     * Add work experience
     *
     * @param Candidate $candidate
     * @param array $data
     * @return WorkExperience
     * @throws \DateMalformedStringException
     */
    public function addWorkExperience(Candidate $candidate, array $data): WorkExperience
    {
        // If is_current is true, set end_date to null
        if (isset($data['is_current']) && $data['is_current']) {
            $data['end_date'] = null;
        }

        // Calculate experience level if not provided
        if (!isset($data['experience_level'])) {
            $startDate = new DateTime($data['start_date']);
            $endDate = isset($data['end_date']) && !empty($data['end_date'])
                ? new DateTime($data['end_date'])
                : new DateTime();

            $interval = $startDate->diff($endDate);
            $years = $interval->y;

            if ($years <= 1) {
                $data['experience_level'] = '0_1';
            } elseif ($years <= 3) {
                $data['experience_level'] = '1_3';
            } elseif ($years <= 5) {
                $data['experience_level'] = '3_5';
            } elseif ($years <= 10) {
                $data['experience_level'] = '5_10';
            } else {
                $data['experience_level'] = '10_plus';
            }
        }

        return $candidate->workExperiences()->create($data)->load('candidate', 'candidate.user');
    }

    /**
     * Update work experience
     *
     * @param WorkExperience $workExperience
     * @param array $data
     * @return WorkExperience
     * @throws \DateMalformedStringException
     */
    public function updateWorkExperience(WorkExperience $workExperience, array $data): WorkExperience
    {
        // If is_current is true, set end_date to null
        if (isset($data['is_current']) && $data['is_current']) {
            $data['end_date'] = null;
        }

        // Calculate experience level if not provided
        if (!isset($data['experience_level'])) {
            $startDate = new DateTime($data['start_date'] ?? $workExperience->start_date);
            $endDate = isset($data['end_date']) && !empty($data['end_date'])
                ? new DateTime($data['end_date'])
                : (isset($data['is_current']) && $data['is_current']
                    ? new DateTime()
                    : ($workExperience->end_date ? new DateTime($workExperience->end_date) : new DateTime()));

            $interval = $startDate->diff($endDate);
            $years = $interval->y;

            if ($years <= 1) {
                $data['experience_level'] = '0_1';
            } elseif ($years <= 3) {
                $data['experience_level'] = '1_3';
            } elseif ($years <= 5) {
                $data['experience_level'] = '3_5';
            } elseif ($years <= 10) {
                $data['experience_level'] = '5_10';
            } else {
                $data['experience_level'] = '10_plus';
            }
        }

        $workExperience->update($data);
        return $workExperience->load('candidate', 'candidate.user');
    }

    /**
     * Delete work experience
     *
     * @param WorkExperience $workExperience
     * @return bool
     */
    public function deleteWorkExperience(WorkExperience $workExperience): bool
    {
        return $workExperience->delete();
    }

    /**
     * Add education history
     *
     * @param Candidate $candidate
     * @param array $data
     * @return EducationHistory
     */
    public function addEducationHistory(Candidate $candidate, array $data): EducationHistory
    {
        return $candidate->educationHistories()->create($data)->load('candidate');
    }

    /**
     * Update education history
     *
     * @param EducationHistory $educationHistory
     * @param array $data
     * @return EducationHistory
     */
    public function updateEducationHistory(EducationHistory $educationHistory, array $data): EducationHistory
    {
        $educationHistory->update($data);
        return $educationHistory->load('candidate');
    }

    /**
     * Delete education history
     *
     * @param EducationHistory $educationHistory
     * @return bool
     */
    public function deleteEducationHistory(EducationHistory $educationHistory): bool
    {
        return $educationHistory->delete();
    }

    /**
     * Add language
     *
     * @param Candidate $candidate
     * @param array $data
     * @return Language
     */
    public function addLanguage(Candidate $candidate, array $data): Language
    {
        return $candidate->languages()->create($data)->load('candidate');
    }

    /**
     * Update language
     *
     * @param Language $language
     * @param array $data
     * @return Language
     */
    public function updateLanguage(Language $language, array $data): Language
    {
        $language->update($data);
        return $language->load('candidate');
    }

    /**
     * Delete language
     *
     * @param Language $language
     * @return bool
     */
    public function deleteLanguage(Language $language): bool
    {
        return $language->delete();
    }

    /**
     * Add credential
     *
     * @param Candidate $candidate
     * @param array $data
     * @return CandidateCredential
     */
    public function addCredential(Candidate $candidate, array $data): CandidateCredential
    {
        return $candidate->credentials()->create($data)->load('candidate');
    }

    /**
     * Update credential
     *
     * @param CandidateCredential $credential
     * @param array $data
     * @return CandidateCredential
     */
    public function updateCredential(CandidateCredential $credential, array $data): CandidateCredential
    {
        $credential->update($data);
        return $credential->load('candidate');
    }

    /**
     * Delete credential
     *
     * @param CandidateCredential $credential
     * @return bool
     */
    public function deleteCredential(CandidateCredential $credential): bool
    {
        return $credential->delete();
    }

    /**
     * Upload resume
     *
     * @param Candidate $candidate
     * @param UploadedFile $file
     * @param string $title
     * @param bool $isPrimary
     * @return Resume
     */
    public function uploadResume(Candidate $candidate, array $data): Resume
    {
        return DB::transaction(function () use ($candidate, $data) {
            // Handle banner cv
            if (isset($data['document']) && $data['document'] instanceof UploadedFile) {
                $data['document'] = $this->uploadCV(
                    $data['document'],
                    config('filestorage.paths.resumes')
                );
            }

            // If this is set as primary, unset other primary resumes
            if ($data['is_primary']) {
                $candidate->resumes()->update(['is_primary' => false]);
            }

            // Create resume record
            return $candidate->resumes()->create([
                'name' => $data['name'],
                'document' => $data['document'],
                'is_primary' => $data['is_primary'],
            ]);
        });
    }

    /**
     * Delete resume
     *
     * @param Resume $resume
     * @return bool|null
     */
    public function deleteResume(Resume $resume): ?bool
    {
        return DB::transaction(function () use ($resume) {
            // Delete resume document
            if ($resume->document) {
                $this->storageService->delete($resume->document);
            }

            return $resume->delete();
        });
    }

    /**
     * Record profile view
     *
     * @param Candidate $candidate
     * @param string|null $ipAddress
     * @param string|null $userAgent
     * @param int|null $employerId
     * @return ProfileViewCount
     */
    public function recordProfileView(Candidate $candidate, ?string $ipAddress = null, ?string $userAgent = null, ?int $employerId = null): ProfileViewCount
    {
        return $candidate->profileViewCounts()->create([
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'employer_id' => $employerId,
        ]);
    }

    /**
     * Upload an image to storage.
     *
     * @param UploadedFile $image The image file to upload.
     * @param string $path The storage path.
     * @param array $options Additional options for the upload.
     * @return string The path to the uploaded image.
     */
    private function uploadCv(UploadedFile $image, string $path, array $options = []): string
    {
        return $this->storageService->upload($image, $path, $options);
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
