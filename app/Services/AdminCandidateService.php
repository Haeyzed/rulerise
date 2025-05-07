<?php

namespace App\Services;

use App\Models\Candidate;
use App\Models\JobApplication;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class AdminCandidateService
{
    /**
     * Get a paginated list of candidates with filters
     *
     * @param array $filters
     * @return LengthAwarePaginator
     */
    public function getCandidates(array $filters = []): LengthAwarePaginator
    {
        $query = Candidate::with(['user'])
            ->withCount('jobApplications');

        // Apply search filter
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->whereHas('user', function($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Apply status filter
        if (isset($filters['status'])) {
            if ($filters['status'] === 'active') {
                $query->whereHas('user', function($q) {
                    $q->where('is_active', true)
                        ->where('is_shadow_banned', false);
                });
            } elseif ($filters['status'] === 'inactive') {
                $query->whereHas('user', function($q) {
                    $q->where('is_active', false)
                        ->where('is_shadow_banned', false);
                });
            } elseif ($filters['status'] === 'blacklisted') {
                $query->whereHas('user', function($q) {
                    $q->where('is_shadow_banned', true);
                });
            }
        }

        // Apply job applications filter
        if (isset($filters['applications_count'])) {
            $count = (int) $filters['applications_count'];
            $query->has('jobApplications', '>=', $count);
        }

        // Apply date range filter
        if (!empty($filters['date_from']) && !empty($filters['date_to'])) {
            $query->whereHas('user', function($q) use ($filters) {
                $q->whereBetween('created_at', [$filters['date_from'], $filters['date_to']]);
            });
        } elseif (!empty($filters['date_from'])) {
            $query->whereHas('user', function($q) use ($filters) {
                $q->where('created_at', '>=', $filters['date_from']);
            });
        } elseif (!empty($filters['date_to'])) {
            $query->whereHas('user', function($q) use ($filters) {
                $q->where('created_at', '<=', $filters['date_to']);
            });
        }

        // Apply sorting
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortOrder = $filters['sort_order'] ?? 'desc';

        if ($sortBy === 'name') {
            $query->join('users', 'candidates.user_id', '=', 'users.id')
                ->orderBy('users.first_name', $sortOrder)
                ->orderBy('users.last_name', $sortOrder)
                ->select('candidates.*');
        } elseif ($sortBy === 'email') {
            $query->join('users', 'candidates.user_id', '=', 'users.id')
                ->orderBy('users.email', $sortOrder)
                ->select('candidates.*');
        } elseif ($sortBy === 'applications_count') {
            $query->withCount('jobApplications')
                ->orderBy('job_applications_count', $sortOrder);
        } else {
            // Default to ordering by user's created_at
            $query->join('users', 'candidates.user_id', '=', 'users.id')
                ->orderBy('users.created_at', $sortOrder)
                ->select('candidates.*');
        }

        // Paginate results
        $perPage = $filters['per_page'] ?? config('app.pagination.per_page', 15);

        return $query->paginate($perPage);
    }

    /**
     * Get candidate profile details
     *
     * @param int $candidateId
     * @return array
     */
    public function getCandidateProfile(int $candidateId): array
    {
        $candidate = Candidate::with([
            'user',
            'qualification',
            'workExperiences' => function($query) {
                $query->orderBy('start_date', 'desc');
            },
            'educationHistories' => function($query) {
                $query->orderBy('start_date', 'desc');
            },
            'languages',
            'portfolio',
            'credentials',
            'resumes' => function($query) {
                $query->orderBy('is_primary', 'desc');
            },
        ])->findOrFail($candidateId);

        // Get application statistics
        $totalApplications = $candidate->jobApplications()->count();
        $hiredCount = $candidate->jobApplications()->where('status', 'hired')->count();

        return [
            'candidate' => $candidate,
            'statistics' => [
                'total_applications' => $totalApplications,
                'hired_count' => $hiredCount,
                'profile_views' => $candidate->profileViewCounts()->count(),
                'saved_jobs' => $candidate->savedJobs()->count(),
                'reported_jobs' => $candidate->reportedJobs()->count(),
            ]
        ];
    }

    /**
     * Get candidate job applications with filters
     *
     * @param int $candidateId
     * @param array $filters
     * @return LengthAwarePaginator
     */
    public function getCandidateApplications(int $candidateId, array $filters = []): LengthAwarePaginator
    {
        $query = JobApplication::where('candidate_id', $candidateId)
            ->with([
                'job' => function($query) {
                    $query->with('employer');
                },
                'resume'
            ]);

        // Apply search filter
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->whereHas('job', function($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('location', 'like', "%{$search}%")
                    ->orWhereHas('employer', function($q) use ($search) {
                        $q->where('company_name', 'like', "%{$search}%");
                    });
            });
        }

        // Apply status filter
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
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

        if ($sortBy === 'job_title') {
            $query->join('job_listings', 'job_applications.job_id', '=', 'job_listings.id')
                ->orderBy('job_listings.title', $sortOrder)
                ->select('job_applications.*');
        } elseif ($sortBy === 'company') {
            $query->join('job_listings', 'job_applications.job_id', '=', 'job_listings.id')
                ->join('employers', 'job_listings.employer_id', '=', 'employers.id')
                ->orderBy('employers.company_name', $sortOrder)
                ->select('job_applications.*');
        } else {
            $query->orderBy($sortBy, $sortOrder);
        }

        // Paginate results
        $perPage = $filters['per_page'] ?? config('app.pagination.per_page', 15);

        return $query->paginate($perPage);
    }

    /**
     * Delete a candidate
     *
     * @param int $candidateId
     * @return bool
     */
    public function deleteCandidate(int $candidateId): bool
    {
        $candidate = Candidate::findOrFail($candidateId);
        $user = $candidate->user;

        // Begin transaction to ensure both candidate and user are deleted
        return \DB::transaction(function() use ($candidate, $user) {
            // Delete the candidate
            $candidate->delete();

            // Soft delete the user
            $user->delete();

            return true;
        });
    }

    /**
     * Moderate candidate account status (activate/deactivate)
     *
     * @param int $candidateId
     * @param bool $isActive
     * @return User
     */
    public function moderateCandidateAccountStatus(int $candidateId, bool $isActive): User
    {
        $candidate = Candidate::findOrFail($candidateId);
        $user = $candidate->user;

        // Update user active status
        $user->is_active = $isActive;
        $user->save();

        return $user;
    }

    /**
     * Set shadow ban status for a candidate
     *
     * @param int $candidateId
     * @param bool $isShadowBanned
     * @return User
     */
    public function setShadowBanForCandidate(int $candidateId, bool $isShadowBanned): User
    {
        $candidate = Candidate::findOrFail($candidateId);
        $user = $candidate->user;

        // Update user shadow ban status
        $user->is_shadow_banned = $isShadowBanned;
        $user->save();

        return $user;
    }
}
