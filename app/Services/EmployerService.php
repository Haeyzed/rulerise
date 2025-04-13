<?php

namespace App\Services;

use App\Models\Candidate;
use App\Models\CandidatePool;
use App\Models\Employer;
use App\Models\JobNotificationTemplate;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Service class for employer related operations
 */
class EmployerService
{
    /**
     * Get employer details with open jobs
     *
     * @param int $employerId
     * @param int|null $jobsPerPage Number of jobs per page, null for all
     * @return array
     */
    public function getEmployerDetails(int $employerId, ?int $jobsPerPage = null): array
    {
        $employer = Employer::with(['user'])->findOrFail($employerId);

        // Get open jobs
        $jobsQuery = $employer->jobs()
            ->publiclyAvailable()
            ->latest();

        // Count all open jobs
        $openJobsCount = $jobsQuery->count();

        // Get paginated jobs or all jobs
        if ($jobsPerPage) {
            $jobs = $jobsQuery->paginate($jobsPerPage);
        } else {
            $jobs = $jobsQuery->get();
        }

        // Get job view statistics
        $totalJobViews = $employer->jobViewCounts()->count();

        return [
            'employer' => $employer,
            'jobs' => $jobs,
            'open_jobs_count' => $openJobsCount,
            'total_job_views' => $totalJobViews,
        ];
    }

    /**
     * Get featured employers
     *
     * @param int $limit Maximum number of employers to return
     * @param bool $withJobCount Include count of open jobs
     * @return Collection
     */
    public function getFeaturedEmployers(int $limit = 10, bool $withJobCount = true): Collection
    {
        $query = Employer::query()
            ->where('is_featured', true)
            ->where('is_verified', true);

        if ($withJobCount) {
            $query->withCount([
                'jobs' => function ($query) {
                    $query->publiclyAvailable();
                }
            ]);
        }

        return $query->limit($limit)->get();
    }

    /**
     * Get employer profile
     *
     * @param User $user
     * @return array
     */
    public function getProfile(User $user): array
    {
        $employer = $user->employer()->with([
            'jobs',
            'activeSubscription.plan',
        ])->first();

        return [
            'user' => $user,
            'employer' => $employer,
        ];
    }

    /**
     * Update employer profile
     *
     * @param User $user
     * @param array $data
     * @return Employer
     */
    public function updateProfile(User $user, array $data): Employer
    {
        DB::transaction(function () use ($user, $data) {
            // Update user data
            if (isset($data['name'])) {
                $user->name = $data['name'];
            }
            if (isset($data['phone'])) {
                $user->phone = $data['phone'];
            }
            $user->save();

            // Update employer data
            $employer = $user->employer;
            $employerData = array_intersect_key($data, array_flip([
                'company_name', 'company_description', 'industry',
                'company_size', 'company_website', 'location'
            ]));

            if (!empty($employerData)) {
                $employer->update($employerData);
            }
        });

        return $user->employer()->with([
            'jobs',
            'activeSubscription.plan',
        ])->first();
    }

    /**
     * Upload company logo
     *
     * @param Employer $employer
     * @param UploadedFile $file
     * @return Employer
     */
    public function uploadLogo(Employer $employer, UploadedFile $file): Employer
    {
        // Delete old logo if exists
        if ($employer->company_logo) {
            Storage::disk('public')->delete($employer->company_logo);
        }

        // Store new logo
        $path = $file->store('company-logos', 'public');
        $employer->company_logo = $path;
        $employer->save();

        return $employer;
    }

    /**
     * Create candidate pool
     *
     * @param Employer $employer
     * @param string $name
     * @param string|null $description
     * @return CandidatePool
     * @throws Exception
     */
    public function createCandidatePool(Employer $employer, string $name, ?string $description = null): CandidatePool
    {
        // Check if employer has an active subscription that allows candidate pools
        $subscription = $employer->activeSubscription;

        if (!$subscription || !$subscription->plan->can_create_candidate_pools) {
            throw new Exception('Your subscription does not allow creating candidate pools');
        }

        return $employer->candidatePools()->create([
            'name' => $name,
            'description' => $description,
        ]);
    }

    /**
     * Add candidate to pool
     *
     * @param CandidatePool $pool
     * @param Candidate $candidate
     * @param string|null $notes
     * @return void
     * @throws Exception
     */
    public function addCandidateToPool(CandidatePool $pool, Candidate $candidate, ?string $notes = null): void
    {
        // Check if candidate is already in the pool
        if ($pool->candidates()->where('candidate_id', $candidate->id)->exists()) {
            throw new Exception('Candidate is already in this pool');
        }

        $pool->candidates()->attach($candidate->id, ['notes' => $notes]);
    }

    /**
     * Remove candidate from pool
     *
     * @param CandidatePool $pool
     * @param Candidate $candidate
     * @return void
     */
    public function removeCandidateFromPool(CandidatePool $pool, Candidate $candidate): void
    {
        $pool->candidates()->detach($candidate->id);
    }

    /**
     * Create or update notification template
     *
     * @param Employer $employer
     * @param array $data
     * @param int|null $templateId
     * @return JobNotificationTemplate
     */
    public function saveNotificationTemplate(Employer $employer, array $data, ?int $templateId = null): JobNotificationTemplate
    {
        if ($templateId) {
            $template = $employer->notificationTemplates()->findOrFail($templateId);
            $template->update($data);
        } else {
            $template = $employer->notificationTemplates()->create($data);
        }

        return $template;
    }

    /**
     * Subscribe to a plan
     *
     * @param Employer $employer
     * @param SubscriptionPlan $plan
     * @param array $paymentData
     * @return Subscription
     * @throws Exception
     */
    public function subscribeToPlan(Employer $employer, SubscriptionPlan $plan, array $paymentData): Subscription
    {
        // Process payment (this would integrate with a payment gateway)
        $paymentSuccessful = true; // Placeholder for payment processing
        $transactionId = 'txn_' . uniqid(); // Placeholder for transaction ID

        if (!$paymentSuccessful) {
            throw new Exception('Payment failed');
        }

        // Calculate dates
        $startDate = now();
        $endDate = $startDate->copy()->addDays($plan->duration_days);

        // Create subscription
        return $employer->subscriptions()->create([
            'subscription_plan_id' => $plan->id,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'amount_paid' => $plan->price,
            'currency' => $plan->currency,
            'payment_method' => $paymentData['payment_method'] ?? 'card',
            'transaction_id' => $transactionId,
            'job_posts_left' => $plan->job_posts,
            'featured_jobs_left' => $plan->featured_jobs,
            'cv_downloads_left' => $plan->cv_downloads,
            'is_active' => true,
        ]);
    }

    /**
     * Update CV download usage
     *
     * @param Employer $employer
     * @return bool
     * @throws Exception
     */
    public function updateCvDownloadUsage(Employer $employer): bool
    {
        $subscription = $employer->activeSubscription;

        if (!$subscription || !$subscription->hasCvDownloadsLeft()) {
            throw new Exception('No active subscription or CV downloads left');
        }

        $subscription->cv_downloads_left -= 1;
        return $subscription->save();
    }
}
