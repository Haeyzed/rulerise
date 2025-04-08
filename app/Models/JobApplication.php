<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * JobApplication model representing a candidate's application to a job
 *
 * @property int $id
 * @property int $job_id
 * @property int $candidate_id
 * @property int|null $resume_id
 * @property string|null $cover_letter
 * @property string $status
 * @property string|null $employer_notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @property-read Job $job
 * @property-read Candidate $candidate
 * @property-read Resume|null $resume
 */
class JobApplication extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'job_id',
        'candidate_id',
        'resume_id',
        'cover_letter',
        'status',
        'employer_notes',
    ];

    /**
     * Get the job that the application belongs to.
     */
    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }

    /**
     * Get the candidate that owns the application.
     */
    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }

    /**
     * Get the resume that was used for the application.
     */
    public function resume(): BelongsTo
    {
        return $this->belongsTo(Resume::class);
    }
}
