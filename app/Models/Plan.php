<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Plan Model
 *
 * Represents subscription plans with flexible billing cycles,
 * feature sets, and trial period support.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property float $price
 * @property string $currency
 * @property string $billing_cycle
 * @property int|null $job_posts_limit
 * @property int $featured_jobs_limit
 * @property int $resume_views_limit
 * @property bool $candidate_database_access
 * @property bool $analytics_access
 * @property bool $priority_support
 * @property array|null $features
 * @property string|null $stripe_price_id
 * @property string|null $paypal_plan_id
 * @property string|null $paypal_product_id
 * @property bool $is_active
 * @property bool $is_popular
 * @property int $trial_days
 * @property bool $has_trial
 * @property int|null $duration_days
 */
class Plan extends Model
{
    use HasFactory;

    /**
     * Billing cycle constants
     */
    public const BILLING_MONTHLY = 'monthly';
    public const BILLING_YEARLY = 'yearly';
    public const BILLING_ONE_TIME = 'one_time';

    /**
     * Currency constants
     */
    public const CURRENCY_USD = 'usd';
    public const CURRENCY_EUR = 'eur';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'price',
        'currency',
        'billing_cycle',
        'job_posts_limit',
        'featured_jobs_limit',
        'candidate_database_access',
        'analytics_access',
        'priority_support',
        'resume_views_limit',
        'features',
        'stripe_price_id',
        'paypal_plan_id',
        'paypal_product_id',
        'is_active',
        'is_popular',
        'trial_days',
        'has_trial',
        'duration_days',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'candidate_database_access' => 'boolean',
        'analytics_access' => 'boolean',
        'priority_support' => 'boolean',
        'features' => 'array',
        'is_active' => 'boolean',
        'is_popular' => 'boolean',
        'has_trial' => 'boolean',
        'trial_days' => 'integer',
        'job_posts_limit' => 'integer',
        'featured_jobs_limit' => 'integer',
        'resume_views_limit' => 'integer',
        'duration_days' => 'integer',
    ];

    // ========================================
    // RELATIONSHIPS
    // ========================================

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    // ========================================
    // BILLING TYPE METHODS
    // ========================================

    public function isRecurring(): bool
    {
        return in_array($this->billing_cycle, [
            self::BILLING_MONTHLY,
            self::BILLING_YEARLY
        ]);
    }

    public function isOneTime(): bool
    {
        return $this->billing_cycle === self::BILLING_ONE_TIME;
    }

    public function isMonthly(): bool
    {
        return $this->billing_cycle === self::BILLING_MONTHLY;
    }

    public function isYearly(): bool
    {
        return $this->billing_cycle === self::BILLING_YEARLY;
    }

    // ========================================
    // TRIAL METHODS
    // ========================================

    public function hasTrial(): bool
    {
        return $this->has_trial && $this->trial_days > 0;
    }

    public function getTrialPeriodDays(): int
    {
        return $this->hasTrial() ? $this->trial_days : 0;
    }

    // ========================================
    // UTILITY METHODS
    // ========================================

    public function getCurrencyCode(): string
    {
        return strtoupper($this->currency);
    }

    public function getFormattedPrice(): string
    {
        return number_format($this->price, 2) . ' ' . $this->getCurrencyCode();
    }

    public function getBillingCycleLabel(): string
    {
        return match ($this->billing_cycle) {
            self::BILLING_MONTHLY => 'Monthly',
            self::BILLING_YEARLY => 'Yearly',
            self::BILLING_ONE_TIME => 'One-time',
            default => ucfirst($this->billing_cycle),
        };
    }

    public function getFeaturesList(): array
    {
        $features = [];

        if ($this->job_posts_limit) {
            $features[] = $this->job_posts_limit === -1
                ? 'Unlimited job posts'
                : "{$this->job_posts_limit} job posts";
        }

        if ($this->featured_jobs_limit > 0) {
            $features[] = "{$this->featured_jobs_limit} featured jobs";
        }

        if ($this->resume_views_limit) {
            $features[] = $this->resume_views_limit === -1
                ? 'Unlimited resume views'
                : "{$this->resume_views_limit} resume views";
        }

        if ($this->candidate_database_access) {
            $features[] = 'Candidate database access';
        }

        if ($this->analytics_access) {
            $features[] = 'Analytics & reporting';
        }

        if ($this->priority_support) {
            $features[] = 'Priority support';
        }

        // Add custom features from the features array
        if ($this->features) {
            $features = array_merge($features, $this->features);
        }

        return $features;
    }

    // ========================================
    // QUERY SCOPES
    // ========================================

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeRecurring($query)
    {
        return $query->whereIn('billing_cycle', [
            self::BILLING_MONTHLY,
            self::BILLING_YEARLY
        ]);
    }

    public function scopeOneTime($query)
    {
        return $query->where('billing_cycle', self::BILLING_ONE_TIME);
    }

    public function scopePopular($query)
    {
        return $query->where('is_popular', true);
    }

    public function scopeWithTrial($query)
    {
        return $query->where('has_trial', true)
            ->where('trial_days', '>', 0);
    }

    public function scopeByBillingCycle($query, string $cycle)
    {
        return $query->where('billing_cycle', $cycle);
    }
}
