<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * SubscriptionPlan model representing available subscription plans for employers
 *
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property float $price
 * @property string $currency
 * @property int|null $duration_days
 * @property int $job_posts_limit
 * @property int $featured_jobs_limit
 * @property int $resume_views_limit
 * @property bool $job_alerts
 * @property bool $candidate_search
 * @property bool $resume_access
 * @property bool $company_profile
 * @property string $support_level
 * @property bool $is_active
 * @property bool $is_featured
 * @property string $payment_type
 * @property array|null $features
 * @property string|null $external_paypal_id
 * @property string|null $external_stripe_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 *
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Subscription[] $subscriptions
 */
class SubscriptionPlan extends Model
{
    use HasFactory;

    /**
     * Payment type constants
     */
    const PAYMENT_TYPE_ONE_TIME = 'one_time';
    const PAYMENT_TYPE_RECURRING = 'recurring';

    /**
     * Tenure type constants
     */
    const TENURE_TYPE_TRIAL = 'TRIAL';
    const TENURE_TYPE_REGULAR = 'REGULAR';

    /**
     * Interval unit constants
     */
    const INTERVAL_UNIT_DAY = 'DAY';
    const INTERVAL_UNIT_WEEK = 'WEEK';
    const INTERVAL_UNIT_MONTH = 'MONTH';
    const INTERVAL_UNIT_YEAR = 'YEAR';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
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

//        'is_trial_period',
//        'trial_period_days',
//        'tenure_type',
//        'interval_unit',
//        'interval_count',
//        'total_cycles',

        'external_paypal_id',
        'external_stripe_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
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
        'features' => 'json',
    ];

    /**
     * Get the subscriptions for the plan.
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class, 'subscription_plan_id');
    }

    /**
     * Check if the plan is a one-time payment plan
     *
     * @return bool
     */
    public function isOneTime(): bool
    {
        return $this->payment_type === self::PAYMENT_TYPE_ONE_TIME;
    }

    /**
     * Check if the plan is a recurring payment plan
     *
     * @return bool
     */
    public function isRecurring(): bool
    {
        return $this->payment_type === self::PAYMENT_TYPE_RECURRING;
    }

    /**
     * Check if the plan has a trial period
     *
     * @return bool
     */
    public function hasTrial(): bool
    {
        return $this->is_trial_period && $this->trial_period_days > 0;
    }

    /**
     * Get formatted duration.
     *
     * @return string
     */
    public function getFormattedDuration(): string
    {
        if ($this->isOneTime()) {
            return 'No expiration';
        }

        if (!$this->duration_days) {
            return 'N/A';
        }

        if ($this->duration_days % 30 === 0) {
            $months = $this->duration_days / 30;
            return $months === 1 ? '1 month' : "$months months";
        }

        if ($this->duration_days % 7 === 0) {
            $weeks = $this->duration_days / 7;
            return $weeks === 1 ? '1 week' : "$weeks weeks";
        }

        return $this->duration_days === 1 ? '1 day' : "{$this->duration_days} days";
    }

    /**
     * Get formatted trial period.
     *
     * @return string|null
     */
    public function getFormattedTrialPeriod(): ?string
    {
        if (!$this->hasTrial()) {
            return null;
        }

        if ($this->trial_period_days % 30 === 0) {
            $months = $this->trial_period_days / 30;
            return $months === 1 ? '1 month trial' : "$months months trial";
        }

        if ($this->trial_period_days % 7 === 0) {
            $weeks = $this->trial_period_days / 7;
            return $weeks === 1 ? '1 week trial' : "$weeks weeks trial";
        }

        return $this->trial_period_days === 1 ? '1 day trial' : "{$this->trial_period_days} days trial";
    }

    /**
     * Get PayPal billing cycles configuration
     *
     * @return array
     */
    public function getPayPalBillingCycles(): array
    {
        $billingCycles = [];

        // Add trial period if enabled
        if ($this->hasTrial()) {
            $billingCycles[] = [
                'frequency' => [
                    'interval_unit' => 'DAY',
                    'interval_count' => $this->trial_period_days
                ],
                'tenure_type' => 'TRIAL',
                'sequence' => 1,
                'total_cycles' => 1,
                'pricing_scheme' => [
                    'fixed_price' => [
                        'value' => '0',
                        'currency_code' => strtoupper($this->currency)
                    ]
                ]
            ];
        }

        // Add regular billing cycle
        $billingCycles[] = [
            'frequency' => [
                'interval_unit' => $this->interval_unit ?: 'DAY',
                'interval_count' => $this->interval_count ?: $this->duration_days
            ],
            'tenure_type' => 'REGULAR',
            'sequence' => $this->hasTrial() ? 2 : 1,
            'total_cycles' => $this->total_cycles ?: 0, // 0 means infinite cycles
            'pricing_scheme' => [
                'fixed_price' => [
                    'value' => (string) $this->price,
                    'currency_code' => strtoupper($this->currency)
                ]
            ]
        ];

        return $billingCycles;
    }

    /**
     * Get formatted price with currency.
     *
     * @return string
     */
    public function getFormattedPrice(): string
    {
        return number_format($this->price, 2) . ' ' . strtoupper($this->currency);
    }
}
