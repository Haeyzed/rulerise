<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * SavedJob model representing a job saved by a candidate
 *
 * @property int $id
 * @property int $candidate_id
 * @property int $job_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @property-read Candidate $candidate
 * @property-read Job $job
 */
class SavedJob extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'candidate_id',
        'job_id',
    ];

    /**
     * Get the candidate that saved the job.
     */
    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }

    /**
     * Get the job that was saved.
     */
    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }
}
