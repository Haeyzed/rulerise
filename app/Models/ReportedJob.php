<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * ReportedJob model representing a job reported by a candidate
 *
 * @property int $id
 * @property int $job_id
 * @property int $candidate_id
 * @property string $reason
 * @property string|null $description
 * @property bool $is_resolved
 * @property string|null $admin_notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @property-read Job $job
 * @property-read Candidate $candidate
 */
class ReportedJob extends Model
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
        'reason',
        'description',
        'is_resolved',
        'admin_notes',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_resolved' => 'boolean',
    ];

    /**
     * Get the job that was reported.
     */
    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }

    /**
     * Get the candidate that reported the job.
     */
    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }
}
