<?php

namespace App\Services;

use App\Models\Candidate;
use App\Models\Degree;
use App\Models\Employer;
use App\Models\Resume;
use Exception;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Service class for resume related operations
 */
class ResumeService
{
    /**
     * Search candidates with filters for employers with active subscriptions
     *
     * @param Employer $employer
     * @param array $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     * @throws Exception
     */
    public function searchCandidates(Employer $employer, array $filters, int $perPage = 10): LengthAwarePaginator
    {
        // Check if employer has an active subscription with candidate search permission
        $subscription = $employer->activeSubscription;
        if (!$subscription) {
            throw new Exception('You need an active subscription to search candidates');
        }

        // Check if the subscription plan allows candidate search
        $plan = $subscription->plan;
        if (!$plan->candidate_search) {
            throw new Exception('Your current subscription plan does not include candidate search');
        }

        // Start with candidates who have active users
        $query = Candidate::query()
            ->whereHas('user', function (Builder $query) {
                $query->where('is_active', true)
                    ->where('is_shadow_banned', false);
            });

        // Apply keyword search
        if (!empty($filters['keyword'])) {
            $keyword = $filters['keyword'];
            $query->where(function ($q) use ($keyword) {
                $q->whereLike('current_position', "%{$keyword}%")
                    ->orWhereLike('current_company', "%{$keyword}%")
                    ->orWhereLike('bio', "%{$keyword}%")
                    ->orWhereJsonContains('skills', $keyword)
                    ->orWhereHas('user', function (Builder $q) use ($keyword) {
                        $q->where(DB::raw("CONCAT(first_name, ' ', last_name)"), 'like', "%{$keyword}%");
                    });
            });
        }

        // Filter by location
        if (!empty($filters['location'])) {
            $location = $filters['location'];
            $query->where(function ($q) use ($location) {
                $q->whereLike('location', "%{$location}%")
                    ->orWhereHas('user', function (Builder $q) use ($location) {
                        $q->whereLike('country',  "%{$location}%")
                            ->orWhereLike('state', "%{$location}%")
                            ->orWhereLike('city', "%{$location}%");
                    });
            });
        }

        // Filter by province/city
        if (!empty($filters['province'])) {
            $province = $filters['province'];
            $query->whereHas('user', function (Builder $q) use ($province) {
                $q->where('state', $province);
            });
        }

        // Filter by education level
        if (!empty($filters['education'])) {
            $educationId = $filters['education'];
//            $query->whereHas('educationHistories', function (Builder $q) use ($educationId) {
//                $q->where('degree_id', $educationId);
//            });
            $query->where('highest_qualification', $educationId);
        }

        // Filter by industry
        if (!empty($filters['industry'])) {
            $industry = $filters['industry'];
            $query->where('prefer_job_industry', $industry);
        }
//
//        // Filter by experience level
//        if (!empty($filters['experience'])) {
//            $experienceLevel = $filters['experience'];
//
//            // Map the frontend values to appropriate database queries
//            switch ($experienceLevel) {
//                case '0_1':
//                    $query->where('year_of_experience', '<=', 1);
//                    break;
//                case '1_3':
//                    $query->where('year_of_experience', '>', 1)
//                        ->where('year_of_experience', '<=', 3);
//                    break;
//                case '3_5':
//                    $query->where('year_of_experience', '>', 3)
//                        ->where('year_of_experience', '<=', 5);
//                    break;
//                case '5_10':
//                    $query->where('year_of_experience', '>', 5)
//                        ->where('year_of_experience', '<=', 10);
//                    break;
//                case '10_plus':
//                    $query->where('year_of_experience', '>', 10);
//                    break;
//            }
//        }

        // Sort by relevance, date, etc.
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortOrder = $filters['sort_order'] ?? 'desc';

        // Validate sort field to prevent SQL injection
        $allowedSortFields = ['created_at', 'updated_at', 'year_of_experience'];
        if (!in_array($sortBy, $allowedSortFields)) {
            $sortBy = 'created_at';
        }

        $query->orderBy($sortBy, $sortOrder);

        // Eager load relationships
        $query->with([
            'user',
            'qualification',
            'workExperiences',
            'educationHistories' => function ($query) {
                $query->with('degree');
            },
            'languages',
            'portfolio',
            'credentials',
            'resumes' => function ($query) {
                $query->where('is_primary', true);
            }
        ]);

        return $query->paginate($perPage);
    }

    /**
     * Get candidate details for an employer with active subscription
     *
     * @param int $id
     * @param Employer $employer
     * @return Candidate
     * @throws Exception
     */
    public function getCandidateDetails(int $id, Employer $employer): Candidate
    {
        // Check if employer has an active subscription with resume access
        $subscription = $employer->activeSubscription;
        if (!$subscription) {
            throw new Exception('You need an active subscription to view candidate details');
        }

        // Check if the subscription plan allows resume access
        $plan = $subscription->plan;
        if (!$plan->resume_access) {
            throw new Exception('Your current subscription plan does not include resume access');
        }

        $candidate = Candidate::with([
            'user',
            'qualification',
            'workExperiences',
            'educationHistories' => function ($query) {
                $query->with('degree');
            },
            'languages',
            'portfolio',
            'credentials',
            'resumes'
        ])->findOrFail($id);

        // Record candidate view
        $this->recordCandidateView($candidate, $employer);

        return $candidate;
    }

