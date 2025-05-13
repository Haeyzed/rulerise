<?php

namespace App\Services;

use App\Enums\JobNotificationTemplateTypeEnum;
use App\Models\Candidate;
use App\Models\Employer;
use App\Models\Job;
use App\Models\JobApplication;
use App\Models\JobCategory;
use App\Models\JobViewCount;
use App\Models\ReportedJob;
use App\Models\Resume;
use App\Models\SavedJob;
use App\Notifications\ApplicationStatusChanged;
use App\Notifications\CandidateApplicationReceived;
use App\Notifications\CandidateWithdrewApplication;
use App\Notifications\EmployerApplicationReceived;
use App\Notifications\JobApplicationWithdrawn;
use Exception;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;

/**
 * Service class for job related operations
 */
class JobService
{

    /**
     * Withdraw a job application
     *
     * @param JobApplication $application
     * @param string|null $reason
     * @return JobApplication
     * @throws Exception
     */
    public function withdrawApplication(JobApplication $application, ?string $reason = null): JobApplication
    {
        // Check if the application can be withdrawn (not already withdrawn, rejected, or hired)
        if (in_array($application->status, ['withdrawn', 'rejected', 'hired'])) {
            throw new Exception('This application cannot be withdrawn due to its current status: ' . $application->status);
        }

        // Update application status to withdrawn
        $application->status = 'withdrawn';
        $application->withdrawal_reason = $reason;
        $application->withdrawn_at = now();
        $application->save();

        // Get related entities
        $candidate = $application->candidate;
        $user = $candidate->user;
        $job = $application->job;
        $employer = $job->employer;
        $employerUser = $employer->user;

        // Notify the candidate
        $user?->notify(new JobApplicationWithdrawn($application, $job));

        // Notify the employer/HR team using their template
        if ($employerUser) {
            // Get the employer's withdrawal notification template
            $withdrawalTemplate = $employer->notificationTemplates()
                ->where('type', JobNotificationTemplateTypeEnum::APPLICATION_WITHDRAWN->value)
                ->first();

            // Use the template if available, otherwise use default notification
            if ($withdrawalTemplate) {
                $employerUser->notify(new CandidateWithdrewApplication(
                    $application,
                    $candidate,
                    $job,
                    $withdrawalTemplate
                ));
            } else {
                $employerUser->notify(new CandidateWithdrewApplication(
                    $application,
                    $candidate,
                    $job
                ));
            }
        }

        return $application;
    }

    /**
     * Get applicants by job with optional filtering and sorting
     *
     * @param Employer $employer
     * @param int $jobId
     * @param array $filters
     * @param string $sortBy
     * @param string $sortOrder
     * @param int $perPage
     * @return array
     */
    public function getApplicantByJobs(
        Employer $employer,
        int $jobId,
        array $filters = [],
        string $sortBy = 'created_at',
        string $sortOrder = 'desc',
        int $perPage = 15
    ): array {
        // Find the job and ensure it belongs to the employer
        $job = $employer->jobs()->findOrFail($jobId);

        // Get application counts for different statuses for this specific job
        $totalApplications = $job->applications()->count();
        $unsortedCount = $job->applications()->where('status', 'unsorted')->count();
        $shortlistedCount = $job->applications()->where('status', 'shortlisted')->count();
        $offerSentCount = $job->applications()->where('status', 'offer_sent')->count();
        $rejectedCount = $job->applications()->where('status', 'rejected')->count();
        $withdrawnCount = $job->applications()->where('status', 'withdrawn')->count();

        // Build the query for applications for this specific job
        $query = $job->applications();

        // Apply status filter if provided
        if (isset($filters['status']) && in_array($filters['status'], ['unsorted', 'shortlisted', 'offer_sent', 'rejected', 'withdrawn'])) {
            $query->where('status', $filters['status']);
        }

        // Eager load relationships
        $query->with([
            'candidate' => function($query) {
                $query->with([
                    'user',
                    'qualification',
                    'workExperiences',
                    'educationHistories',
                    'languages',
                    'portfolio',
                    'credentials',
                    'savedJobs',
                    'resumes',
                    'reportedJobs',
                    'profileViewCounts',
                ]);
            },
            'resume',
            'job'
        ]);

        // Apply sorting
        $query->orderBy($sortBy, $sortOrder);

        // Get paginated results
        $applications = $query->paginate($perPage);

        // Return both the paginated applications and the counts
        return [
            'job_applications' => $applications,
            'counts' => [
                'total' => $totalApplications,
                'unsorted' => $unsortedCount,
                'shortlisted' => $shortlistedCount,
                'offer_sent' => $offerSentCount,
                'rejected' => $rejectedCount,
                'withdrawn' => $withdrawnCount
            ]
        ];
    }

