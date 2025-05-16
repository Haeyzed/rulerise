<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Subscription model representing an employer's subscription to a plan
 *
 * @property int $id
 * @property int $employer_id
 * @property int $subscription_plan_id
 * @property Carbon $start_date
 * @property Carbon $end_date
 * @property float $amount_paid
 * @property string $currency
 * @property string|null $payment_method
 * @property string|null $transaction_id
 * @property string|null $payment_reference
 * @property string|null $subscription_id
 * @property string|null $receipt_path
 * @property int $job_posts_left
 * @property int $featured_jobs_left
 * @property int $cv_downloads_left
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @property-read Employer $employer
 * @property-read SubscriptionPlan $plan
 */
class Subscription extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
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
        'subscriber_info',
        'billing_info',
        'external_status',
        'status_update_time',
        'next_billing_date',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
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
        'subscriber_info' => 'json',
        'billing_info' => 'json',
    ];

    /**
     * Get the employer that owns the subscription.
     */
    public function employer(): BelongsTo
    {
        return $this->belongsTo(Employer::class);
    }

    /**
     * Get the plan that the subscription belongs to.
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    /**
     * Check if the subscription is expired.
     *
     * @return bool
     */
    public function isExpired(): bool
    {
        return $this->end_date < now();
    }

    /**
     * Check if the subscription has job posts left.
     *
     * @return bool
     */
    public function hasJobPostsLeft(): bool
    {
        return $this->job_posts_left > 0;
    }

    /**
     * Check if the subscription has featured jobs left.
     *
     * @return bool
     */
    public function hasFeaturedJobsLeft(): bool
    {
        return $this->featured_jobs_left > 0;
    }

    /**
     * Check if the subscription has CV downloads left.
     *
     * @return bool
     */
    public function hasCvDownloadsLeft(): bool
    {
        return $this->cv_downloads_left > 0;
    }

    /**
     * Get days remaining in the subscription.
     *
     * @return int
     */
    public function daysRemaining(): int
    {
        if ($this->isExpired()) {
            return 0;
        }

        return now()->diffInDays($this->end_date);
    }

    /**
     * Get subscription status text.
     *
     * @return string
     */
    public function getStatusText(): string
    {
        if (!$this->is_active) {
            return 'Cancelled';
        }

        if ($this->isExpired()) {
            return 'Expired';
        }

        return 'Active';
    }
}
