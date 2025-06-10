<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionPlan extends Model
{
    use HasFactory;

    const PAYMENT_TYPE_ONE_TIME = 'one_time';
    const PAYMENT_TYPE_RECURRING = 'recurring';

    const INTERVAL_UNIT_DAY = 'DAY';
    const INTERVAL_UNIT_WEEK = 'WEEK';
    const INTERVAL_UNIT_MONTH = 'MONTH';
    const INTERVAL_UNIT_YEAR = 'YEAR';

    protected $fillable = [
        'name',
        'description',
        'price',
        'currency',
        'duration_days',
        'job_posts_limit',
        'featured_jobs_limit',
        'resume_views_limit',
        'job_alerts',
        'candidate_search',
        'resume_access',
        'company_profile',
        'support_level',
        'is_active',
        'is_featured',
        'features',
        'payment_type',
        'has_trial',
        'trial_period_days',
        'payment_gateway_config',
        'interval_unit',
        'interval_count',
        'total_cycles',
        'metadata',
        'external_paypal_id',
        'external_stripe_id',
    ];

    protected $casts = [
        'price' => 'float',
        'duration_days' => 'integer',
        'job_posts_limit' => 'integer',
        'featured_jobs_limit' => 'integer',
        'resume_views_limit' => 'integer',
        'job_alerts' => 'boolean',
        'candidate_search' => 'boolean',
        'resume_access' => 'boolean',
        'company_profile' => 'boolean',
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
        'has_trial' => 'boolean',
        'trial_period_days' => 'integer',
        'interval_count' => 'integer',
        'total_cycles' => 'integer',
        'features' => 'json',
        'payment_gateway_config' => 'json',
        'metadata' => 'json',
    ];

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class, 'subscription_plan_id');
    }

    public function isOneTime(): bool
    {
        return $this->payment_type === self::PAYMENT_TYPE_ONE_TIME;
    }

    public function isRecurring(): bool
    {
        return $this->payment_type === self::PAYMENT_TYPE_RECURRING;
    }

    public function hasTrial(): bool
    {
        return $this->has_trial && $this->trial_period_days > 0;
    }

    public function getTrialPeriodDays(): int
    {
        return $this->hasTrial() ? $this->trial_period_days : 0;
    }

    public function getFormattedPrice(): string
    {
        return number_format($this->price, 2) . ' ' . strtoupper($this->currency);
    }
}