    /**
     * Apply for a job
     *
     * @param Job $job
     * @param Candidate $candidate
     * @param Resume|null $resume
     * @param string|null $coverLetter
     * @param string $applyVia
     * @return JobApplication
     * @throws Exception
     */
    public function applyForJob(Job $job, Candidate $candidate, ?Resume $resume = null, ?string $coverLetter = null, string $applyVia = 'profile_cv'): JobApplication
    {
        // Check if job is available for application
        if ($job->is_draft) {
            throw new Exception('This job is not available for applications');
        }

        if (!$job->is_active) {
            throw new Exception('This job is not currently accepting applications');
        }

        // Check if candidate has already applied
        $existingApplication = JobApplication::query()->where('job_id', $job->id)
            ->where('candidate_id', $candidate->id)
            ->first();

        if ($existingApplication) {
            throw new Exception('You have already applied for this job');
        }

        // Validate apply_via value
        if (!in_array($applyVia, ['custom_cv', 'profile_cv'])) {
            throw new Exception('Invalid application method');
        }

        // If no resume is provided, use the primary resume
        if (!$resume) {
            $resume = $candidate->primaryResume;

            if (!$resume) {
                throw new Exception('No resume provided or found');
            }
        }

        // Create job application
        $application = JobApplication::query()->create([
            'job_id' => $job->id,
            'candidate_id' => $candidate->id,
            'resume_id' => $resume->id,
            'cover_letter' => $coverLetter,
            'status' => 'applied',
            'apply_via' => $applyVia,
        ]);

        // Send notifications
        $this->sendApplicationNotifications($application);

        return $application;
    }

    /**
     * Send notifications for a new job application
     *
     * @param JobApplication $application
     * @return void
     */
    private function sendApplicationNotifications(JobApplication $application): void
    {
        $candidate = $application->candidate;
        $candidateUser = $candidate->user;
        $job = $application->job;
        $employer = $job->employer;
        $employerUser = $employer->user;

        // Notify the candidate
        $candidateUser?->notify(new CandidateApplicationReceived($application, $job));

        // Notify the employer using their template
        if ($employerUser) {
            // Get the employer's application received notification template
            $applicationTemplate = $employer->notificationTemplates()
                ->where('type', JobNotificationTemplateTypeEnum::APPLICATION_RECEIVED->value)
                ->first();

            // Use the template if available, otherwise use default notification
            if ($applicationTemplate) {
                $employerUser->notify(new EmployerApplicationReceived(
                    $application,
                    $candidate,
                    $job,
                    $applicationTemplate
                ));
            } else {
                $employerUser->notify(new EmployerApplicationReceived(
                    $application,
                    $candidate,
                    $job
                ));
            }
        }
    }

    /**
     * Change job application status
     *
     * @param JobApplication $application
     * @param string $status
     * @param string|null $notes
     * @return JobApplication
     */
    public function changeApplicationStatus(JobApplication $application, string $status, ?string $notes = null): JobApplication
    {
        // Store the previous status for comparison
        $previousStatus = $application->status;

        // Update application status
        $application->status = $status;

        if ($notes) {
            $application->employer_notes = $notes;
        }

        $application->save();

        // Only send notification if status has actually changed
        if ($previousStatus !== $status) {
            $this->sendStatusChangeNotification($application, $status);
        }

        return $application;
    }

    /**
     * Send notification to candidate about status change
     *
     * @param JobApplication $application
     * @param string $status
     * @return void
     */
    private function sendStatusChangeNotification(JobApplication $application, string $status): void
    {
        $candidate = $application->candidate;
        $candidateUser = $candidate->user;
        $job = $application->job;
        $employer = $job->employer;

        if (!$candidateUser) {
            return;
        }

        // Get the appropriate template type for this status
        $templateType = JobNotificationTemplateTypeEnum::fromApplicationStatus($status);

        // Get the employer's template for this status if available
        $template = $employer->notificationTemplates()
            ->where('type', $templateType->value)
            ->first();

        // Send notification to candidate
        $candidateUser->notify(new ApplicationStatusChanged(
            $application,
            $job,
            $status,
            $template
        ));
    }

