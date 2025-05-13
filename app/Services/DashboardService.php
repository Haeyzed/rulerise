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

        return [
            'active_jobs_count' => $activeJobsCount,
            'new_messages_count' => $newMessagesCount,
            'new_candidates_count' => $newCandidatesCount,
            'job_views_data' => $jobViewsData,
            'job_applications_data' => $jobApplicationsData,
            'recent_job_updates' => $recentJobUpdates,
        ];
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

        // Get daily/weekly/monthly views for chart based on date range
        $groupBy = 'DATE(created_at)'; // Default daily grouping
        $period = $daysDiff <= 7 ? 'week' : ($daysDiff <= 31 ? 'month' : 'year');

        // Adjust grouping based on period
        if ($period === 'month') {
            // For monthly view (30 days), group by day but format as "May" (month name)
            $groupBy = 'DATE(created_at)';
        } else if ($period === 'year') {
            // For yearly view (365 days), group by day but format as "May 7" (month + day)
            $groupBy = 'DATE(created_at)';
        }

        $viewsData = JobViewCount::query()->whereHas('job', function ($query) use ($employer) {
            $query->where('employer_id', $employer->id);
        })
            ->whereBetween('created_at', [$startDate, $endDate])
            ->select(DB::raw($groupBy . ' as date_group'), DB::raw('COUNT(*) as count'))
            ->groupBy('date_group')
            ->orderBy('date_group')
            ->get();

        // Format the results based on grouping
        $formattedData = [];

        if ($period === 'week') {
            // Weekly view - show days of week (Wed, Thu, Fri, etc.)
            $currentDate = clone $startDate;
            while ($currentDate <= $endDate) {
                $dateString = $currentDate->format('D'); // Day name (Wed, Thu, etc.)
                $formattedData[$dateString] = 0;
                $currentDate->addDay();
            }

            foreach ($viewsData as $item) {
                $date = Carbon::parse($item->date_group);
                $dateString = $date->format('D');
                $formattedData[$dateString] = $item->count;
            }
        } else if ($period === 'month') {
            // Monthly view - show month names (May, May, etc.)
            $currentDate = clone $startDate;
            while ($currentDate <= $endDate) {
                $dateString = $currentDate->format('M'); // Month name (May, Jun, etc.)
                $formattedData[$dateString] = 0;
                $currentDate->addDay();
            }

            foreach ($viewsData as $item) {
                $date = Carbon::parse($item->date_group);
                $dateString = $date->format('M');
                if (isset($formattedData[$dateString])) {
                    $formattedData[$dateString] += $item->count;
                }
            }
        } else {
            // Yearly view - show specific dates (May 7, May 8, etc.)
            $currentDate = clone $startDate;
            while ($currentDate <= $endDate) {
                $dateString = $currentDate->format('M j'); // May 7, May 8, etc.
                $formattedData[$dateString] = 0;
                $currentDate->addDay();
            }

            foreach ($viewsData as $item) {
                $date = Carbon::parse($item->date_group);
                $dateString = $date->format('M j');
                $formattedData[$dateString] = $item->count;
            }
        }

        return [
            'total' => $totalViews,
            'percentage_change' => round($percentageChange, 1),
            'daily_data' => $formattedData,
            'period' => $period
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

        // Get daily/weekly/monthly applications for chart based on date range
        $groupBy = 'DATE(created_at)'; // Default daily grouping
        $period = $daysDiff <= 7 ? 'week' : ($daysDiff <= 31 ? 'month' : 'year');

        // Adjust grouping based on period
        if ($period === 'month') {
            // For monthly view (30 days), group by day but format as "May" (month name)
            $groupBy = 'DATE(created_at)';
        } else if ($period === 'year') {
            // For yearly view (365 days), group by day but format as "May 7" (month + day)
            $groupBy = 'DATE(created_at)';
        }

        $applicationsData = JobApplication::query()->whereHas('job', function ($query) use ($employer) {
            $query->where('employer_id', $employer->id);
        })
            ->whereBetween('created_at', [$startDate, $endDate])
            ->select(DB::raw($groupBy . ' as date_group'), DB::raw('COUNT(*) as count'))
            ->groupBy('date_group')
            ->orderBy('date_group')
            ->get();

        // Format the results based on grouping
        $formattedData = [];

        if ($period === 'week') {
            // Weekly view - show days of week (Wed, Thu, Fri, etc.)
            $currentDate = clone $startDate;
            while ($currentDate <= $endDate) {
                $dateString = $currentDate->format('D'); // Day name (Wed, Thu, etc.)
                $formattedData[$dateString] = 0;
                $currentDate->addDay();
            }

            foreach ($applicationsData as $item) {
                $date = Carbon::parse($item->date_group);
                $dateString = $date->format('D');
                $formattedData[$dateString] = $item->count;
            }
        } else if ($period === 'month') {
            // Monthly view - show month names (May, May, etc.)
            $currentDate = clone $startDate;
            while ($currentDate <= $endDate) {
                $dateString = $currentDate->format('M'); // Month name (May, Jun, etc.)
                $formattedData[$dateString] = 0;
                $currentDate->addDay();
            }

            foreach ($applicationsData as $item) {
                $date = Carbon::parse($item->date_group);
                $dateString = $date->format('M');
                if (isset($formattedData[$dateString])) {
                    $formattedData[$dateString] += $item->count;
                }
            }
        } else {
            // Yearly view - show specific dates (May 7, May 8, etc.)
            $currentDate = clone $startDate;
            while ($currentDate <= $endDate) {
                $dateString = $currentDate->format('M j'); // May 7, May 8, etc.
                $formattedData[$dateString] = 0;
                $currentDate->addDay();
            }

            foreach ($applicationsData as $item) {
                $date = Carbon::parse($item->date_group);
                $dateString = $date->format('M j');
                $formattedData[$dateString] = $item->count;
            }
        }

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
            'daily_data' => $formattedData,
            'status_counts' => $statusCounts,
            'period' => $period
        ];
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