    /**
     * Download a candidate's resume
     *
     * @param int $resumeId
     * @param Employer $employer
     * @return Resume
     * @throws Exception
     */
    public function downloadResume(int $resumeId, Employer $employer): Resume
    {
        // Check if employer has an active subscription
        $subscription = $employer->activeSubscription;
        if (!$subscription) {
            throw new Exception('You need an active subscription to download resumes');
        }

        // Check if the subscription plan allows resume access
        $plan = $subscription->plan;
        if (!$plan->resume_access) {
            throw new Exception('Your current subscription plan does not include resume downloads');
        }

        // Check if employer has CV downloads left
        if ($subscription->cv_downloads_left <= 0) {
            throw new Exception('You have reached your resume download limit for this subscription period');
        }

        $resume = Resume::with('candidate.user')->findOrFail($resumeId);

        // Decrement the CV downloads left
        $subscription->decrement('cv_downloads_left');

        // Record the download
        DB::table('resume_downloads')->insert([
            'employer_id' => $employer->id,
            'resume_id' => $resume->id,
            'candidate_id' => $resume->candidate_id,
            'downloaded_at' => now(),
        ]);

        return $resume;
    }

    /**
     * Record candidate view by employer
     *
     * @param Candidate $candidate
     * @param Employer $employer
     * @return void
     */
    private function recordCandidateView(Candidate $candidate, Employer $employer): void
    {
        // Check if employer has already viewed this candidate today
        $existingView = DB::table('candidate_view_counts')
            ->where('employer_id', $employer->id)
            ->where('candidate_id', $candidate->id)
            ->whereDate('created_at', now()->toDateString())
            ->first();

        if (!$existingView) {
            // Record new view
            DB::table('candidate_view_counts')->insert([
                'employer_id' => $employer->id,
                'candidate_id' => $candidate->id,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Update total view count on candidate
            DB::table('candidates')
                ->where('id', $candidate->id)
                ->increment('view_count');
        }
    }

    /**
     * Get recommended candidates for an employer based on their job postings
     *
     * @param Employer $employer
     * @param int $perPage
     * @return LengthAwarePaginator
     * @throws Exception
     */
    public function getRecommendedCandidates(Employer $employer, int $perPage = 10): LengthAwarePaginator
    {
        // Check if employer has an active subscription with candidate search
        $subscription = $employer->activeSubscription;
        if (!$subscription) {
            throw new Exception('You need an active subscription to view recommended candidates');
        }

        // Check if the subscription plan allows candidate search
        $plan = $subscription->plan;
        if (!$plan->candidate_search) {
            throw new Exception('Your current subscription plan does not include candidate recommendations');
        }

        // Get employer's job categories and skills
        $jobCategories = $employer->jobs()->pluck('job_category_id')->unique()->toArray();
        $jobSkills = $employer->jobs()->pluck('skills_required')->flatten()->unique()->toArray();

        $query = Candidate::query()
            ->whereHas('user', function (Builder $query) {
                $query->where('is_active', true)
                    ->where('is_shadow_banned', false);
            });

        // Prioritize candidates with matching job categories
        if (!empty($jobCategories)) {
            $query->whereHas('educationHistories', function (Builder $q) use ($jobCategories) {
                $q->whereIn('field_of_study', function ($q) use ($jobCategories) {
                    $q->select('name')
                        ->from('job_categories')
                        ->whereIn('id', $jobCategories);
                });
            });
        }

        // Prioritize candidates with matching skills
        if (!empty($jobSkills)) {
            $query->where(function ($q) use ($jobSkills) {
                foreach ($jobSkills as $skill) {
                    $q->orWhereJsonContains('skills', $skill);
                }
            });
        }

        // Eager load relationships
        $query->with([
            'user',
            'qualification',
            'workExperiences',
            'educationHistories' => function ($query) {
                $query->with('degree');
            },
            'languages',
            'portfolio',
            'credentials',
            'resumes' => function ($query) {
                $query->where('is_primary', true);
            }
        ]);

        // Sort by most recent
        $query->latest();

        return $query->paginate($perPage);
    }

    /**
     * Get subscription usage statistics for an employer
     *
     * @param Employer $employer
     * @return array
     */
    public function getSubscriptionUsage(Employer $employer): array
    {
        $subscription = $employer->activeSubscription;

        if (!$subscription) {
            return [
                'has_subscription' => false,
                'message' => 'No active subscription found'
            ];
        }

        $plan = $subscription->plan;

        return [
            'has_subscription' => true,
            'subscription_name' => $plan->name,
            'cv_downloads' => [
                'used' => $plan->resume_views_limit - $subscription->cv_downloads_left,
                'total' => $plan->resume_views_limit,
                'remaining' => $subscription->cv_downloads_left
            ],
            'candidate_search' => $plan->candidate_search,
            'resume_access' => $plan->resume_access,
            'expires_at' => $subscription->end_date->format('Y-m-d'),
            'days_remaining' => $subscription->daysRemaining()
        ];
    }
}