    /**
     * Change status for multiple job applications
     *
     * @param array $applicationIds
     * @param string $status
     * @param string|null $notes
     * @return array
     */
    public function batchChangeApplicationStatus(array $applicationIds, string $status, ?string $notes = null): array
    {
        $results = [
            'success' => [],
            'failed' => []
        ];

        foreach ($applicationIds as $applicationId) {
            try {
                $application = JobApplication::query()->findOrFail($applicationId);

                // Store previous status
                $previousStatus = $application->status;

                // Update status and notes
                $application->status = $status;
                if ($notes) {
                    $application->employer_notes = $notes;
                }
                $application->save();

                // Send notification if status changed
                if ($previousStatus !== $status) {
                    $this->sendStatusChangeNotification($application, $status);
                }

                $results['success'][] = $application->id;
            } catch (\Exception $e) {
                $results['failed'][] = [
                    'application_id' => $applicationId,
                    'reason' => $e->getMessage()
                ];
            }
        }

        return $results;
    }

    /**
     * Get employer jobs with optional filtering and sorting
     *
     * @param Employer $employer
     * @param array $filters
     * @param string $sortBy
     * @param string $sortOrder
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getEmployerJobs(
        Employer $employer,
        array $filters = [],
        string $sortBy = 'created_at',
        string $sortOrder = 'desc',
        int $perPage = 15
    ): LengthAwarePaginator {
        $query = $employer->jobs();

        // Apply filters if provided
        if (isset($filters['status'])) {
            $status = $filters['status'];
            if ($status === 'open') {
                $query->where('is_active', true);
            } elseif ($status === 'close') {
                $query->where('is_active', false);
            }
        }

        if (isset($filters['featured'])) {
            $featured = $filters['featured'];
            $query->where('is_featured', $featured === 'true');
        }

        // Eager load relationships
        $query->with(['category', 'employer.candidatePools']);

        // Apply sorting
        $query->orderBy($sortBy, $sortOrder);

        return $query->paginate($perPage);
    }

    /**
     * Get a specific job for an employer
     *
     * @param Employer $employer
     * @param int $jobId
     * @return Job
     * @throws ModelNotFoundException
     */
    public function getEmployerJob(Employer $employer, int $jobId): Job
    {
        return $employer->jobs()
            ->with(['category', 'applications.candidate.user'])
            ->findOrFail($jobId);
    }


