<?php

namespace App\Traits;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

trait HasDateFilter
{
    /**
     * Scope a query to filter by date range.
     *
     * @param Builder $query
     * @param string|null $startDate
     * @param string|null $endDate
     * @param string $column
     * @return Builder
     */
    public function scopeFilterByDateRange(Builder $query, ?string $startDate, ?string $endDate, string $column = 'created_at'): Builder
    {
        if ($startDate && $endDate) {
            $start = Carbon::parse($startDate)->startOfDay();
            $end = Carbon::parse($endDate)->endOfDay();

            return $query->whereBetween($column, [$start, $end]);
        }

        return $query;
    }

    /**
     * Scope a query to filter by today's date.
     *
     * @param Builder $query
     * @param string $column
     * @return Builder
     */
    public function scopeToday(Builder $query, string $column = 'created_at'): Builder
    {
        return $query->whereDate($column, Carbon::today());
    }

    /**
     * Scope a query to filter by yesterday's date.
     *
     * @param Builder $query
     * @param string $column
     * @return Builder
     */
    public function scopeYesterday(Builder $query, string $column = 'created_at'): Builder
    {
        return $query->whereDate($column, Carbon::yesterday());
    }

    /**
     * Scope a query to filter by this week.
     *
     * @param Builder $query
     * @param string $column
     * @return Builder
     */
    public function scopeThisWeek(Builder $query, string $column = 'created_at'): Builder
    {
        return $query->whereBetween($column, [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()]);
    }

    /**
     * Scope a query to filter by this month.
     *
     * @param Builder $query
     * @param string $column
     * @return Builder
     */
    public function scopeThisMonth(Builder $query, string $column = 'created_at'): Builder
    {
        return $query->whereBetween($column, [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()]);
    }

    /**
     * Scope a query to filter by this year.
     *
     * @param Builder $query
     * @param string $column
     * @return Builder
     */
    public function scopeThisYear(Builder $query, string $column = 'created_at'): Builder
    {
        return $query->whereBetween($column, [Carbon::now()->startOfYear(), Carbon::now()->endOfYear()]);
    }
}
