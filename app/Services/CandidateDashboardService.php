<?php

namespace App\Services;

use App\Models\BlogPost;
use App\Models\Candidate;
use App\Models\Job;
use App\Models\JobApplication;
use App\Models\JobViewCount;
use App\Models\ProfileViewCount;
use App\Models\SavedJob;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class CandidateDashboardService
{
    /**
     * Get dashboard metrics for a candidate
     *
     * @param Candidate $candidate
     * @param int $days Number of days to look back for metrics
     * @return array
     */
    public function getDashboardMetrics(Candidate $candidate, int $days = 30): array
    {
        $startDate = Carbon::now()->subDays($days);

        // Get job applications count
        $jobApplicationsCount = $this->getJobApplicationsCount($candidate, $startDate);

        // Get profile views count
        $profileViewsCount = $this->getProfileViewsCount($candidate, $startDate);

        // Get newest jobs
        $newestJobs = $this->getNewestJobs();

        // Get recommended jobs
        $recommendedJobs = $this->getRecommendedJobs($candidate);

        // Get saved jobs
        $savedJobs = $this->getSavedJobs($candidate);

        // Get applied jobs
        $appliedJobs = $this->getAppliedJobs($candidate);

        // Get profile completion percentage
        $profileCompletionPercentage = $this->calculateProfileCompletionPercentage($candidate);

        return [
            'job_applications_count' => $jobApplicationsCount,
            'profile_views_count' => $profileViewsCount,
            'newest_jobs' => $newestJobs,
            'recommended_jobs' => $recommendedJobs,
            'saved_jobs' => $savedJobs,
            'applied_jobs' => $appliedJobs,
            'profile_completion_percentage' => $profileCompletionPercentage,
            'days' => $days,
        ];
    }

    /**
     * Get job applications count for a candidate within a date range
     *
     * @param Candidate $candidate
     * @param Carbon $startDate
     * @return int
     */
    private function getJobApplicationsCount(Candidate $candidate, Carbon $startDate): int
    {
        return JobApplication::where('candidate_id', $candidate->id)
            ->where('created_at', '>=', $startDate)
            ->count();
    }

    /**
     * Get profile views count for a candidate within a date range
     *
     * @param Candidate $candidate
     * @param Carbon $startDate
     * @return int
     */
    private function getProfileViewsCount(Candidate $candidate, Carbon $startDate): int
    {
        return ProfileViewCount::where('candidate_id', $candidate->id)
            ->where('created_at', '>=', $startDate)
            ->count();
    }

    /**
     * Get newest jobs
     *
     * @param int $limit
     * @return Collection
     */
    public function getNewestJobs(int $limit = 10): Collection
    {
        return Job::publiclyAvailable()
            ->with(['employer', 'category'])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get recommended jobs for a candidate
     *
     * @param Candidate $candidate
     * @param int $limit
     * @return Collection
     */
    public function getRecommendedJobs(Candidate $candidate, int $limit = 10): Collection
    {
        // Get candidate's skills and preferences
        $skills = $candidate->skills ?? [];
        $preferredIndustry = $candidate->prefer_job_industry;
        $jobType = $candidate->job_type;
        $location = $candidate->location;

        // Build query for recommended jobs
        $query = Job::publiclyAvailable()
            ->with(['employer', 'category']);

        // Filter by industry if available
        if ($preferredIndustry) {
            $query->where('job_industry', $preferredIndustry);
        }

        // Filter by job type if available
        if ($jobType) {
            $query->where('job_type', $jobType);
        }

        // Filter by location if available
        if ($location) {
            $query->where('location', 'like', "%{$location}%");
        }

        // Filter by skills if available
        if (!empty($skills)) {
            $query->where(function ($q) use ($skills) {
                foreach ($skills as $skill) {
                    $q->orWhereJsonContains('skills_required', $skill);
                }
            });
        }

        // Exclude jobs the candidate has already applied to
        $appliedJobIds = JobApplication::where('candidate_id', $candidate->id)
            ->pluck('job_id')
            ->toArray();

        if (!empty($appliedJobIds)) {
            $query->whereNotIn('id', $appliedJobIds);
        }

        // Get recommended jobs
        return $query->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get saved jobs for a candidate
     *
     * @param Candidate $candidate
     * @param int $limit
     * @return Collection
     */
    public function getSavedJobs(Candidate $candidate, int $limit = 10): Collection
    {
        return SavedJob::where('candidate_id', $candidate->id)
            ->where('is_saved', true)
            ->with(['job.employer', 'job.category'])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->pluck('job');
    }

    /**
     * Get applied jobs for a candidate
     *
     * @param Candidate $candidate
     * @param int $limit
     * @return Collection
     */
    public function getAppliedJobs(Candidate $candidate, int $limit = 10): Collection
    {
        return JobApplication::where('candidate_id', $candidate->id)
            ->with(['job.employer', 'job.category'])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->pluck('job');
    }

    /**
     * Calculate profile completion percentage for a candidate
     *
     * @param Candidate $candidate
     * @return int
     */
    public function calculateProfileCompletionPercentage(Candidate $candidate): int
    {
        $totalFields = 0;
        $completedFields = 0;

        // Load relationships
        $candidate->load(['user', 'workExperiences', 'educationHistories', 'languages', 'credentials', 'resumes']);

        // Check user fields
        $userFields = [
            'first_name', 'last_name', 'email', 'phone', 'country', 'state', 'city', 'profile_picture'
        ];

        foreach ($userFields as $field) {
            $totalFields++;
            if (!empty($candidate->user->{$field})) {
                $completedFields++;
            }
        }

        // Check candidate fields
        $candidateFields = [
            'year_of_experience', 'highest_qualification', 'prefer_job_industry',
            'bio', 'current_position', 'current_company', 'location',
            'expected_salary', 'job_type', 'skills'
        ];

        foreach ($candidateFields as $field) {
            $totalFields++;
            if (!empty($candidate->{$field})) {
                $completedFields++;
            }
        }

        // Check relationships
        $relationships = [
            'workExperiences', 'educationHistories', 'languages', 'credentials', 'resumes'
        ];

        foreach ($relationships as $relationship) {
            $totalFields++;
            if ($candidate->{$relationship}->count() > 0) {
                $completedFields++;
            }
        }

        // Calculate percentage
        return ($completedFields / $totalFields) * 100;
    }

    /**
     * Get paginated jobs by type for a candidate
     *
     * @param Candidate $candidate
     * @param string $type Type of jobs to get (newest, recommended, saved, applied)
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getPaginatedJobs(Candidate $candidate, string $type, int $perPage = 10): LengthAwarePaginator
    {
        switch ($type) {
            case 'newest':
                return Job::publiclyAvailable()
                    ->with(['employer', 'category'])
                    ->orderBy('created_at', 'desc')
                    ->paginate($perPage);

            case 'recommended':
                // Get candidate's skills and preferences
                $skills = $candidate->skills ?? [];
                $preferredIndustry = $candidate->prefer_job_industry;
                $jobType = $candidate->job_type;
                $location = $candidate->location;

                // Build query for recommended jobs
                $query = Job::publiclyAvailable()
                    ->with(['employer', 'category']);

                // Filter by industry if available
                if ($preferredIndustry) {
                    $query->where('job_industry', $preferredIndustry);
                }

                // Filter by job type if available
                if ($jobType) {
                    $query->where('job_type', $jobType);
                }

                // Filter by location if available
                if ($location) {
                    $query->where('location', 'like', "%{$location}%");
                }

                // Filter by skills if available
                if (!empty($skills)) {
                    $query->where(function ($q) use ($skills) {
                        foreach ($skills as $skill) {
                            $q->orWhereJsonContains('skills_required', $skill);
                        }
                    });
                }

                // Exclude jobs the candidate has already applied to
                $appliedJobIds = JobApplication::where('candidate_id', $candidate->id)
                    ->pluck('job_id')
                    ->toArray();

                if (!empty($appliedJobIds)) {
                    $query->whereNotIn('id', $appliedJobIds);
                }

                // Get recommended jobs
                return $query->orderBy('created_at', 'desc')
                    ->paginate($perPage);

            case 'saved':
                $savedJobIds = SavedJob::where('candidate_id', $candidate->id)
                    ->where('is_saved', true)
                    ->pluck('job_id')
                    ->toArray();

                return Job::whereIn('id', $savedJobIds)
                    ->with(['employer', 'category'])
                    ->orderBy('created_at', 'desc')
                    ->paginate($perPage);

            case 'applied':
                $appliedJobIds = JobApplication::where('candidate_id', $candidate->id)
                    ->pluck('job_id')
                    ->toArray();

                return Job::whereIn('id', $appliedJobIds)
                    ->with(['employer', 'category'])
                    ->orderBy('created_at', 'desc')
                    ->paginate($perPage);

            default:
                return Job::publiclyAvailable()
                    ->with(['employer', 'category'])
                    ->orderBy('created_at', 'desc')
                    ->paginate($perPage);
        }
    }

    /**
     * Record a profile view for a candidate
     *
     * @param Candidate $candidate
     * @param string|null $ipAddress
     * @param string|null $userAgent
     * @param int|null $viewerId
     * @return ProfileViewCount
     */
    public function recordProfileView(
        Candidate $candidate,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        ?int $viewerId = null
    ): ProfileViewCount {
        return ProfileViewCount::query()->create([
            'candidate_id' => $candidate->id,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'viewer_id' => $viewerId,
        ]);
    }

    /**
     * Get latest blog posts
     *
     * @param int $limit
     * @return Collection
     */
    public function getLatestBlogPosts(int $limit = 2): Collection
    {
        return BlogPost::orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }
}