    /**
     * Get saved jobs for a candidate
     *
     * @param Candidate $candidate
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getSavedJobs(Candidate $candidate, int $perPage = 10): LengthAwarePaginator
    {
        return SavedJob::query()
            ->where('candidate_id', $candidate->id)
            ->where('is_saved', true)
            ->with(['job.employer', 'job.category'])
            ->latest()
            ->paginate($perPage);
    }

    /**
     * Get applied jobs for a candidate
     *
     * @param Candidate $candidate
     * @param array $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getAppliedJobs(Candidate $candidate, array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        $query = JobApplication::query()
            ->where('candidate_id', $candidate->id)
            ->with(['job.employer', 'job.category', 'resume']);

        // Filter by status if provided
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Filter by date range if provided
        if (!empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        return $query->latest()->paginate($perPage);
    }

    /**
     * Get recommended jobs for a candidate
     *
     * @param Candidate $candidate
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getRecommendedJobs(Candidate $candidate, int $perPage = 10): LengthAwarePaginator
    {
        // Get candidate's skills, experience, and preferences
        $candidateSkills = $candidate->skills ?? [];
        $candidateIndustry = $candidate->industry ?? null;
        $candidateExperienceLevel = $candidate->experience_level ?? null;

        // Get candidate's applied job categories
        $appliedJobCategoryIds = JobApplication::query()
            ->where('candidate_id', $candidate->id)
            ->join('job_listings', 'job_applications.job_id', '=', 'job_listings.id')
            ->pluck('job_listings.job_category_id')
            ->unique()
            ->toArray();

        // Build query for recommended jobs
        $query = Job::query()
            ->where('is_draft', false)
            ->where('is_active', true)
            ->notExpired()
            ->with(['employer', 'category']);

        // Exclude jobs the candidate has already applied to
        $appliedJobIds = JobApplication::query()
            ->where('candidate_id', $candidate->id)
            ->pluck('job_id')
            ->toArray();

        if (!empty($appliedJobIds)) {
            $query->whereNotIn('id', $appliedJobIds);
        }

        // Prioritize jobs in the same categories the candidate has applied to before
        if (!empty($appliedJobCategoryIds)) {
            $query->orderByRaw("CASE WHEN job_category_id IN (" . implode(',', $appliedJobCategoryIds) . ") THEN 0 ELSE 1 END");
        }

        // Prioritize jobs matching candidate's industry if available
        if ($candidateIndustry) {
            $query->orderByRaw("CASE WHEN job_industry = ? THEN 0 ELSE 1 END", [$candidateIndustry]);
        }

        // Prioritize jobs matching candidate's experience level if available
        if ($candidateExperienceLevel) {
            $query->orderByRaw("CASE WHEN experience_level = ? THEN 0 ELSE 1 END", [$candidateExperienceLevel]);
        }

        // Prioritize jobs with skills matching the candidate's skills
        if (!empty($candidateSkills)) {
            foreach ($candidateSkills as $index => $skill) {
                $query->orderByRaw("CASE WHEN JSON_CONTAINS(skills_required, ?) THEN 0 ELSE 1 END", [json_encode($skill)]);
            }
        }

        // Finally, sort by creation date (newest first)
        $query->latest();

        return $query->paginate($perPage);
    }

    /**
     * Create a new job
     *
     * @param Employer $employer
     * @param array $data
     * @return Job
     * @throws Exception
     */
    public function createJob(Employer $employer, array $data): Job
    {
        // Check if employer has an active subscription with job posts left
        $subscription = $employer->activeSubscription;

//        if (!$subscription || !$subscription->hasJobPostsLeft()) {
//            throw new Exception('No active subscription or job posts left');
//        }

        // Validate job category exists
        if (!empty($data['job_category_id'])) {
            $category = JobCategory::query()->find($data['job_category_id']);
            if (!$category) {
                throw new Exception('Invalid job category selected');
            }
        }

        // Generate slug
        $data['slug'] = Str::slug($data['title']) . '-' . Str::random(8);

        // Set default values for draft/public status
        if (!isset($data['is_draft'])) {
            $data['is_draft'] = false;
        }

        if (!isset($data['is_active'])) {
            // If it's a draft, it shouldn't be active by default
            $data['is_active'] = !$data['is_draft'];
        }

        // If it's a draft, it shouldn't be approved by default
        if ($data['is_draft'] && !isset($data['is_approved'])) {
            $data['is_approved'] = false;
        }

        // Create job
        $job = $employer->jobs()->create($data);

        // Decrement job posts left
//        $subscription->job_posts_left -= 1;
//        $subscription->save();

        return $job->load('category');
    }

    /**
     * Update a job
     *
     * @param Job $job
     * @param array $data
     * @return Job
     * @throws Exception
     */
    public function updateJob(Job $job, array $data): Job
    {
        // Validate job category exists if provided
        if (!empty($data['job_category_id'])) {
            $category = JobCategory::query()->find($data['job_category_id']);
            if (!$category) {
                throw new Exception('Invalid job category selected');
            }
        }

        // Update slug if title is changed
        if (isset($data['title']) && $data['title'] !== $job->title) {
            $data['slug'] = Str::slug($data['title']) . '-' . Str::random(8);
        }

        // Handle draft/public status logic
        if (isset($data['is_draft']) && $data['is_draft'] != $job->is_draft) {
            // If changing from draft to published
            if ($job->is_draft && !$data['is_draft']) {
                // When publishing a draft, make it active by default unless specified
                if (!isset($data['is_active'])) {
                    $data['is_active'] = true;
                }
            }
            // If changing from published to draft
            else if (!$job->is_draft && $data['is_draft']) {
                // When converting to draft, make it inactive by default
                if (!isset($data['is_active'])) {
                    $data['is_active'] = false;
                }
            }
        }

        $job->update($data);

        return $job->load('category');
    }

    /**
     * Delete a job
     *
     * @param Job $job
     * @return bool
     */
    public function deleteJob(Job $job): bool
    {
        return $job->delete();
    }

