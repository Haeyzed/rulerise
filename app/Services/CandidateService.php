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
        DB::transaction(function () use ($user, $data) {
            // Update user data
            if (isset($data['first_name'])) {
                $user->first_name = $data['first_name'];
            }
            if (isset($data['last_name'])) {
                $user->last_name = $data['last_name'];
            }
            if (isset($data['other_name'])) {
                $user->other_name = $data['other_name'];
            }
            if (isset($data['phone'])) {
                $user->phone = $data['phone'];
            }
            $user->save();

            // Update candidate data
            $candidate = $user->candidate;
            $candidateData = array_intersect_key($data, array_flip([
                'title', 'bio', 'current_position', 'current_company',
                'location', 'expected_salary', 'currency', 'job_type', 'is_available'
            ]));

            if (!empty($candidateData)) {
                $candidate->update($candidateData);
            }

            // Update qualification if provided
            if (isset($data['qualification'])) {
                $qualificationData = $data['qualification'];
                $qualification = $candidate->qualification;

                if (!$qualification) {
                    $qualification = new Qualification(['candidate_id' => $candidate->id]);
                }

                $qualification->fill($qualificationData);
                $qualification->save();
            }
        });

        return $user->candidate()->with([
            'qualification',
            'workExperiences',
            'educationHistories',
            'languages',
            'portfolio',
            'credentials',
            'resumes',
        ])->first();
    }

    /**
     * Upload profile picture
     *
     * @param User $user
     * @param UploadedFile $file
     * @return User
     */
    public function uploadProfilePicture(User $user, UploadedFile $file): User
    {
        // Delete old profile picture if exists
        if ($user->profile_picture) {
            Storage::disk('public')->delete($user->profile_picture);
        }

        // Store new profile picture
        $path = $file->store('profile-pictures', 'public');
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

        return $candidate->workExperiences()->create($data);
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
        return $workExperience;
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
        return $candidate->educationHistories()->create($data);
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
        return $educationHistory;
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
        return $candidate->languages()->create($data);
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
        return $language;
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
        return $candidate->credentials()->create($data);
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
        return $credential;
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
}
