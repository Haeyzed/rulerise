<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * OldSubscription model representing an employer's subscription to a plan
 *
 * @property int $id
 * @property int $employer_id
 * @property int $subscription_plan_id
 * @property Carbon $start_date
 * @property Carbon|null $end_date
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
 * @property string $payment_type
 * @property bool $is_suspended
 * @property bool $used_trial
 * @property array|null $subscriber_info
 * @property array|null $billing_info
 * @property string|null $external_status
 * @property Carbon|null $status_update_time
 * @property Carbon|null $next_billing_date
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @property-read Employer $employer
 * @property-read SubscriptionPlan $plan
 */
class OldSubscription extends Model
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
        'used_trial',
        'subscriber_info',
        'billing_info',
        'external_status',
        'status_update_time',
        'next_billing_date',
        'payment_type',
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
        'used_trial' => 'boolean',
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
     * Check if the subscription is a one-time payment
     *
     * @return bool
     */
    public function isOneTime(): bool
    {
        return $this->payment_type === SubscriptionPlan::PAYMENT_TYPE_ONE_TIME;
    }

    /**
     * Check if the subscription is a recurring payment
     *
     * @return bool
     */
    public function isRecurring(): bool
    {
        return $this->payment_type === SubscriptionPlan::PAYMENT_TYPE_RECURRING;
    }
}
