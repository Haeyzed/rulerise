<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property float $price
 * @property string $currency
 * @property string $billing_cycle
 * @property int|null $job_posts_limit
 * @property int $featured_jobs_limit
 * @property bool $candidate_database_access
 * @property bool $analytics_access
 * @property bool $priority_support
 * @property array|null $features
 * @property string|null $stripe_price_id
 * @property string|null $paypal_plan_id
 * @property bool $is_active
 * @property bool $is_popular
 * @property int $trial_days
 * @property bool $has_trial
 */
class Plan extends Model
{
    use HasFactory;

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
    ];

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function isRecurring(): bool
    {
        return in_array($this->billing_cycle, ['monthly', 'yearly']);
    }

    public function isOneTime(): bool
    {
        return $this->billing_cycle === 'one_time';
    }

    public function hasTrial(): bool
    {
        return $this->has_trial && $this->trial_days > 0;
    }

    public function getTrialPeriodDays(): int
    {
        return $this->hasTrial() ? $this->trial_days : 0;
    }

    public function getCurrencyCode(): string
    {
        return strtoupper($this->currency);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeRecurring($query)
    {
        return $query->whereIn('billing_cycle', ['monthly', 'yearly']);
    }

    public function scopeOneTime($query)
    {
        return $query->where('billing_cycle', 'one_time');
    }
}
