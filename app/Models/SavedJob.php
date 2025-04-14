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
 * @property boolean $is_saved
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
        'is_saved'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_saved' => 'boolean',
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
