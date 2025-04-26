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
 * @property int $job_posts
 * @property int $featured_jobs
 * @property int $cv_downloads
 * @property bool $can_view_candidates
 * @property bool $can_create_candidate_pools
 * @property bool $is_active
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
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'description',
        'price',
        'currency',
        'duration_days',
        'job_posts',
        'featured_jobs',
        'cv_downloads',
        'can_view_candidates',
        'can_create_candidate_pools',
        'is_active',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'price' => 'float',
        'duration_days' => 'integer',
        'job_posts' => 'integer',
        'featured_jobs' => 'integer',
        'cv_downloads' => 'integer',
        'can_view_candidates' => 'boolean',
        'can_create_candidate_pools' => 'boolean',
        'is_active' => 'boolean',
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
