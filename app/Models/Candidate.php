<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * Candidate model representing job seekers
 *
 * @property int $id
 * @property int $user_id
 * @property string|null $year_of_experience
 * @property string|null $highest_qualification
 * @property string|null $prefer_job_industry
 * @property bool $available_to_work
 * @property string|null $bio
 * @property string|null $current_position
 * @property string|null $current_company
 * @property string|null $location
 * @property float|null $expected_salary
 * @property string $currency
 * @property string|null $job_type
 * @property array|null $skills
 * @property bool $is_available
 * @property bool $is_featured
 * @property bool $is_verified
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @property-read User $user
 */
class Candidate extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'year_of_experience',
        'highest_qualification',
        'prefer_job_industry',
        'available_to_work',
        'bio',
        'current_position',
        'current_company',
        'location',
        'expected_salary',
        'currency',
        'job_type',
        'date_of_birth',
        'gender',
        'skills',
        'is_available',
        'is_featured',
        'is_verified',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'date_of_birth' => 'datetime',
        'expected_salary' => 'float',
        'available_to_work' => 'boolean',
        'is_available' => 'boolean',
        'is_featured' => 'boolean',
        'is_verified' => 'boolean',
        'skills' => 'array',
    ];

    /**
     * Get the user that owns the candidate profile.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

//    /**
//     * Get the skills for the candidate.
//     */
//    public function skills(): BelongsToMany
//    {
//        return $this->belongsToMany(Skill::class, 'candidate_skill');
//    }

    /**
     * Get the qualification associated with the candidate.
     */
    public function qualification(): HasOne
    {
        return $this->hasOne(Qualification::class);
    }

    /**
     * Get the work experiences for the candidate.
     */
    public function workExperiences(): HasMany
    {
        return $this->hasMany(WorkExperience::class);
    }

    /**
     * Get the education histories for the candidate.
     */
    public function educationHistories(): HasMany
    {
        return $this->hasMany(EducationHistory::class);
    }

    /**
     * Get the languages for the candidate.
     */
    public function languages(): HasMany
    {
        return $this->hasMany(Language::class);
    }

    /**
     * Get the portfolio associated with the candidate.
     */
    public function portfolio(): HasOne
    {
        return $this->hasOne(Portfolio::class);
    }

    /**
     * Get the credentials for the candidate.
     */
    public function credentials(): HasMany
    {
        return $this->hasMany(CandidateCredential::class);
    }

    /**
     * Get the job applications for the candidate.
     */
    public function jobApplications(): HasMany
    {
        return $this->hasMany(JobApplication::class);
    }

    /**
     * Get the saved jobs for the candidate.
     */
    public function savedJobs(): HasMany
    {
        return $this->hasMany(SavedJob::class);
    }

    /**
     * Get the resumes for the candidate.
     */
    public function resumes(): HasMany
    {
        return $this->hasMany(Resume::class);
    }

    /**
     * Get the reported jobs for the candidate.
     */
    public function reportedJobs(): HasMany
    {
        return $this->hasMany(ReportedJob::class);
    }

    /**
     * Get the profile view counts for the candidate.
     */
    public function profileViewCounts(): HasMany
    {
        return $this->hasMany(ProfileViewCount::class);
    }

    /**
     * Get the candidate pools that the candidate belongs to.
     */
    public function candidatePools()
    {
        return $this->belongsToMany(CandidatePool::class, 'candidate_pool_candidate')
            ->withPivot('notes')
            ->withTimestamps();
    }

    /**
     * Get the primary resume for the candidate.
     */
    public function primaryResume(): HasOne
    {
        return $this->hasOne(Resume::class)->where('is_primary', true);
    }
}
