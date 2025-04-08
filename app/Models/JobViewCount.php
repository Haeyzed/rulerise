<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * JobViewCount model representing views of a job listing
 *
 * @property int $id
 * @property int $job_id
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property int|null $candidate_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @property-read Job $job
 * @property-read Candidate|null $candidate
 */
class JobViewCount extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'job_id',
        'ip_address',
        'user_agent',
        'candidate_id',
    ];

    /**
     * Get the job that owns the view count.
     */
    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }

    /**
     * Get the candidate that viewed the job.
     */
    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }
}
