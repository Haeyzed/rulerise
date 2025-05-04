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
        $period = $dateRange['period'] ?? $this->determinePeriodFromDateRange($startDate, $endDate);

        // Get active jobs count
        $activeJobsCount = $this->getActiveJobsCount($employer);

        // Get new messages count
        $newMessagesCount = $this->getNewMessagesCount($employer->user, $startDate, $endDate);

        // Get new candidates count
        $newCandidatesCount = $this->getNewCandidatesCount($employer, $startDate, $endDate);

        // Get job views data
        $jobViewsData = $this->getJobViewsData($employer, $startDate, $endDate, $period);

        // Get job applications data
        $jobApplicationsData = $this->getJobApplicationsData($employer, $startDate, $endDate, $period);

        // Get recent job updates
        $recentJobUpdates = $this->getRecentJobUpdates($employer, $startDate, $endDate);

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
     * @param string $period
     * @return array
     */
    private function getJobViewsData(Employer $employer, Carbon $startDate, Carbon $endDate, string $period): array
    {
        // Get total job views for the date range
        $totalViews = JobViewCount::query()->whereHas('job', function ($query) use ($employer) {
            $query->where('employer_id', $employer->id);
        })
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();

        // Get previous period for comparison
        $previousPeriod = $this->getPreviousPeriod($startDate, $endDate, $period);
        $previousStartDate = $previousPeriod['start_date'];
        $previousEndDate = $previousPeriod['end_date'];

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

        // Get appropriate grouping format based on period
        $groupFormat = $this->getGroupingFormat($period);

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
        $result = $this->fillMissingDates($startDate, $endDate, $groupedViews, $period);

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
     * @param string $period
     * @return array
     */
    private function getJobApplicationsData(Employer $employer, Carbon $startDate, Carbon $endDate, string $period): array
    {
        // Get total job applications for the date range
        $totalApplications = JobApplication::query()->whereHas('job', function ($query) use ($employer) {
            $query->where('employer_id', $employer->id);
        })
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();

        // Get previous period for comparison
        $previousPeriod = $this->getPreviousPeriod($startDate, $endDate, $period);
        $previousStartDate = $previousPeriod['start_date'];
        $previousEndDate = $previousPeriod['end_date'];

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

        // Get appropriate grouping format based on period
        $groupFormat = $this->getGroupingFormat($period);

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
        $result = $this->fillMissingDates($startDate, $endDate, $groupedApplications, $period);

        // Get application status counts
        $statusCounts = JobApplication::query()->whereHas('job', function ($query) use ($employer) {
            $query->where('employer_id', $employer->id);
        })
            ->whereBetween('created_at', [$startDate, $endDate])
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
     * Get previous period date range based on current period
     *
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @param string $period
     * @return array
     */
    private function getPreviousPeriod(Carbon $startDate, Carbon $endDate, string $period): array
    {
        $previousStartDate = null;
        $previousEndDate = null;

        switch ($period) {
            case 'week':
                $previousStartDate = (clone $startDate)->subDays(7);
                $previousEndDate = (clone $endDate)->subDays(7);
                break;
            case 'month':
                $previousStartDate = (clone $startDate)->subMonth();
                $previousEndDate = (clone $endDate)->subMonth();
                break;
            case 'year':
                $previousStartDate = (clone $startDate)->subYear();
                $previousEndDate = (clone $endDate)->subYear();
                break;
            default:
                // Default to previous week
                $previousStartDate = (clone $startDate)->subDays(7);
                $previousEndDate = (clone $endDate)->subDays(7);
        }

        return [
            'start_date' => $previousStartDate,
            'end_date' => $previousEndDate,
        ];
    }

    /**
     * Get appropriate date format for grouping based on period
     *
     * @param string $period
     * @return string
     */
    private function getGroupingFormat(string $period): string
    {
        return match ($period) {
            'week' => '%Y-%m-%d', // Daily format for week
            'month' => '%Y-%m-%d', // Daily format for month
            'year' => '%Y-%m', // Monthly format for year
            default => '%Y-%m-%d', // Default to daily format
        };
    }

    /**
     * Fill in missing dates with zero counts
     *
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @param Collection $data
     * @param string $period
     * @return array
     */
    private function fillMissingDates(Carbon $startDate, Carbon $endDate, Collection $data, string $period): array
    {
        $result = [];
        $currentDate = clone $startDate;

        // Determine the increment method and format based on period
        switch ($period) {
            case 'week':
            case 'month':
                $incrementMethod = 'addDay';
                $formatMethod = 'Y-m-d';
                break;
            case 'year':
                $incrementMethod = 'addMonth';
                $formatMethod = 'Y-m';
                break;
            default:
                $incrementMethod = 'addDay';
                $formatMethod = 'Y-m-d';
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
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @param int $limit
     * @return Collection
     */
    private function getRecentJobUpdates(Employer $employer, Carbon $startDate, Carbon $endDate, int $limit = 5): Collection
    {
        return $employer->jobs()
            ->with(['applications' => function ($query) use ($startDate, $endDate) {
                $query->whereBetween('created_at', [$startDate, $endDate])
                    ->select('job_id', DB::raw('COUNT(*) as applications_count'))
                    ->groupBy('job_id');
            }])
            ->withCount(['applications' => function ($query) use ($startDate, $endDate) {
                $query->whereBetween('created_at', [$startDate, $endDate]);
            }])
            ->whereBetween('created_at', [$startDate, $endDate])
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
