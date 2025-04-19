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
use Illuminate\Support\Carbon;

/**
 * Employer model representing companies that post jobs
 *
 * @property int $id
 * @property int $user_id
 * @property string $company_name
 * @property string|null $company_email
 * @property string|null $company_logo
 * @property string|null $company_description
 * @property string|null $company_industry
 * @property string|null $number_of_employees
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
 * @property bool $is_verified
 * @property bool $is_featured
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @property-read User $user
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
        'is_verified',
        'is_featured',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'company_founded' => 'date',
        'is_verified' => 'boolean',
        'is_featured' => 'boolean',
        'company_benefits' => 'array',
    ];



    /**
     * Get the user that owns the employer profile.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the candidate pools for the employer.
     */
    public function candidatePools(): HasMany
    {
        return $this->hasMany(CandidatePool::class);
    }

    /**
     * Get all job applications across all of the employer's jobs.
     */
    public function applications(): HasManyThrough
    {
        return $this->hasManyThrough(JobApplication::class, Job::class);
    }

//    /**
//     * Get the benefits offered by the company.
//     */
//    public function benefits(): HasMany
//    {
//        return $this->hasMany(CompanyBenefit::class);
//    }

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

    /**
     * Get the URL of the client's logo.
     *
     * @return string|null
     */
    public function getCompanyLogoUrlAttribute():  ?string
    {
        if (!$this->company_logo) {
            return null;
        }

        return app(StorageService::class)->url($this->company_logo);
    }
}
