<?php

namespace App\Services;

use App\Models\Candidate;
use App\Models\CandidatePool;
use App\Models\Employer;
use App\Models\Job;
use App\Models\JobApplication;
use App\Models\JobNotificationTemplate;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Storage\StorageService;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Throwable;

/**
 * Service class for employer related operations
 */
class EmployerService
{
    /**
     * @var StorageService
     */
    protected StorageService $storageService;
    /**
     * Job service instance
     *
     * @var JobService
     */
    protected JobService $jobService;

    /**
     * BlogPostService constructor.
     *
     * @param StorageService $storageService
     * @param JobService $jobService
     */
    public function __construct(StorageService $storageService, JobService $jobService)
    {
        $this->storageService = $storageService;
        $this->jobService = $jobService;
    }


    /**
     * Get a paginated list of employers with filters
     *
     * @param array $filters
     * @return LengthAwarePaginator
     */
    public function getEmployers(array $filters = []): LengthAwarePaginator
    {
        $query = Employer::with(['user', 'activeSubscription.plan'])
            ->withCount('jobs');

        // Apply search filter
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function($q) use ($search) {
                $q->where('company_name', 'like', "%{$search}%")
                    ->orWhere('company_email', 'like', "%{$search}%")
                    ->orWhereHas('user', function($q) use ($search) {
                        $q->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        // Apply status filter
        if (isset($filters['status'])) {
            if ($filters['status'] === 'active') {
                $query->whereHas('user', function($q) {
                    $q->where('is_active', true);
                });
            } elseif ($filters['status'] === 'inactive') {
                $query->whereHas('user', function($q) {
                    $q->where('is_active', false);
                });
            } elseif ($filters['status'] === 'blacklisted') {
                $query->whereHas('user', function($q) {
                    $q->where('is_shadow_banned', true);
                });
            }
        }

        // Apply featured filter
        if (isset($filters['is_featured'])) {
            $query->where('is_featured', $filters['is_featured']);
        }

        // Apply verified filter
        if (isset($filters['is_verified'])) {
            $query->where('is_verified', $filters['is_verified']);
        }

        // Apply date range filter
        if (!empty($filters['date_from']) && !empty($filters['date_to'])) {
            $query->whereBetween('created_at', [$filters['date_from'], $filters['date_to']]);
        } elseif (!empty($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        } elseif (!empty($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to']);
        }

        // Apply sorting
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortOrder = $filters['sort_order'] ?? 'desc';
        $query->orderBy($sortBy, $sortOrder);

        // Paginate results
        $perPage = $filters['per_page'] ?? config('app.pagination.per_page', 15);

        return $query->paginate($perPage);
    }

    /**
     * Get employer profile details
     *
     * @param int $employerId
     * @return Employer
     */
    public function getEmployerProfile(int $employerId): Employer
    {
        return Employer::with([
            'user',
            'activeSubscription.plan',
        ])->findOrFail($employerId);
    }

    /**
     * Get employer job listings with filters
     *
     * @param int $employerId
     * @param array $filters
     * @return LengthAwarePaginator
     */
    public function getEmployerJobsForAdmin(int $employerId, array $filters = []): LengthAwarePaginator
    {
        $query = Job::where('employer_id', $employerId)
            ->withCount('applications');

        // Apply search filter
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('short_description', 'like', "%{$search}%")
                    ->orWhere('location', 'like', "%{$search}%");
            });
        }

        // Apply status filter
        if (isset($filters['status'])) {
            if ($filters['status'] === 'open') {
                $query->where('is_active', true)
                    ->where(function($q) {
                        $q->whereNull('deadline')
                            ->orWhere('deadline', '>=', now());
                    });
            } elseif ($filters['status'] === 'closed') {
                $query->where(function($q) {
                    $q->where('is_active', false)
                        ->orWhere(function($sq) {
                            $sq->whereNotNull('deadline')
                                ->where('deadline', '<', now());
                        });
                });
            } elseif ($filters['status'] === 'draft') {
                $query->where('is_draft', true);
            }
        }

        // Apply job type filter
        if (!empty($filters['job_type'])) {
            $query->where('job_type', $filters['job_type']);
        }

        // Apply location filter
        if (!empty($filters['location'])) {
            $query->where('location', 'like', "%{$filters['location']}%");
        }

        // Apply date range filter
        if (!empty($filters['date_from']) && !empty($filters['date_to'])) {
            $query->whereBetween('created_at', [$filters['date_from'], $filters['date_to']]);
        } elseif (!empty($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        } elseif (!empty($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to']);
        }

        // Apply sorting
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortOrder = $filters['sort_order'] ?? 'desc';
        $query->orderBy($sortBy, $sortOrder);

        // Paginate results
        $perPage = $filters['per_page'] ?? config('app.pagination.per_page', 15);

        return $query->paginate($perPage);
    }

    /**
     * Get hired candidates for an employer with filters
     *
     * @param int $employerId
     * @param array $filters
     * @return LengthAwarePaginator
     */
    public function getHiredCandidates(int $employerId, array $filters = []): LengthAwarePaginator
    {
        $query = JobApplication::whereHas('job', function (Builder $query) use ($employerId) {
            $query->where('employer_id', $employerId);
        })
            ->where('status', 'hired')
            ->with(['candidate.user', 'job']);

        // Apply search filter
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function($q) use ($search) {
                $q->whereHas('candidate.user', function($q) use ($search) {
                    $q->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                })
                    ->orWhereHas('job', function($q) use ($search) {
                        $q->where('title', 'like', "%{$search}%");
                    });
            });
        }

        // Apply job filter
        if (!empty($filters['job_id'])) {
            $query->where('job_id', $filters['job_id']);
        }

        // Apply date range filter
        if (!empty($filters['date_from']) && !empty($filters['date_to'])) {
            $query->whereBetween('created_at', [$filters['date_from'], $filters['date_to']]);
        } elseif (!empty($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        } elseif (!empty($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to']);
        }

        // Apply sorting
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortOrder = $filters['sort_order'] ?? 'desc';
        $query->orderBy($sortBy, $sortOrder);

        // Paginate results
        $perPage = $filters['per_page'] ?? config('app.pagination.per_page', 15);

        return $query->paginate($perPage);
    }

    /**
     * Get employer transactions with filters
     *
     * @param int $employerId
     * @param array $filters
     * @return LengthAwarePaginator
     */
    public function getEmployerTransactions(int $employerId, array $filters = []): LengthAwarePaginator
    {
        $query = Subscription::where('employer_id', $employerId)
            ->with('plan');

        // Apply search filter
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function($q) use ($search) {
                $q->where('transaction_id', 'like', "%{$search}%")
                    ->orWhere('payment_reference', 'like', "%{$search}%")
                    ->orWhereHas('plan', function($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%");
                    });
            });
        }

        // Apply payment method filter
        if (!empty($filters['payment_method'])) {
            $query->where('payment_method', $filters['payment_method']);
        }

        // Apply status filter
        if (isset($filters['status'])) {
            if ($filters['status'] === 'active') {
                $query->where('is_active', true)
                    ->where('end_date', '>=', now());
            } elseif ($filters['status'] === 'expired') {
                $query->where('end_date', '<', now());
            } elseif ($filters['status'] === 'cancelled') {
                $query->where('is_active', false);
            }
        }

        // Apply date range filter
        if (!empty($filters['date_from']) && !empty($filters['date_to'])) {
            $query->whereBetween('created_at', [$filters['date_from'], $filters['date_to']]);
        } elseif (!empty($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        } elseif (!empty($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to']);
        }

        // Apply sorting
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortOrder = $filters['sort_order'] ?? 'desc';
        $query->orderBy($sortBy, $sortOrder);

        // Paginate results
        $perPage = $filters['per_page'] ?? config('app.pagination.per_page', 15);

        return $query->paginate($perPage);
    }

    /**
     * Get employer statistics
     *
     * @param int $employerId
     * @return array
     */
    public function getEmployerStatistics(int $employerId): array
    {
        $employer = Employer::findOrFail($employerId);

        // Job statistics
        $totalJobs = $employer->jobs()->count();
        $activeJobs = $employer->jobs()->where('is_active', true)->count();
        $draftJobs = $employer->jobs()->where('is_draft', true)->count();
        $expiredJobs = $employer->jobs()
            ->where('deadline', '<', now())
            ->where('deadline', '!=', null)
            ->count();

        // Application statistics
        $totalApplications = $employer->applications()->count();

        // Application status counts
        $applicationStatusCounts = $employer->applications()
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        // Hired candidates count
        $hiredCandidatesCount = $employer->applications()
            ->where('status', 'hired')
            ->count();

        // Subscription status
        $hasActiveSubscription = $employer->hasActiveSubscription();
        $activeSubscription = $employer->activeSubscription()->with('plan')->first();

        return [
            'total_jobs' => $totalJobs,
            'active_jobs' => $activeJobs,
            'draft_jobs' => $draftJobs,
            'expired_jobs' => $expiredJobs,
            'total_applications' => $totalApplications,
            'application_status_counts' => $applicationStatusCounts,
            'hired_candidates_count' => $hiredCandidatesCount,
            'has_active_subscription' => $hasActiveSubscription,
            'active_subscription' => $activeSubscription,
        ];
    }

    /**
     * Delete an employer
     *
     * @param int $employerId
     * @return bool
     */
    public function deleteEmployer(int $employerId): bool
    {
        $employer = Employer::findOrFail($employerId);
        $user = $employer->user;

        // Begin transaction to ensure both employer and user are deleted
        return \DB::transaction(function() use ($employer, $user) {
            // Delete the employer
            $employer->delete();

            // Soft delete the user
            $user->delete();

            return true;
        });
    }

    /**
     * Moderate employer account status (activate/deactivate)
     *
     * @param int $employerId
     * @param bool $isActive
     * @return User
     */
    public function moderateEmployerAccountStatus(int $employerId, bool $isActive): User
    {
        $employer = Employer::findOrFail($employerId);
        $user = $employer->user;

        // Update user active status
        $user->is_active = $isActive;
        $user->save();

        return $user;
    }

    /**
     * Set shadow-ban status for an employer
     *
     * @param int $employerId
     * @param bool $isShadowBanned
     * @return User
     */
    public function setShadowBanForEmployer(int $employerId, bool $isShadowBanned): User
    {
        $employer = Employer::findOrFail($employerId);
        $user = $employer->user;

        // Update user shadow-ban status
        $user->is_shadow_banned = $isShadowBanned;
        $user->save();

        return $user;
    }

    /**
     * Get candidates who applied to employer's jobs with optional filtering and sorting
     *
     * @param Employer $employer
     * @param array $filters
     * @param string $sortBy
     * @param string $sortOrder
     * @param int $perPage
     * @return array
     */
    public function getEmployersCandidateAppliedJobs(
        Employer $employer,
        array $filters = [],
        string $sortBy = 'created_at',
        string $sortOrder = 'desc',
        int $perPage = 15
    ): array {
        // Get all job IDs for this employer
        $jobIds = $employer->jobs()->pluck('id')->toArray();

        if (empty($jobIds)) {
            return [
                'candidates' => new LengthAwarePaginator([], 0, $perPage),
                'counts' => [
                    'total' => 0,
                    'unsorted' => 0,
                    'sorted' => 0,
                    'shortlisted' => 0,
                    'offer_sent' => 0,
                ]
            ];
        }

        // Get counts for different application statuses
        $totalApplications = DB::table('job_applications')
            ->whereIn('job_id', $jobIds)
            ->count();

        $unsortedCount = DB::table('job_applications')
            ->whereIn('job_id', $jobIds)
            ->where('status', 'unsorted')
            ->count();

        $sortedCount = DB::table('job_applications')
            ->whereIn('job_id', $jobIds)
            ->where('status', 'sorted')
            ->count();

        $shortlistedCount = DB::table('job_applications')
            ->whereIn('job_id', $jobIds)
            ->where('status', 'shortlisted')
            ->count();

        $offerSentCount = DB::table('job_applications')
            ->whereIn('job_id', $jobIds)
            ->where('status', 'offer_sent')
            ->count();

        // Build the query for candidates who applied to this employer's jobs
        $query = Candidate::query()
            ->whereHas('jobApplications', function ($query) use ($jobIds, $filters) {
                $query->whereIn('job_id', $jobIds);

                // Apply status filter if provided
                if (isset($filters['status']) && $filters['status'] !== 'all') {
                    $query->where('status', $filters['status']);
                }
            })
            ->with([
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
                'jobApplications' => function ($query) use ($jobIds) {
                    $query->whereIn('job_id', $jobIds)
                        ->with('job:id,title,location,salary,job_type');
                }
            ])
            ->withCount([
                'jobApplications as total_applications' => function ($query) use ($jobIds) {
                    $query->whereIn('job_id', $jobIds);
                },
                'jobApplications as unsorted_count' => function ($query) use ($jobIds) {
                    $query->whereIn('job_id', $jobIds)->where('status', 'unsorted');
                },
                'jobApplications as sorted_count' => function ($query) use ($jobIds) {
                    $query->whereIn('job_id', $jobIds)->where('status', 'sorted');
                },
                'jobApplications as shortlisted_count' => function ($query) use ($jobIds) {
                    $query->whereIn('job_id', $jobIds)->where('status', 'shortlisted');
                },
                'jobApplications as offer_sent_count' => function ($query) use ($jobIds) {
                    $query->whereIn('job_id', $jobIds)->where('status', 'offer_sent');
                }
            ]);

        // Apply sorting
        if ($sortBy === 'name') {
            $query->join('users', 'candidates.user_id', '=', 'users.id')
                ->orderBy('users.first_name', $sortOrder)
                ->orderBy('users.last_name', $sortOrder)
                ->select('candidates.*');
        } elseif ($sortBy === 'applications') {
            $query->withCount(['jobApplications' => function ($query) use ($jobIds) {
                $query->whereIn('job_id', $jobIds);
            }])->orderBy('job_applications_count', $sortOrder);
        } else {
            $query->orderBy($sortBy, $sortOrder);
        }

        // Get paginated results
        $candidates = $query->paginate($perPage);

        // Return both the paginated candidates and the counts
        return [
            'candidates' => $candidates,
            'counts' => [
                'total' => $totalApplications,
                'unsorted' => $unsortedCount,
                'sorted' => $sortedCount,
                'shortlisted' => $shortlistedCount,
                'offer_sent' => $offerSentCount,
            ]
        ];
    }

    /**
     * Add candidates to multiple pools
     *
     * @param array $poolIds
     * @param array $candidateIds
     * @param string|null $notes
     * @param Employer $employer
     * @return array
     * @throws Exception
     */
    public function addCandidatesToMultiplePools(array $poolIds, array $candidateIds, ?string $notes = null, Employer $employer): array
    {
        $results = [
            'success' => [],
            'failed' => [],
            'pools' => []
        ];

        // Verify all pools belong to the employer
        $pools = $employer->candidatePools()->whereIn('id', $poolIds)->get();

        if ($pools->count() !== count($poolIds)) {
            throw new Exception('One or more pools do not belong to this employer');
        }

        // Process each pool
        foreach ($pools as $pool) {
            $poolResult = $this->addCandidatesToPool($pool, $candidateIds, $notes);

            // Track results for this pool
            $results['pools'][] = [
                'pool_id' => $pool->id,
                'pool_name' => $pool->name,
                'success_count' => count($poolResult['success']),
                'failed_count' => count($poolResult['failed'])
            ];

            // Merge success and failed results
            $results['success'] = array_merge($results['success'], $poolResult['success']);
            $results['failed'] = array_merge($results['failed'], $poolResult['failed']);
        }

        // Remove duplicates from success array
        $results['success'] = array_unique($results['success']);

        return $results;
    }

    /**
     * Remove candidates from multiple pools
     *
     * @param array $poolIds
     * @param array $candidateIds
     * @param Employer $employer
     * @return array
     * @throws Exception
     */
    public function removeCandidatesFromMultiplePools(array $poolIds, array $candidateIds, Employer $employer): array
    {
        $results = [
            'success' => [],
            'failed' => [],
            'pools' => []
        ];

        // Verify all pools belong to the employer
        $pools = $employer->candidatePools()->whereIn('id', $poolIds)->get();

        if ($pools->count() !== count($poolIds)) {
            throw new Exception('One or more pools do not belong to this employer');
        }

        // Process each pool
        foreach ($pools as $pool) {
            $poolResult = $this->removeCandidatesFromPool($pool, $candidateIds);

            // Track results for this pool
            $results['pools'][] = [
                'pool_id' => $pool->id,
                'pool_name' => $pool->name,
                'success_count' => count($poolResult['success']),
                'failed_count' => count($poolResult['failed'])
            ];

            // Merge success and failed results
            $results['success'] = array_merge($results['success'], $poolResult['success']);
            $results['failed'] = array_merge($results['failed'], $poolResult['failed']);
        }

        // Remove duplicates from success array
        $results['success'] = array_unique($results['success']);

        return $results;
    }

    /**
     * Add multiple candidates to pool
     *
     * @param CandidatePool $pool
     * @param array $candidateIds
     * @param string|null $notes
     * @return array
     * @throws Exception
     */
    public function addCandidatesToPool(CandidatePool $pool, array $candidateIds, ?string $notes = null): array
    {
        $results = [
            'success' => [],
            'failed' => []
        ];

        // Get employer
        $employer = $pool->employer;

        // Get all job IDs for this employer
        $employerJobIds = $employer->jobs()->pluck('id')->toArray();

        foreach ($candidateIds as $candidateId) {
            try {
                $candidate = Candidate::query()->findOrFail($candidateId);

                // Check if candidate is already in the pool
                if ($pool->candidates()->where('candidate_id', $candidate->id)->exists()) {
                    $results['failed'][] = [
                        'candidate_id' => $candidate->id,
                        'reason' => 'Candidate is already in this pool'
                    ];
                    continue;
                }

                // Check if candidate has applied to any of the employer's jobs
                $hasApplied = JobApplication::whereIn('job_id', $employerJobIds)
                    ->where('candidate_id', $candidate->id)
                    ->exists();

                if (!$hasApplied) {
                    $results['failed'][] = [
                        'candidate_id' => $candidate->id,
                        'reason' => 'Candidate has not applied to any of your job postings'
                    ];
                    continue;
                }

                $pool->candidates()->attach($candidate->id, ['notes' => $notes]);
                $results['success'][] = $candidate->id;
            } catch (Exception $e) {
                $results['failed'][] = [
                    'candidate_id' => $candidateId,
                    'reason' => $e->getMessage()
                ];
            }
        }

        return $results;
    }


    /**
     * Remove multiple candidates from pool
     *
     * @param CandidatePool $pool
     * @param array $candidateIds
     * @return array
     */
    public function removeCandidatesFromPool(CandidatePool $pool, array $candidateIds): array
    {
        $results = [
            'success' => [],
            'failed' => []
        ];

        foreach ($candidateIds as $candidateId) {
            try {
                $candidate = Candidate::query()->findOrFail($candidateId);

                // Check if candidate is in the pool
                if (!$pool->candidates()->where('candidate_id', $candidate->id)->exists()) {
                    $results['failed'][] = [
                        'candidate_id' => $candidate->id,
                        'reason' => 'Candidate is not in this pool'
                    ];
                    continue;
                }

                $pool->candidates()->detach($candidate->id);
                $results['success'][] = $candidate->id;
            } catch (Exception $e) {
                $results['failed'][] = [
                    'candidate_id' => $candidateId,
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
     * @return array
     */
    public function getEmployerJobs(
        Employer $employer,
        array $filters = [],
        string $sortBy = 'created_at',
        string $sortOrder = 'desc',
        int $perPage = 15
    ): array {
        // Get job counts for different statuses
        $totalJobs = $employer->jobs()->count();
        $openJobs = $employer->jobs()->where('is_active', true)->where('is_draft', false)->count();
        $closedJobs = $employer->jobs()->where('is_active', false)->where('is_draft', false)->count();
        $draftJobs = $employer->jobs()->where('is_draft', true)->count();

        // Build the query
        $query = $employer->jobs();

        // Apply filters if provided
        if (isset($filters['status'])) {
            $status = $filters['status'];
            if ($status === 'open') {
                $query->where('is_active', true)->where('is_draft', false);
            } elseif ($status === 'close') {
                $query->where('is_active', false)->where('is_draft', false);
            } elseif ($status === 'draft') {
                $query->where('is_draft', true);
            }
            // If status is 'all' or not recognized, don't apply any filter
        }

        if (isset($filters['featured'])) {
            $featured = $filters['featured'];
            $query->where('is_featured', $featured === 'true');
        }

        // Eager load relationships
        $query->with([
            'category',
            'applications.candidate.user',
            'employer.candidatePools'
        ])->withCount('applications');

        // Apply sorting
        $query->orderBy($sortBy, $sortOrder);

        // Get paginated results
        $jobs = $query->paginate($perPage);

        // Return both the paginated jobs and the counts
        return [
            'jobs' => $jobs,
            'counts' => [
                'total' => $totalJobs,
                'open' => $openJobs,
                'closed' => $closedJobs,
                'draft' => $draftJobs
            ]
        ];
    }

    /**
     * Get a specific job for an employer with only its own applications
     *
     * @param Employer $employer
     * @param int $jobId
     * @return Job
     * @throws ModelNotFoundException
     */
    public function getEmployerJob(Employer $employer, int $jobId): Job
    {
        $job = $employer->jobs()
            ->with([
                'category',
                'applications' => function($query) {
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
                                'profileViewCounts'
                            ]);
                        },
                        'resume'
                    ]);
                },
                'employer.candidatePools'
            ])
            ->withCount('applications')
            ->findOrFail($jobId);

        return $job;
    }

    /**
     * Create a new job for an employer
     *
     * @param Employer $employer
     * @param array $data
     * @return Job
     * @throws Exception
     */
    public function createEmployerJob(Employer $employer, array $data): Job
    {
        return $this->jobService->createJob($employer, $data);
    }

    /**
     * Update an employer's job
     *
     * @param Employer $employer
     * @param int $jobId
     * @param array $data
     * @return Job
     * @throws ModelNotFoundException|Exception
     */
    public function updateEmployerJob(Employer $employer, int $jobId, array $data): Job
    {
        $job = $employer->jobs()->findOrFail($jobId);
        return $this->jobService->updateJob($job, $data);
    }

    /**
     * Delete an employer's job
     *
     * @param Employer $employer
     * @param int $jobId
     * @return bool
     * @throws ModelNotFoundException
     */
    public function deleteEmployerJob(Employer $employer, int $jobId): bool
    {
        $job = $employer->jobs()->findOrFail($jobId);
        return $this->jobService->deleteJob($job);
    }

    /**
     * Set job open/close status
     *
     * @param Employer $employer
     * @param int $jobId
     * @param bool $isActive
     * @return Job
     * @throws ModelNotFoundException|Exception
     */
    public function setJobStatus(Employer $employer, int $jobId, bool $isActive): Job
    {
        $job = $employer->jobs()->findOrFail($jobId);
        return $this->jobService->setJobStatus($job, $isActive);
    }

    /**
     * Publish job as featured
     *
     * @param Employer $employer
     * @param int $jobId
     * @return Job
     * @throws ModelNotFoundException|Exception
     */
    public function publishJobAsFeatured(Employer $employer, int $jobId): Job
    {
        $job = $employer->jobs()->findOrFail($jobId);
        return $this->jobService->setJobAsFeatured($job);
    }

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

        // Get jobs - don't filter by approval status when showing employer's own jobs
        $jobsQuery = $employer->jobs()
            ->where('is_active', true)
            ->where('is_draft', false)
            ->notExpired()
            ->with(['category']) // Make sure to load the category relationship
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
                    // For public display, we should still use publiclyAvailable
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
        $employer =  $user->employer()->with([
            'notificationTemplates',
            'subscriptions',
            'activeSubscription',
            'applications'
        ])->first();

        if ($employer) {
            $employer->append('company_logo_url');
        }

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
     * @throws \Throwable
     */
    public function updateProfile(User $user, array $data): Employer
    {
        return DB::transaction(function () use ($user, $data) {
            // Handle company logo
            if (isset($data['company_logo']) && $data['company_logo'] instanceof UploadedFile) {
                if ($user->employer->company_logo) {
                    $this->storageService->delete($user->employer->company_logo);
                }

                $logoPath = $this->uploadImage(
                    $data['company_logo'],
                    config('filestorage.paths.company_logos')
                );
                $data['company_logo'] = $logoPath;
            }

            Log::info('Updating user data');

            if (isset($data['first_name'])) $user->first_name = $data['first_name'];
            if (isset($data['last_name'])) $user->last_name = $data['last_name'];
            if (isset($data['title'])) $user->title = $data['title'];
            if (isset($data['email'])) $user->email = $data['email'];
            if (isset($data['phone'])) $user->phone = $data['phone'];
            if (isset($data['phone_country_code'])) $user->phone_country_code = $data['phone_country_code'];
            if (isset($data['country'])) $user->country = $data['country'];
            if (isset($data['state'])) $user->state = $data['state'];
            if (isset($data['city'])) $user->city = $data['city'];

            $user->save();

            $employer = $user->employer;

            if (isset($data['company_name'])) $employer->company_name = $data['company_name'];
            if (isset($data['company_email'])) $employer->company_email = $data['company_email'];
            if (isset($data['company_logo'])) $employer->company_logo = $data['company_logo'];
            if (isset($data['company_description'])) $employer->company_description = $data['company_description'];
            if (isset($data['company_industry'])) $employer->company_industry = $data['company_industry'];
            if (isset($data['company_size'])) $employer->company_size = $data['company_size'];
            if (isset($data['company_founded'])) $employer->company_founded = $data['company_founded'];
            if (isset($data['company_country'])) $employer->company_country = $data['company_country'];
            if (isset($data['company_state'])) $employer->company_state = $data['company_state'];
            if (isset($data['company_address'])) $employer->company_address = $data['company_address'];
            if (isset($data['company_phone_number'])) $employer->company_phone_number = $data['company_phone_number'];
            if (isset($data['company_website'])) $employer->company_website = $data['company_website'];
            if (isset($data['company_benefits'])) $employer->company_benefits = $data['company_benefits'];
            if (isset($data['company_linkedin_url'])) $employer->company_linkedin_url = $data['company_linkedin_url'];
            if (isset($data['company_twitter_url'])) $employer->company_twitter_url = $data['company_twitter_url'];
            if (isset($data['company_facebook_url'])) $employer->company_facebook_url = $data['company_facebook_url'];

            $employer->save();
            $employer->refresh();
            return $employer->load([
                'notificationTemplates',
                'subscriptions',
                'activeSubscription',
            ]);
        });
    }

//
//    /**
//     * Upload company logo
//     *
//     * @param Employer $employer
//     * @param UploadedFile $file
//     * @return Employer
//     */
//    public function uploadLogo(Employer $employer, UploadedFile $logo): Employer
//    {
//        // Delete old logo if exists
//        if ($employer->company_logo) {
//            $this->storageService->delete($employer->company_logo);
//        }
//
//        // Store new logo
//        $path = $this->uploadImage(
//            $logo,
//            config('filestorage.paths.company_logos')
//        );
//
//        $employer->company_logo = $path;
//        $employer->save();
//
//        return $employer;
//    }

    /**
     * Upload company logo
     *
     * @param Employer $employer
     * @param UploadedFile $file
     * @return Employer
     */
    public function uploadLogo(Employer $employer, array $data): Employer
    {
        return DB::transaction(function () use ($employer, $data) {
            // Handle company logo
            if (isset($data['company_logo']) && $data['company_logo'] instanceof UploadedFile) {
                // Delete old image if exists
                if ($employer->company_logo) {
                    $this->storageService->delete($employer->company_logo);
                }
                $data['company_logo_path'] = $this->uploadImage(
                    $data['company_logo'],
                    config('filestorage.paths.company_logos')
                );
                unset($data['company_logo']);
            }

            if (isset($data['company_logo_path'])) {
                $employerData['company_logo'] = $data['company_logo_path'];
            }

            // Update blog post
            $employer->update($employerData);

            return $employer;
        });
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

//        if (!$subscription || !$subscription->plan->can_create_candidate_pools) {
//            throw new Exception('Your subscription does not allow creating candidate pools');
//        }

        return $employer->candidatePools()->create([
            'name' => $name,
            'description' => $description,
        ])->load('employer.user');
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

//    /**
//     * Add multiple candidates to pool
//     *
//     * @param CandidatePool $pool
//     * @param array $candidateIds
//     * @param string|null $notes
//     * @return array
//     * @throws Exception
//     */
//    public function addCandidatesToPool(CandidatePool $pool, array $candidateIds, ?string $notes = null): array
//    {
//        $results = [
//            'success' => [],
//            'failed' => []
//        ];
//
//        foreach ($candidateIds as $candidateId) {
//            try {
//                $candidate = Candidate::query()->findOrFail($candidateId);
//
//                // Check if candidate is already in the pool
//                if ($pool->candidates()->where('candidate_id', $candidate->id)->exists()) {
//                    $results['failed'][] = [
//                        'candidate_id' => $candidate->id,
//                        'reason' => 'Candidate is already in this pool'
//                    ];
//                    continue;
//                }
//
//                $pool->candidates()->attach($candidate->id, ['notes' => $notes]);
//                $results['success'][] = $candidate->id;
//            } catch (Exception $e) {
//                $results['failed'][] = [
//                    'candidate_id' => $candidateId,
//                    'reason' => $e->getMessage()
//                ];
//            }
//        }
//
//        return $results;
//    }

//    /**
//     * Remove multiple candidates from pool
//     *
//     * @param CandidatePool $pool
//     * @param array $candidateIds
//     * @return array
//     */
//    public function removeCandidatesFromPool(CandidatePool $pool, array $candidateIds): array
//    {
//        $results = [
//            'success' => [],
//            'failed' => []
//        ];
//
//        foreach ($candidateIds as $candidateId) {
//            try {
//                $candidate = Candidate::findOrFail($candidateId);
//
//                // Check if candidate is in the pool
//                if (!$pool->candidates()->where('candidate_id', $candidate->id)->exists()) {
//                    $results['failed'][] = [
//                        'candidate_id' => $candidate->id,
//                        'reason' => 'Candidate is not in this pool'
//                    ];
//                    continue;
//                }
//
//                $pool->candidates()->detach($candidate->id);
//                $results['success'][] = $candidate->id;
//            } catch (Exception $e) {
//                $results['failed'][] = [
//                    'candidate_id' => $candidateId,
//                    'reason' => $e->getMessage()
//                ];
//            }
//        }
//
//        return $results;
//    }

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

    /**
     * Upload an image to storage.
     *
     * @param UploadedFile $image The image file to upload.
     * @param string $path The storage path.
     * @param string $fileName The name to store the file as.
     * @param array $options Additional options for the upload.
     * @return string The path to the uploaded image.
     */
    private function uploadImage(UploadedFile $image, string $path, &$fileName = null, array $options = []): string
    {
        // Generate a filename based on the current timestamp and a random string
        $fileName = time() . '-' . Str::slug(pathinfo($image->getClientOriginalName(), PATHINFO_FILENAME)) . '.' . $image->getClientOriginalExtension();

        return $this->storageService->upload($image, $path, $fileName, $options);
    }

//    /**
//     * Upload an image to storage.
//     *
//     * @param UploadedFile $image The image file to upload.
//     * @param string $path The storage path.
//     * @param array $options Additional options for the upload.
//     * @return string The path to the uploaded image.
//     */
//    private function uploadImage(UploadedFile $image, string $path, array $options = []): string
//    {
//        return $this->storageService->upload($image, $path, $options);
//    }
}
