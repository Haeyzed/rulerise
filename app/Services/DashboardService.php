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
        $isCustomDateRange = $dateRange['is_custom_date_range'] ?? false;

        // Get active jobs count
        $activeJobsCount = $this->getActiveJobsCount($employer);

        // Get new messages count
        $newMessagesCount = $this->getNewMessagesCount($employer->user, $startDate, $endDate);

        // Get new candidates count
        $newCandidatesCount = $this->getNewCandidatesCount($employer, $startDate, $endDate);

        // Get job views data
        $jobViewsData = $this->getJobViewsData($employer, $startDate, $endDate, $isCustomDateRange);

        // Get job applications data
        $jobApplicationsData = $this->getJobApplicationsData($employer, $startDate, $endDate, $isCustomDateRange);

        // Get recent job updates
        $recentJobUpdates = $this->getRecentJobUpdates($employer);

        // Format date range for display
        $formattedStartDate = $startDate->format('M j, Y');
        $formattedEndDate = $endDate->format('M j, Y');

        return [
            'active_jobs_count' => $activeJobsCount,
            'new_messages_count' => $newMessagesCount,
            'new_candidates_count' => $newCandidatesCount,
            'job_views_data' => $jobViewsData,
            'job_applications_data' => $jobApplicationsData,
            'recent_job_updates' => $recentJobUpdates,
            'date_range' => [
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
                'formatted_start_date' => $formattedStartDate,
                'formatted_end_date' => $formattedEndDate,
                'display' => "$formattedStartDate - $formattedEndDate",
                'is_custom_date_range' => $isCustomDateRange
            ]
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
     * @param bool $isCustomDateRange
     * @return array
     */
    private function getJobViewsData(Employer $employer, Carbon $startDate, Carbon $endDate, bool $isCustomDateRange = false): array
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

        // For custom date range, always group by day with YYYY-MM-DD format
        if ($isCustomDateRange) {
            $groupBy = 'DATE(created_at)';
            $period = 'custom';
        } else {
            // Get daily/weekly/monthly views for chart based on date range
            $period = $daysDiff <= 7 ? 'week' : ($daysDiff <= 31 ? 'month' : 'year');

            // Adjust grouping based on period
            if ($period === 'week') {
                // For weekly view, group by day
                $groupBy = 'DATE(created_at)';
            } else if ($period === 'month') {
                // For monthly view, group by week
                $groupBy = 'YEARWEEK(created_at)';
            } else {
                // For yearly view, group by month
                $groupBy = 'MONTH(created_at)';
            }
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

        if ($isCustomDateRange) {
            // Custom date range - use exact dates in YYYY-MM-DD format
            $currentDate = clone $startDate;
            while ($currentDate <= $endDate) {
                $dateString = $currentDate->format('Y-m-d');
                $formattedData[$dateString] = 0;
                $currentDate->addDay();
            }

            foreach ($viewsData as $item) {
                $dateString = $item->date_group;
                $formattedData[$dateString] = $item->count;
            }
        } else if ($period === 'week') {
            // Weekly view - show shortened days of week (Mon, Tue, Wed, etc.)
            $daysOfWeek = [
                'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'
            ];

            // Initialize all days with zero counts
            foreach ($daysOfWeek as $day) {
                $formattedData[$day] = 0;
            }

            // Fill in actual data
            foreach ($viewsData as $item) {
                $date = Carbon::parse($item->date_group);
                $dayName = $date->format('D'); // Short day name (Mon, Tue, etc.)
                $formattedData[$dayName] = $item->count;
            }
        } else if ($period === 'month') {
            // Monthly view - show weeks with range (Week 1 (May 1-7), Week 2 (May 8-14), etc.)

            // Calculate the number of weeks in the date range
            $startWeek = (clone $startDate)->startOfWeek();
            $endWeek = (clone $endDate)->endOfWeek();
            $weekNumber = 1;

            // Initialize all weeks with zero counts
            $currentWeek = clone $startWeek;
            while ($currentWeek <= $endWeek) {
                $weekStart = (clone $currentWeek)->format('M j'); // Abbreviated month (Jan, Feb, etc.)
                $weekEnd = (clone $currentWeek)->endOfWeek()->format('M j');
                $weekLabel = "Week $weekNumber ($weekStart-$weekEnd)";
                $formattedData[$weekLabel] = 0;
                $currentWeek->addWeek();
                $weekNumber++;
            }

            // Fill in actual data
            foreach ($viewsData as $item) {
                $yearWeek = $item->date_group;
                $year = substr($yearWeek, 0, 4);
                $week = substr($yearWeek, 4);

                // Create a date for this year/week
                $weekDate = Carbon::now()->setISODate($year, $week);
                $weekStart = (clone $weekDate)->startOfWeek()->format('M j'); // Abbreviated month
                $weekEnd = (clone $weekDate)->endOfWeek()->format('M j');

                // Find the week number relative to the start date
                $weeksSinceStart = $weekDate->startOfWeek()->diffInWeeks($startWeek) + 1;
                $weekLabel = "Week $weeksSinceStart ($weekStart-$weekEnd)";

                // If this week is in our formatted data, update the count
                if (isset($formattedData[$weekLabel])) {
                    $formattedData[$weekLabel] = $item->count;
                }
            }
        } else {
            // Yearly view - show abbreviated months (Jan, Feb, etc.)
            $months = [
                'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun',
                'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'
            ];

            // Initialize all months with zero counts
            foreach ($months as $month) {
                $formattedData[$month] = 0;
            }

            // Fill in actual data
            foreach ($viewsData as $item) {
                $monthNumber = $item->date_group;
                $monthName = $months[$monthNumber - 1]; // Convert 1-12 to 0-11 for array index
                $formattedData[$monthName] = $item->count;
            }
        }

        // Format date range for display
        $formattedStartDate = $startDate->format('M j, Y');
        $formattedEndDate = $endDate->format('M j, Y');

        return [
            'total' => $totalViews,
            'percentage_change' => round($percentageChange, 1),
            'daily_data' => $formattedData,
            'period' => $period,
            'date_range' => [
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
                'formatted_start_date' => $formattedStartDate,
                'formatted_end_date' => $formattedEndDate,
                'display' => "$formattedStartDate - $formattedEndDate",
                'is_custom_date_range' => $isCustomDateRange
            ]
        ];
    }

    /**
     * Get job applications data for an employer
     *
     * @param Employer $employer
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @param bool $isCustomDateRange
     * @return array
     */
    private function getJobApplicationsData(Employer $employer, Carbon $startDate, Carbon $endDate, bool $isCustomDateRange = false): array
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

        // For custom date range, always group by day with YYYY-MM-DD format
        if ($isCustomDateRange) {
            $groupBy = 'DATE(created_at)';
            $period = 'custom';
        } else {
            // Get daily/weekly/monthly applications for chart based on date range
            $period = $daysDiff <= 7 ? 'week' : ($daysDiff <= 31 ? 'month' : 'year');

            // Adjust grouping based on period
            if ($period === 'week') {
                // For weekly view, group by day
                $groupBy = 'DATE(created_at)';
            } else if ($period === 'month') {
                // For monthly view, group by week
                $groupBy = 'YEARWEEK(created_at)';
            } else {
                // For yearly view, group by month
                $groupBy = 'MONTH(created_at)';
            }
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

        if ($isCustomDateRange) {
            // Custom date range - use exact dates in YYYY-MM-DD format
            $currentDate = clone $startDate;
            while ($currentDate <= $endDate) {
                $dateString = $currentDate->format('Y-m-d');
                $formattedData[$dateString] = 0;
                $currentDate->addDay();
            }

            foreach ($applicationsData as $item) {
                $dateString = $item->date_group;
                $formattedData[$dateString] = $item->count;
            }
        } else if ($period === 'week') {
            // Weekly view - show shortened days of week (Mon, Tue, Wed, etc.)
            $daysOfWeek = [
                'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'
            ];

            // Initialize all days with zero counts
            foreach ($daysOfWeek as $day) {
                $formattedData[$day] = 0;
            }

            // Fill in actual data
            foreach ($applicationsData as $item) {
                $date = Carbon::parse($item->date_group);
                $dayName = $date->format('D'); // Short day name (Mon, Tue, etc.)
                $formattedData[$dayName] = $item->count;
            }
        } else if ($period === 'month') {
            // Monthly view - show weeks with range (Week 1 (May 1-7), Week 2 (May 8-14), etc.)

            // Calculate the number of weeks in the date range
            $startWeek = (clone $startDate)->startOfWeek();
            $endWeek = (clone $endDate)->endOfWeek();
            $weekNumber = 1;

            // Initialize all weeks with zero counts
            $currentWeek = clone $startWeek;
            while ($currentWeek <= $endWeek) {
                $weekStart = (clone $currentWeek)->format('M j'); // Abbreviated month (Jan, Feb, etc.)
                $weekEnd = (clone $currentWeek)->endOfWeek()->format('M j');
                $weekLabel = "Week $weekNumber ($weekStart-$weekEnd)";
                $formattedData[$weekLabel] = 0;
                $currentWeek->addWeek();
                $weekNumber++;
            }

            // Fill in actual data
            foreach ($applicationsData as $item) {
                $yearWeek = $item->date_group;
                $year = substr($yearWeek, 0, 4);
                $week = substr($yearWeek, 4);

                // Create a date for this year/week
                $weekDate = Carbon::now()->setISODate($year, $week);
                $weekStart = (clone $weekDate)->startOfWeek()->format('M j'); // Abbreviated month
                $weekEnd = (clone $weekDate)->endOfWeek()->format('M j');

                // Find the week number relative to the start date
                $weeksSinceStart = $weekDate->startOfWeek()->diffInWeeks($startWeek) + 1;
                $weekLabel = "Week $weeksSinceStart ($weekStart-$weekEnd)";

                // If this week is in our formatted data, update the count
                if (isset($formattedData[$weekLabel])) {
                    $formattedData[$weekLabel] = $item->count;
                }
            }
        } else {
            // Yearly view - show abbreviated months (Jan, Feb, etc.)
            $months = [
                'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun',
                'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'
            ];

            // Initialize all months with zero counts
            foreach ($months as $month) {
                $formattedData[$month] = 0;
            }

            // Fill in actual data
            foreach ($applicationsData as $item) {
                $monthNumber = $item->date_group;
                $monthName = $months[$monthNumber - 1]; // Convert 1-12 to 0-11 for array index
                $formattedData[$monthName] = $item->count;
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

        // Format date range for display
        $formattedStartDate = $startDate->format('M j, Y');
        $formattedEndDate = $endDate->format('M j, Y');

        return [
            'total' => $totalApplications,
            'percentage_change' => round($percentageChange, 1),
            'daily_data' => $formattedData,
            'status_counts' => $statusCounts,
            'period' => $period,
            'date_range' => [
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
                'formatted_start_date' => $formattedStartDate,
                'formatted_end_date' => $formattedEndDate,
                'display' => "$formattedStartDate - $formattedEndDate",
                'is_custom_date_range' => $isCustomDateRange
            ]
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
