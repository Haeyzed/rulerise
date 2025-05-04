<?php

namespace App\Services;

use App\Models\Candidate;
use App\Models\Employer;
use App\Models\Job;
use App\Models\JobApplication;
use App\Models\JobViewCount;
use App\Models\Message;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    /**
     * Get dashboard metrics for an employer
     *
     * @param Employer $employer
     * @param array $dateRange
     * @return array
     */
    public function getEmployerDashboardMetrics(Employer $employer, array $dateRange): array
    {
        $startDate = Carbon::parse($dateRange['start_date']);
        $endDate = Carbon::parse($dateRange['end_date']);

        // Get active jobs count
        $activeJobsCount = $this->getActiveJobsCount($employer);

        // Get new messages count
        $newMessagesCount = $this->getNewMessagesCount($employer->user, $startDate, $endDate);

        // Get new candidates count
        $newCandidatesCount = $this->getNewCandidatesCount($employer, $startDate, $endDate);

        // Get job views data
        $jobViewsData = $this->getJobViewsData($employer, $startDate, $endDate);

        // Get job applications data
        $jobApplicationsData = $this->getJobApplicationsData($employer, $startDate, $endDate);

        // Get recent job updates
        $recentJobUpdates = $this->getRecentJobUpdates($employer);

        // Determine the period based on date range
        $period = $this->determinePeriodFromDateRange($startDate, $endDate);

        return [
            'active_jobs_count' => $activeJobsCount,
            'new_messages_count' => $newMessagesCount,
            'new_candidates_count' => $newCandidatesCount,
            'job_views_data' => $jobViewsData,
            'job_applications_data' => $jobApplicationsData,
            'recent_job_updates' => $recentJobUpdates,
            'period' => $period,
        ];
    }

    /**
     * Determine the period based on date range
     *
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @return string
     */
    private function determinePeriodFromDateRange(Carbon $startDate, Carbon $endDate): string
    {
        $diffInDays = $startDate->diffInDays($endDate);

        if ($diffInDays <= 7) {
            return 'week';
        } elseif ($diffInDays <= 31) {
            return 'month';
        } else {
            return 'year';
        }
    }

    /**
     * Get active jobs count for an employer
     *
     * @param Employer $employer
     * @return int
     */
    private function getActiveJobsCount(Employer $employer): int
    {
        return $employer->jobs()
            ->where('is_active', true)
            ->where('is_draft', false)
            ->count();
    }

    /**
     * Get new messages count for a user
     *
     * @param User $user
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @return int
     */
    private function getNewMessagesCount(User $user, Carbon $startDate, Carbon $endDate): int
    {
        return Message::query()->where('receiver_id', $user->id)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();
    }

    /**
     * Get new candidates count for an employer
     *
     * @param Employer $employer
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @return int
     */
    private function getNewCandidatesCount(Employer $employer, Carbon $startDate, Carbon $endDate): int
    {
        return JobApplication::query()->whereHas('job', function ($query) use ($employer) {
            $query->where('employer_id', $employer->id);
        })
            ->whereBetween('created_at', [$startDate, $endDate])
            ->distinct('candidate_id')
            ->count('candidate_id');
    }

    /**
     * Get job views data for an employer
     *
     * @param Employer $employer
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @return array
     */
    private function getJobViewsData(Employer $employer, Carbon $startDate, Carbon $endDate): array
    {
        // Get total job views for the date range
        $totalViews = JobViewCount::query()->whereHas('job', function ($query) use ($employer) {
            $query->where('employer_id', $employer->id);
        })
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();

        // Get previous period for comparison
        $daysDiff = $startDate->diffInDays($endDate);
        $previousStartDate = (clone $startDate)->subDays($daysDiff + 1);
        $previousEndDate = (clone $startDate)->subDay();

        $previousTotalViews = JobViewCount::whereHas('job', function ($query) use ($employer) {
            $query->where('employer_id', $employer->id);
        })
            ->whereBetween('created_at', [$previousStartDate, $previousEndDate])
            ->count();

        // Calculate percentage change
        $percentageChange = 0;
        if ($previousTotalViews > 0) {
            $percentageChange = (($totalViews - $previousTotalViews) / $previousTotalViews) * 100;
        }

        // Get appropriate grouping format based on date range
        $groupFormat = $this->getGroupingFormat($startDate, $endDate);

        // Get grouped views for chart
        $groupedViews = JobViewCount::query()->whereHas('job', function ($query) use ($employer) {
            $query->where('employer_id', $employer->id);
        })
            ->whereBetween('created_at', [$startDate, $endDate])
            ->select(DB::raw("DATE_FORMAT(created_at, '{$groupFormat}') as date"), DB::raw('COUNT(*) as count'))
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->mapWithKeys(function ($item) {
                return [$item->date => $item->count];
            });

        // Fill in missing dates with zero counts
        $result = $this->fillMissingDates($startDate, $endDate, $groupedViews, $groupFormat);

        return [
            'total' => $totalViews,
            'percentage_change' => round($percentageChange, 1),
            'daily_data' => $result,
        ];
    }

    /**
     * Get job applications data for an employer
     *
     * @param Employer $employer
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @return array
     */
    private function getJobApplicationsData(Employer $employer, Carbon $startDate, Carbon $endDate): array
    {
        // Get total job applications for the date range
        $totalApplications = JobApplication::query()->whereHas('job', function ($query) use ($employer) {
            $query->where('employer_id', $employer->id);
        })
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();

        // Get previous period for comparison
        $daysDiff = $startDate->diffInDays($endDate);
        $previousStartDate = (clone $startDate)->subDays($daysDiff + 1);
        $previousEndDate = (clone $startDate)->subDay();

        $previousTotalApplications = JobApplication::query()->whereHas('job', function ($query) use ($employer) {
            $query->where('employer_id', $employer->id);
        })
            ->whereBetween('created_at', [$previousStartDate, $previousEndDate])
            ->count();

        // Calculate percentage change
        $percentageChange = 0;
        if ($previousTotalApplications > 0) {
            $percentageChange = (($totalApplications - $previousTotalApplications) / $previousTotalApplications) * 100;
        }

        // Get appropriate grouping format based on date range
        $groupFormat = $this->getGroupingFormat($startDate, $endDate);

        // Get grouped applications for chart
        $groupedApplications = JobApplication::query()->whereHas('job', function ($query) use ($employer) {
            $query->where('employer_id', $employer->id);
        })
            ->whereBetween('created_at', [$startDate, $endDate])
            ->select(DB::raw("DATE_FORMAT(created_at, '{$groupFormat}') as date"), DB::raw('COUNT(*) as count'))
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->mapWithKeys(function ($item) {
                return [$item->date => $item->count];
            });

        // Fill in missing dates with zero counts
        $result = $this->fillMissingDates($startDate, $endDate, $groupedApplications, $groupFormat);

        // Get application status counts
        $statusCounts = JobApplication::query()->whereHas('job', function ($query) use ($employer) {
            $query->where('employer_id', $employer->id);
        })
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->get()
            ->mapWithKeys(function ($item) {
                return [$item->status => $item->count];
            });

        return [
            'total' => $totalApplications,
            'percentage_change' => round($percentageChange, 1),
            'daily_data' => $result,
            'status_counts' => $statusCounts,
        ];
    }

    /**
     * Get appropriate date format for grouping based on date range
     *
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @return string
     */
    private function getGroupingFormat(Carbon $startDate, Carbon $endDate): string
    {
        $diffInDays = $startDate->diffInDays($endDate);

        if ($diffInDays <= 31) {
            return '%Y-%m-%d'; // Daily format for week and month
        } elseif ($diffInDays <= 365) {
            return '%Y-%m'; // Monthly format for year
        } else {
            return '%Y'; // Yearly format for multi-year
        }
    }

    /**
     * Fill in missing dates with zero counts
     *
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @param Collection $data
     * @param string $groupFormat
     * @return array
     */
    private function fillMissingDates(Carbon $startDate, Carbon $endDate, Collection $data, string $groupFormat): array
    {
        $result = [];
        $currentDate = clone $startDate;

        // Determine the increment method based on the grouping format
        if ($groupFormat === '%Y-%m-%d') {
            $incrementMethod = 'addDay';
            $formatMethod = 'Y-m-d';
        } elseif ($groupFormat === '%Y-%m') {
            $incrementMethod = 'addMonth';
            $formatMethod = 'Y-m';
        } else {
            $incrementMethod = 'addYear';
            $formatMethod = 'Y';
        }

        while ($currentDate <= $endDate) {
            $dateString = $currentDate->format($formatMethod);
            $result[$dateString] = $data[$dateString] ?? 0;
            $currentDate->$incrementMethod();
        }

        return $result;
    }

    /**
     * Get recent job updates for an employer
     *
     * @param Employer $employer
     * @param int $limit
     * @return Collection
     */
    private function getRecentJobUpdates(Employer $employer, int $limit = 5): Collection
    {
        return $employer->jobs()
            ->with(['applications' => function ($query) {
                $query->select('job_id', DB::raw('COUNT(*) as applications_count'))
                    ->groupBy('job_id');
            }])
            ->withCount('applications')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($job) {
                return [
                    'id' => $job->id,
                    'title' => $job->title,
                    'job_type' => $job->job_type,
                    'location' => $job->location,
                    'created_at' => $job->created_at,
                    'applications_count' => $job->applications_count,
                    'vacancies' => $job->vacancies,
                    'capacity_filled' => $job->vacancies > 0
                        ? round(($job->applications_count / $job->vacancies) * 100)
                        : 0,
                ];
            });
    }
}
