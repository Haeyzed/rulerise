<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class Subscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'employer_id',
        'subscription_plan_id',
        'start_date',
        'end_date',
        'amount_paid',
        'currency',
        'payment_method',
        'transaction_id',
        'payment_reference',
        'subscription_id',
        'receipt_path',
        'job_posts_left',
        'featured_jobs_left',
        'cv_downloads_left',
        'is_active',
        'is_suspended',
        'used_trial',
        'subscriber_info',
        'billing_info',
        'external_status',
        'status_update_time',
        'next_billing_date',
        'payment_type',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'next_billing_date' => 'date',
        'status_update_time' => 'datetime',
        'amount_paid' => 'float',
        'job_posts_left' => 'integer',
        'featured_jobs_left' => 'integer',
        'cv_downloads_left' => 'integer',
        'is_active' => 'boolean',
        'is_suspended' => 'boolean',
        'used_trial' => 'boolean',
        'subscriber_info' => 'json',
        'billing_info' => 'json',
    ];

    public function employer(): BelongsTo
    {
        return $this->belongsTo(Employer::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    public function isOneTime(): bool
    {
        return $this->payment_type === SubscriptionPlan::PAYMENT_TYPE_ONE_TIME;
    }

    public function isRecurring(): bool
    {
        return $this->payment_type === SubscriptionPlan::PAYMENT_TYPE_RECURRING;
    }

    public function isExpired(): bool
    {
        if ($this->isOneTime()) {
            return false;
        }

        if ($this->end_date === null) {
            return false;
        }

        return $this->end_date < now();
    }

    public function hasJobPostsLeft(): bool
    {
        return $this->job_posts_left > 0;
    }

    public function hasFeaturedJobsLeft(): bool
    {
        return $this->featured_jobs_left > 0;
    }

    public function hasCvDownloadsLeft(): bool
    {
        return $this->cv_downloads_left > 0;
    }

    public function daysRemaining(): int
    {
        if ($this->isOneTime()) {
            return PHP_INT_MAX;
        }

        if ($this->isExpired()) {
            return 0;
        }

        return now()->diffInDays($this->end_date);
    }

    public function getStatusText(): string
    {
        if (!$this->is_active) {
            return 'Cancelled';
        }

        if ($this->is_suspended) {
            return 'Suspended';
        }

        if ($this->isExpired() && !$this->isOneTime()) {
            return 'Expired';
        }

        return 'Active';
    }
}
