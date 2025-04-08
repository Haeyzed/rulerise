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
        return $this->hasMany(Subscription::class);
    }
}