    /**
     * Set job as featured
     *
     * @param Job $job
     * @return Job
     * @throws Exception
     */
    public function setJobAsFeatured(Job $job): Job
    {
        // Check if employer has an active subscription with featured jobs left
        $subscription = $job->employer->activeSubscription;

        if (!$subscription || !$subscription->hasFeaturedJobsLeft()) {
            throw new Exception('No active subscription or featured jobs left');
        }

        // Set job as featured
        $job->is_featured = true;
        $job->save();

        // Decrement featured jobs left
        $subscription->featured_jobs_left -= 1;
        $subscription->save();

        return $job;
    }

    /**
     * Set job open/close status
     *
     * @param Job $job
     * @param bool $isActive
     * @return Job
     */
    public function setJobStatus(Job $job, bool $isActive): Job
    {
        // If activating a job that's a draft, prevent it
        if ($isActive && $job->is_draft) {
            throw new Exception('Cannot activate a job that is in draft mode');
        }

        $job->is_active = $isActive;
        $job->save();

        return $job;
    }

    /**
     * Publish a draft job
     *
     * @param Job $job
     * @return Job
     * @throws Exception
     */
    public function publishDraftJob(Job $job): Job
    {
        if (!$job->is_draft) {
            throw new Exception('Job is already published');
        }

        // Ensure the job has a category
        if (empty($job->job_category_id)) {
            throw new Exception('Job must have a category before publishing');
        }

        $job->is_draft = false;
        $job->is_active = true;
        $job->save();

        return $job;
    }

    /**
     * Save a job
     *
     * @param Job $job
     * @param Candidate $candidate
     * @return SavedJob
     * @throws Exception
     */
    public function saveJob(Job $job, Candidate $candidate): SavedJob
    {
        // Check if job is available for saving
        if ($job->is_draft) {
            throw new Exception('This job is not available');
        }

        // Check if the job is already saved
        $existingSaved = SavedJob::query()
            ->where('job_id', $job->id)
            ->where('candidate_id', $candidate->id)
            ->first();

        if ($existingSaved) {
            // Toggle the `is_saved` value
            $existingSaved->is_saved = !$existingSaved->is_saved;
            $existingSaved->save();

            return $existingSaved;
        }

        // Create a new saved record if it doesn't exist
        return SavedJob::query()->create([
            'job_id' => $job->id,
            'candidate_id' => $candidate->id,
            'is_saved' => true,
        ]);
    }

    /**
     * Unsave a job
     *
     * @param Job $job
     * @param Candidate $candidate
     * @return bool
     */
    public function unsaveJob(Job $job, Candidate $candidate): bool
    {
        return SavedJob::query()->where('job_id', $job->id)
            ->where('candidate_id', $candidate->id)
            ->delete();
    }

    /**
     * Report a job
     *
     * @param Job $job
     * @param Candidate $candidate
     * @param string $reason
     * @param string|null $description
     * @return ReportedJob
     * @throws Exception
     */
    public function reportJob(Job $job, Candidate $candidate, string $reason, ?string $description = null): ReportedJob
    {
        // Check if job is available for reporting
        if ($job->is_draft) {
            throw new Exception('This job is not available');
        }

        // Check if job is already reported by this candidate
        $existingReport = ReportedJob::query()->where('job_id', $job->id)
            ->where('candidate_id', $candidate->id)
            ->first();

        if ($existingReport) {
            throw new Exception('You have already reported this job');
        }

        // Report job
        return ReportedJob::query()->create([
            'job_id' => $job->id,
            'candidate_id' => $candidate->id,
            'reason' => $reason,
            'description' => $description,
        ]);
    }

    /**
     * Record job view
     *
     * @param Job $job
     * @param string|null $ipAddress
     * @param string|null $userAgent
     * @param int|null $candidateId
     * @return JobViewCount
     * @throws Exception
     */
    public function recordJobView(Job $job, ?string $ipAddress = null, ?string $userAgent = null, ?int $candidateId = null): JobViewCount
    {
        // Check if job is available for viewing
        if ($job->is_draft) {
            throw new Exception('This job is not available');
        }

        return $job->viewCounts()->create([
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'candidate_id' => $candidateId,
        ]);
    }

    /**
     * Get similar jobs
     *
     * @param Job $job
     * @param int $limit
     * @return Collection
     */
    public function getSimilarJobs(Job $job, int $perPage = 5): Collection
    {
        return Job::with([
            'employer:id,company_name,company_email,company_logo'
        ])
            ->where('job_category_id', $job->job_category_id)
            ->where('id', '!=', $job->id)
            ->where('is_draft', false)
            ->where('is_active', true)
            ->notExpired()
            ->latest()
            ->limit($perPage)
            ->get();
    }

