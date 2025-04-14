<?php

namespace App\Services;

use App\Models\Candidate;
use App\Models\Employer;
use App\Models\Job;
use App\Models\JobApplication;
use App\Models\JobCategory;
use App\Models\JobViewCount;
use App\Models\ReportedJob;
use App\Models\Resume;
use App\Models\SavedJob;
use Exception;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

/**
 * Service class for job related operations
 */
class JobService
{
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
     * Apply for a job
     *
     * @param Job $job
     * @param Candidate $candidate
     * @param Resume|null $resume
     * @param string|null $coverLetter
     * @return JobApplication
     * @throws Exception
     */
    public function applyForJob(Job $job, Candidate $candidate, ?Resume $resume = null, ?string $coverLetter = null): JobApplication
    {
        // Check if job is available for application
        if ($job->is_draft) {
            throw new Exception('This job is not available for applications');
        }

        if (!$job->is_active) {
            throw new Exception('This job is not currently accepting applications');
        }

        if (!$job->is_approved) {
            throw new Exception('This job is pending approval and not available for applications');
        }

        // Check if candidate has already applied
        $existingApplication = JobApplication::query()->where('job_id', $job->id)
            ->where('candidate_id', $candidate->id)
            ->first();

        if ($existingApplication) {
            throw new Exception('You have already applied for this job');
        }

        // If no resume is provided, use the primary resume
        if (!$resume) {
            $resume = $candidate->primaryResume;

            if (!$resume) {
                throw new Exception('No resume provided or found');
            }
        }

        // Create job application
        return JobApplication::query()->create([
            'job_id' => $job->id,
            'candidate_id' => $candidate->id,
            'resume_id' => $resume->id,
            'cover_letter' => $coverLetter,
            'status' => 'applied',
        ]);
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
        return Job::query()->where('job_category_id', $job->job_category_id)
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

        // For public search, you might want to keep the approval requirement
        // based on your business logic. If you want to show unapproved jobs too,
        // just remove this line
        // $query->where('is_approved', true);

        // Apply filters
        if (!empty($filters['keyword'])) {
            $keyword = $filters['keyword'];
            $query->where(function($q) use ($keyword) {
                $q->where('title', 'like', "%{$keyword}%")
                    ->orWhere('description', 'like', "%{$keyword}%");
            });
        }

        if (!empty($filters['location'])) {
            $query->where('location', 'like', "%{$filters['location']}%");
        }

        // Filter by category
        if (!empty($filters['job_category_id'])) {
            $query->where('job_category_id', $filters['job_category_id']);
        }

        // Filter by province
        if (!empty($filters['province'])) {
            $query->where('province', $filters['province']);
        }

        // Filter by date posted
        if (!empty($filters['date_posted'])) {
            $datePosted = $filters['date_posted'];

            if ($datePosted === 'today') {
                $query->whereDate('created_at', now()->toDateString());
            } elseif ($datePosted === '3days') {
                $query->where('created_at', '>=', now()->subDays(3));
            } elseif ($datePosted === 'week') {
                $query->where('created_at', '>=', now()->subWeek());
            } elseif ($datePosted === 'month') {
                $query->where('created_at', '>=', now()->subMonth());
            }
            // 'any' doesn't need filtering
        }

        // Filter by job industry
        if (!empty($filters['job_industry'])) {
            $query->where('job_industry', $filters['job_industry']);
        }

        if (!empty($filters['experience_level'])) {
            $query->where('experience_level', $filters['experience_level']);
        }

        if (!empty($filters['min_salary'])) {
            $query->where('min_salary', '>=', $filters['min_salary']);
        }

        if (!empty($filters['is_remote'])) {
            $query->where('is_remote', true);
        }

        // Sort
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortOrder = $filters['sort_order'] ?? 'desc';

        $query->orderBy($sortBy, $sortOrder);

        return $query->with(['employer', 'category'])->paginate($perPage);
    }

    /**
     * Get latest jobs
     *
     * @param int $perPage
     * @param bool $withEmployer Include employer information
     * @param bool $withCategory Include category information
     * @return Collection
     */
    public function getLatestJobs(int $perPage = 10, bool $withEmployer = true, bool $withCategory = true): Collection
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

        return $query->limit($perPage)->get();
    }

    /**
     * Get featured jobs
     *
     * @param int $limit Maximum number of jobs to return
     * @param bool $withEmployer Include employer information
     * @param bool $withCategory Include category information
     * @return Collection
     */
    public function getFeaturedJobs(int $limit = 10, bool $withEmployer = true, bool $withCategory = true): Collection
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

        return $query->limit($limit)->get();
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
        $application->status = $status;

        if ($notes) {
            $application->employer_notes = $notes;
        }

        $application->save();

        return $application;
    }
}
