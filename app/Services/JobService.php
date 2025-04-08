<?php

namespace App\Services;

use App\Models\Candidate;
use App\Models\Employer;
use App\Models\Job;
use App\Models\JobApplication;
use App\Models\JobViewCount;
use App\Models\ReportedJob;
use App\Models\Resume;
use App\Models\SavedJob;
use Exception;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
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

        if (!$subscription || !$subscription->hasJobPostsLeft()) {
            throw new Exception('No active subscription or job posts left');
        }

        // Generate slug
        $data['slug'] = Str::slug($data['title']) . '-' . Str::random(8);

        // Create job
        $job = $employer->jobs()->create($data);

        // Decrement job posts left
        $subscription->job_posts_left -= 1;
        $subscription->save();

        return $job;
    }

    /**
     * Update a job
     *
     * @param Job $job
     * @param array $data
     * @return Job
     */
    public function updateJob(Job $job, array $data): Job
    {
        // Update slug if title is changed
        if (isset($data['title']) && $data['title'] !== $job->title) {
            $data['slug'] = Str::slug($data['title']) . '-' . Str::random(8);
        }

        $job->update($data);

        return $job;
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
        $job->is_active = $isActive;
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
        // Check if job is already saved
        $existingSaved = SavedJob::query()->where('job_id', $job->id)
            ->where('candidate_id', $candidate->id)
            ->first();

        if ($existingSaved) {
            throw new Exception('Job already saved');
        }

        // Save job
        return SavedJob::query()->create([
            'job_id' => $job->id,
            'candidate_id' => $candidate->id,
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
     */
    public function recordJobView(Job $job, ?string $ipAddress = null, ?string $userAgent = null, ?int $candidateId = null): JobViewCount
    {
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
    public function getSimilarJobs(Job $job, int $limit = 5): Collection
    {
        return Job::query()->where('job_category_id', $job->job_category_id)
            ->where('id', '!=', $job->id)
            ->publiclyAvailable()
            ->latest()
            ->limit($limit)
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
        $query = Job::query()->publiclyAvailable();

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

        if (!empty($filters['job_type'])) {
            $query->where('job_type', $filters['job_type']);
        }

        if (!empty($filters['category_id'])) {
            $query->where('job_category_id', $filters['category_id']);
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

        return $query->with('employer')->paginate($perPage);
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