    /**
     * Search jobs
     *
     * @param array $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function searchJobs(array $filters, int $perPage = 10): LengthAwarePaginator
    {
        $query = Job::query()
            ->where('is_draft', false)
            ->where('is_active', true)
            ->notExpired();

        // Apply filters
        if (!empty($filters['keyword'])) {
            $keyword = $filters['keyword'];
            $query->where(function($q) use ($keyword) {
                $q->where('title', 'like', "%{$keyword}%")
                    ->orWhere('description', 'like', "%{$keyword}%")
                    ->orWhere('short_description', 'like', "%{$keyword}%");
            });
        }

        if (!empty($filters['location'])) {
            $query->where(function($q) use ($filters) {
                $q->where('location', 'like', "%{$filters['location']}%");
            });
        }

        // Filter by province
        if (!empty($filters['province'])) {
            $query->where('state', $filters['province']);
        }

        // Filter by date posted
        if (!empty($filters['date_posted'])) {
            $query->whereDate('created_at', $filters['date_posted']);
        }

        // Filter by job industry (which is actually job category)
        if (!empty($filters['job_industry'])) {
            // First try to find by exact category name
            $category = JobCategory::where('name', $filters['job_industry'])->first();

            if ($category) {
                // If found, filter by category ID
                $query->where('job_category_id', $category->id);
            } else {
                // If not found by exact name, try to search by similar name
                $query->whereHas('category', function($q) use ($filters) {
                    $q->where('name', 'like', "%{$filters['job_industry']}%");
                });
            }
        }

        // Filter by experience level
        if (!empty($filters['experience_level'])) {
            $experienceLevel = $filters['experience_level'];

            // Map the frontend values to appropriate database queries
            switch ($experienceLevel) {
                case '0_1':
                    $query->where('years_of_experience', '<=', 1);
                    break;
                case '1_3':
                    $query->where('years_of_experience', '>', 1)
                        ->where('years_of_experience', '<=', 3);
                    break;
                case '3_5':
                    $query->where('years_of_experience', '>', 3)
                        ->where('years_of_experience', '<=', 5);
                    break;
                case '5_10':
                    $query->where('years_of_experience', '>', 5)
                        ->where('years_of_experience', '<=', 10);
                    break;
                case '10_plus':
                    $query->where('years_of_experience', '>', 10);
                    break;
            }
        }

        // Sort
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortOrder = $filters['sort_order'] ?? 'desc';

        // Validate sort field to prevent SQL injection
        $allowedSortFields = ['created_at', 'title', 'salary'];
        if (!in_array($sortBy, $allowedSortFields)) {
            $sortBy = 'created_at';
        }

        $query->orderBy($sortBy, $sortOrder);

        // Always include these relationships
        return $query->with(['employer', 'category'])->paginate($perPage);
    }

/**
     * Get latest jobs
     *
     * @param int $perPage
     * @param bool $withEmployer Include employer information
     * @param bool $withCategory Include category information
     * @return LengthAwarePaginator
     */
    public function getLatestJobs(int $perPage = 10, bool $withEmployer = true, bool $withCategory = true): LengthAwarePaginator
    {
        $query = Job::query()
            ->where('is_draft', false)
            ->where('is_active', true)
            ->notExpired()
            ->latest();

        if ($withEmployer) {
            $query->with('employer');
        }

        if ($withCategory) {
            $query->with('category');
        }

        return $query->paginate($perPage);
    }

    /**
     * Get featured jobs
     *
     * @param int $perPage Maximum number of jobs to return
     * @param bool $withEmployer Include employer information
     * @param bool $withCategory Include category information
     * @return LengthAwarePaginator
     */
    public function getFeaturedJobs(int $perPage = 10, bool $withEmployer = true, bool $withCategory = true): LengthAwarePaginator
    {
        $query = Job::query()
            ->where('is_draft', false)
            ->where('is_active', true)
            ->where('is_featured', true)
            ->notExpired()
            ->latest();

        if ($withEmployer) {
            $query->with('employer');
        }

        if ($withCategory) {
            $query->with('category');
        }

        return $query->paginate($perPage);
    }
}
