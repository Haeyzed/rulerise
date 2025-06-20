<?php

namespace App\Models;

use App\Services\Storage\StorageService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;

/**
 * Employer Model
 *
 * Represents companies that post jobs and manage subscriptions.
 * Includes comprehensive company profile management and subscription tracking.
 *
 * @property int $id
 * @property int $user_id
 * @property string $company_name
 * @property string|null $company_email
 * @property string|null $company_logo
 * @property string|null $company_description
 * @property string|null $company_industry
 * @property string|null $company_size
 * @property string|null $company_founded
 * @property string|null $company_country
 * @property string|null $company_state
 * @property string|null $company_address
 * @property string|null $company_phone_number
 * @property string|null $company_website
 * @property array|null $company_benefits
 * @property string|null $company_linkedin_url
 * @property string|null $company_twitter_url
 * @property string|null $company_facebook_url
 * @property string|null $stripe_customer_id
 * @property string|null $paypal_customer_id
 * @property bool $is_verified
 * @property bool $is_featured
 * @property bool $has_used_trial
 * @property Carbon|null $trial_used_at
 * @property Carbon|null $created_at
 * @property-read User $user
 * @property-read string|null $company_logo_url
 */
class Employer extends Model
{
    use HasFactory, Notifiable;

    protected $appends = ['company_logo_url'];

    protected $fillable = [
        'user_id',
        'company_name',
        'company_email',
        'company_logo',
        'company_description',
        'company_industry',
        'company_size',
        'company_founded',
        'company_country',
        'company_state',
        'company_address',
        'company_phone_number',
        'company_website',
        'company_benefits',
        'company_linkedin_url',
        'company_twitter_url',
        'company_facebook_url',
        'stripe_customer_id',
        'paypal_customer_id',
        'is_verified',
        'is_featured',
        'has_used_trial',
        'trial_used_at',
    ];

    protected $casts = [
        'company_founded' => 'date',
        'is_verified' => 'boolean',
        'is_featured' => 'boolean',
        'company_benefits' => 'array',
        'has_used_trial' => 'boolean',
        'trial_used_at' => 'datetime',
    ];

    // ========================================
    // RELATIONSHIPS
    // ========================================

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function candidatePools(): HasMany
    {
        return $this->hasMany(CandidatePool::class);
    }

    public function applications(): HasManyThrough
    {
        return $this->hasManyThrough(JobApplication::class, Job::class);
    }

    public function jobs(): HasMany
    {
        return $this->hasMany(Job::class);
    }

    public function jobViewCounts(): HasManyThrough
    {
        return $this->hasManyThrough(JobViewCount::class, Job::class);
    }

    public function notificationTemplates(): HasMany
    {
        return $this->hasMany(JobNotificationTemplate::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function activeSubscription(): HasOne
    {
        return $this->hasOne(Subscription::class)
            ->where('is_active', true)
//            ->where('status', Subscription::STATUS_ACTIVE)
            ->where(function ($query) {
                $query->whereNull('end_date')
                    ->orWhere('end_date', '>=', now());
            })
            ->latest();
    }

    // ========================================
    // SUBSCRIPTION METHODS
    // ========================================

    public function hasActiveSubscription(): bool
    {
        return $this->activeSubscription()->exists();
    }

    public function getActiveSubscription(): ?Subscription
    {
        return $this->activeSubscription;
    }

    public function getCurrentPlan(): ?Plan
    {
        return $this->activeSubscription?->plan;
    }

    public function canAccessFeature(string $feature): bool
    {
        $subscription = $this->getActiveSubscription();

        if (!$subscription || !$subscription->isActive()) {
            return false;
        }

        $plan = $subscription->plan;

        return match ($feature) {
            'candidate_database' => $plan->candidate_database_access,
            'analytics' => $plan->analytics_access,
            'priority_support' => $plan->priority_support,
            default => false,
        };
    }

    public function getRemainingJobPosts(): int
    {
        $subscription = $this->getActiveSubscription();

        if (!$subscription || !$subscription->isActive()) {
            return 0;
        }

        $plan = $subscription->plan;

        if ($plan->job_posts_limit === -1) {
            return PHP_INT_MAX; // Unlimited
        }

        $usedPosts = $this->jobs()
            ->where('created_at', '>=', $subscription->start_date)
            ->count();

        return max(0, $plan->job_posts_limit - $usedPosts);
    }

    public function getRemainingResumeViews(): int
    {
        $subscription = $this->getActiveSubscription();

        if (!$subscription) {
            return 0;
        }

        return max(0, $subscription->cv_downloads_left ?? 0);
    }

    // ========================================
    // TRIAL METHODS
    // ========================================

    public function hasUsedTrial(): bool
    {
        return $this->has_used_trial;
    }

    public function markTrialAsUsed(): void
    {
        $this->update([
            'has_used_trial' => true,
            'trial_used_at' => now(),
        ]);
    }

    public function isEligibleForTrial(): bool
    {
        return !$this->hasUsedTrial();
    }

    // ========================================
    // COMPANY PROFILE METHODS
    // ========================================

    public function getCompanyLogoUrlAttribute(): ?string
    {
        if (!$this->company_logo) {
            return null;
        }

        return app(StorageService::class)->url($this->company_logo);
    }

    public function getCompanyDisplayName(): string
    {
        return $this->company_name ?: $this->user->name;
    }

    public function isProfileComplete(): bool
    {
        $requiredFields = [
            'company_name',
            'company_email',
            'company_description',
            'company_industry',
            'company_country',
        ];

        foreach ($requiredFields as $field) {
            if (empty($this->$field)) {
                return false;
            }
        }

        return true;
    }

    public function getCompletionPercentage(): int
    {
        $fields = [
            'company_name',
            'company_email',
            'company_logo',
            'company_description',
            'company_industry',
            'company_size',
            'company_country',
            'company_website',
            'company_phone_number',
        ];

        $completedFields = 0;
        foreach ($fields as $field) {
            if (!empty($this->$field)) {
                $completedFields++;
            }
        }

        return round(($completedFields / count($fields)) * 100);
    }

    // ========================================
    // QUERY SCOPES
    // ========================================

    public function scopeVerified($query)
    {
        return $query->where('is_verified', true);
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    public function scopeWithActiveSubscription($query)
    {
        return $query->whereHas('activeSubscription');
    }

    public function scopeTrialEligible($query)
    {
        return $query->where('has_used_trial', false);
    }
}
