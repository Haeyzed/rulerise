<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Job model representing job listings.
 *
 * @property int $id
 * @property int $employer_id
 * @property int $job_category_id
 * @property string $title
 * @property string $slug
 * @property string $short_description
 * @property string $description
 * @property string $job_type
 * @property string $employment_type
 * @property string $job_industry
 * @property string $location
 * @property string $job_level
 * @property string $experience_level
 * @property float|null $salary
 * @property string $salary_payment_mode
 * @property string $email_to_apply
 * @property bool $easy_apply
 * @property bool $email_apply
 * @property int $vacancies
 * @property Carbon|null $deadline
 * @property bool $is_active
 * @property bool $is_featured
 * @property bool $is_approved
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @property-read Employer $employer
 * @property-read JobCategory $category
 * @property-read Collection|JobApplication[] $applications
 * @property-read Collection|SavedJob[] $savedJobs
 * @property-read Collection|JobViewCount[] $viewCounts
 * @property-read Collection|ReportedJob[] $reports
 */

class Job extends Model
{
    use HasFactory;

    protected $table = 'job_listings';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        // Foreign keys
        'employer_id',
        'job_category_id',

        // Basic info
        'title',
        'slug',
        'short_description',
        'description',
        'job_type',
        'employment_type',
        'job_industry',
        'location',
        'language',
        'job_level',
        'experience_level',
        'skills_required',

        // Salary
        'salary',
        'salary_payment_mode',

        // Application info
        'email_to_apply',
        'easy_apply',
        'email_apply',
        'vacancies',
        'deadline',

        // Flags
        'is_active',
        'is_draft',
        'is_featured',
        'is_approved',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'salary' => 'float',
        'deadline' => 'date',
        'is_active' => 'boolean',
        'is_draft' => 'boolean',
        'is_featured' => 'boolean',
        'is_approved' => 'boolean',
        'easy_apply' => 'boolean',
        'email_apply' => 'boolean',
        'vacancies' => 'integer',
        'skills_required' => 'array',
    ];

    /**
     * Get the employer that owns the job.
     */
    public function employer(): BelongsTo
    {
        return $this->belongsTo(Employer::class);
    }

    /**
     * Get the category that the job belongs to.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(JobCategory::class, 'job_category_id');
    }

    /**
     * Get the applications for the job.
     */
    public function applications(): HasMany
    {
        return $this->hasMany(JobApplication::class);
    }

    /**
     * Get the saved jobs for the job.
     */
    public function savedJobs(): HasMany
    {
        return $this->hasMany(SavedJob::class);
    }

    /**
     * Get the view counts for the job.
     */
    public function viewCounts(): HasMany
    {
        return $this->hasMany(JobViewCount::class);
    }

    /**
     * Get the reports for the job.
     */
    public function reports(): HasMany
    {
        return $this->hasMany(ReportedJob::class);
    }

    /**
     * Scope a query to only include active jobs.
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to only include featured jobs.
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true);
    }

    /**
     * Scope a query to only include approved jobs.
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('is_approved', true);
    }

    /**
     * Scope a query to only include jobs that haven't expired.
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeNotExpired(Builder $query): Builder
    {
        return $query->where(function($query) {
            $query->whereNull('deadline')
                ->orWhere('deadline', '>=', now());
        });
    }

    /**
     * Scope a query to only include jobs that are available for public viewing.
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopePubliclyAvailable(Builder $query): Builder
    {
        return $query->active()->approved()->notExpired();
    }
}
