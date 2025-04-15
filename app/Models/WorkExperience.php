<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * WorkExperience model representing a candidate's work history
 *
 * @property int $id
 * @property int $candidate_id
 * @property string $job_title
 * @property string $company_name
 * @property string|null $location
 * @property \Illuminate\Support\Carbon $start_date
 * @property \Illuminate\Support\Carbon|null $end_date
 * @property bool $is_current
 * @property string|null $description
 * @property string|null $achievements
 * @property string|null $company_website
 * @property string|null $employment_type
 * @property string|null $industry
 * @property string|null $experience_level
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 *
 * @property-read \App\Models\Candidate $candidate
 */
class WorkExperience extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'candidate_id',
        'job_title',
        'company_name',
        'location',
        'start_date',
        'end_date',
        'is_current',
        'description',
        'achievements',
        'company_website',
        'employment_type',
        'industry',
        'experience_level',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_current' => 'boolean',
    ];

    /**
     * Get the candidate that owns the work experience.
     */
    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }

    /**
     * Calculate the experience level based on start and end dates
     *
     * @return string|null
     */
    public function calculateExperienceLevel(): ?string
    {
        if (!$this->start_date) {
            return null;
        }

        $endDate = $this->is_current ? now() : ($this->end_date ?? now());
        $years = $this->start_date->diffInYears($endDate);

        if ($years <= 1) {
            return '0_1';
        } elseif ($years <= 3) {
            return '1_3';
        } elseif ($years <= 5) {
            return '3_5';
        } elseif ($years <= 10) {
            return '5_10';
        } else {
            return '10_plus';
        }
    }

    /**
     * Get human-readable experience level
     *
     * @return string|null
     */
    public function getExperienceLevelTextAttribute(): ?string
    {
        $levels = [
            '0_1' => '0-1 year',
            '1_3' => '1-3 years',
            '3_5' => '3-5 years',
            '5_10' => '5-10 years',
            '10_plus' => '10+ years',
        ];

        return $levels[$this->experience_level] ?? null;
    }
}
