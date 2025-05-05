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
 * @property int $duration_days
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
 * @property array|null $features
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 *
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Subscription[] $subscriptions
 */
class SubscriptionPlan extends Model
{
    use HasFactory;

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
     * Get formatted duration.
     *
     * @return string
     */
    public function getFormattedDuration(): string
    {
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
     * Get formatted price with currency.
     *
     * @return string
     */
    public function getFormattedPrice(): string
    {
        return number_format($this->price, 2) . ' ' . strtoupper($this->currency);
    }
}
