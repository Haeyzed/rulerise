<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * Employer model representing companies that post jobs
 *
 * @property int $id
 * @property int $user_id
 * @property string $company_name
 * @property string|null $company_logo
 * @property string|null $company_description
 * @property string|null $industry
 * @property string|null $company_size
 * @property string|null $company_website
 * @property string|null $location
 * @property bool $is_verified
 * @property bool $is_featured
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @property-read User $user
 * @property-read Collection|Job[] $jobs
 * @property-read Collection|JobViewCount[] $jobViewCounts
 * @property-read Collection|CandidatePool[] $candidatePools
 * @property-read Collection|JobNotificationTemplate[] $notificationTemplates
 * @property-read Collection|Subscription[] $subscriptions
 */
class Employer extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'company_name',
        'company_logo',
        'company_description',
        'industry',
        'company_size',
        'company_website',
        'location',
        'is_verified',
        'is_featured',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_verified' => 'boolean',
        'is_featured' => 'boolean',
    ];

    /**
     * Get the user that owns the employer profile.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the jobs for the employer.
     */
    public function jobs(): HasMany
    {
        return $this->hasMany(Job::class);
    }

    /**
     * Get the job view counts for the employer's jobs.
     */
    public function jobViewCounts(): HasManyThrough
    {
        return $this->hasManyThrough(JobViewCount::class, Job::class);
    }

    /**
     * Get the candidate pools for the employer.
     */
    public function candidatePools(): HasMany
    {
        return $this->hasMany(CandidatePool::class);
    }

    /**
     * Get the notification templates for the employer.
     */
    public function notificationTemplates(): HasMany
    {
        return $this->hasMany(JobNotificationTemplate::class);
    }

    /**
     * Get the subscriptions for the employer.
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * Get the active subscription for the employer.
     */
    public function activeSubscription(): HasOne
    {
        return $this->hasOne(Subscription::class)
            ->where('is_active', true)
            ->where('end_date', '>=', now())
            ->latest();
    }

    /**
     * Check if employer has an active subscription
     *
     * @return bool
     */
    public function hasActiveSubscription(): bool
    {
        return $this->activeSubscription()->exists();
    }
}
