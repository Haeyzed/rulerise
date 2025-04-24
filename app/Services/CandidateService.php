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
     * Get candidate profile with all relationships
     *
     * @param User $user
     * @return User
     */
    public function getProfile(User $user): User
    {
        // First load the user with roles and permissions
        $user->load(['roles', 'permissions']);

        // Then load the candidate with all its relationships
        $candidate = $user->candidate;

        $candidate?->load([
            'qualification',
            'workExperiences',
            'educationHistories',
            'languages',
            'portfolio',
            'credentials',
            'resumes',
            'jobApplications',
            'savedJobs',
            'reportedJobs',
            'candidatePools',
            'primaryResume',
        ]);

        return $user;
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
                $profilePicturePath = $this->uploadImage(
                    $data['profile_picture'],
                    config('filestorage.paths.profile_images')
                );

                // Update user's profile picture
                $user->profile_picture = $profilePicturePath;
            }

            // Update user data - fields that belong to the User model
            if (isset($data['first_name'])) {
                $user->first_name = $data['first_name'];
            }

            if (isset($data['last_name'])) {
                $user->last_name = $data['last_name'];
            }

            if (isset($data['other_name'])) {
                $user->other_name = $data['other_name'];
            }

            if (isset($data['email'])) {
                $user->email = $data['email'];
            }

            if (isset($data['phone'])) {
                $user->phone = $data['phone'];
            }

            if (isset($data['phone_country_code'])) {
                $user->phone_country_code = $data['phone_country_code'];
            }

            if (isset($data['country'])) {
                $user->country = $data['country'];
            }

            if (isset($data['state'])) {
                $user->state = $data['state'];
            }

            if (isset($data['city'])) {
                $user->city = $data['city'];
            }

            // Save user changes
            $user->save();

            // Update candidate data - fields that belong to the Candidate model
            $candidate = $user->candidate;

            if (isset($data['bio'])) {
                $candidate->bio = $data['bio'];
            }

            if (isset($data['date_of_birth'])) {
                $candidate->date_of_birth = $data['date_of_birth'];
            }

            if (isset($data['gender'])) {
                $candidate->gender = $data['gender'];
            }

            if (isset($data['job_title'])) {
                $candidate->job_title = $data['job_title'];
            }

            if (isset($data['year_of_experience'])) {
                $candidate->year_of_experience = $data['year_of_experience'];
            }

            if (isset($data['experience_level'])) {
                $candidate->experience_level = $data['experience_level'];
            }

            if (isset($data['highest_qualification'])) {
                $candidate->highest_qualification = $data['highest_qualification'];
            }

            if (isset($data['prefer_job_industry'])) {
                $candidate->prefer_job_industry = $data['prefer_job_industry'];
            }

            if (isset($data['available_to_work'])) {
                $candidate->available_to_work = $data['available_to_work'];
            }

            if (isset($data['github'])) {
                $candidate->github = $data['github'];
            }

            if (isset($data['linkedin'])) {
                $candidate->linkedin = $data['linkedin'];
            }

            if (isset($data['twitter'])) {
                $candidate->twitter = $data['twitter'];
            }

            if (isset($data['portfolio_url'])) {
                $candidate->portfolio_url = $data['portfolio_url'];
            }

            // Handle skills separately as it's a JSON field
            if (isset($data['skills']) && is_array($data['skills'])) {
                $candidate->skills = $data['skills'];
            }

            // Save candidate changes
            $candidate->save();

            return $user->candidate()->with([
                'qualification',
                'workExperiences',
                'educationHistories',
                'languages',
                'portfolio',
                'credentials',
                'resumes',
            ])->first();
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
     * @param array $data
     * @return Resume
     */
    public function uploadResume(Candidate $candidate, array $data): Resume
    {
        return DB::transaction(function () use ($candidate, $data) {
            // Handle resume document upload
            if (isset($data['document']) && $data['document'] instanceof UploadedFile) {
                $data['document'] = $this->uploadCV(
                    $data['document'],
                    config('filestorage.paths.resumes')
                );
            }

            // Determine if this should be primary
            if (isset($data['is_primary']) && $data['is_primary']) {
                // If marked as primary, unset all other primary resumes
                $candidate->resumes()->update(['is_primary' => false]);
            } else {
                // If not provided, and no other resumes exist, make it primary
                if (!$candidate->resumes()->exists()) {
                    $data['is_primary'] = true;
                } else {
                    $data['is_primary'] = false;
                }
            }

            // Generate resume name if not provided
            if (empty($data['name'])) {
                $user = $candidate->user;
                $resumeCount = $candidate->resumes()->count() + 1;
                $data['name'] = "{$user->first_name} {$user->last_name} - Resume v{$resumeCount}";
            }

            // Create and return the resume
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
     * Upload an document to storage.
     *
     * @param UploadedFile $document The document file to upload.
     * @param string $path The storage path.
     * @param array $options Additional options for the upload.
     * @return string The path to the uploaded document.
     */
    private function uploadCv(UploadedFile $document, string $path, array $options = []): string
    {
        return $this->storageService->upload($document, $path, $options);
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
