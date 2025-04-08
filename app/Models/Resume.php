<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Resume model representing a candidate's resume/CV
 *
 * @property int $id
 * @property int $candidate_id
 * @property string $title
 * @property string $file_path
 * @property string $file_name
 * @property string $file_type
 * @property int $file_size
 * @property bool $is_primary
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @property-read Candidate $candidate
 * @property-read Collection|JobApplication[] $jobApplications
 */
class Resume extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'candidate_id',
        'title',
        'file_path',
        'file_name',
        'file_type',
        'file_size',
        'is_primary',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'file_size' => 'integer',
        'is_primary' => 'boolean',
    ];

    /**
     * Get the candidate that owns the resume.
     */
    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }

    /**
     * Get the job applications that use this resume.
     */
    public function jobApplications(): HasMany
    {
        return $this->hasMany(JobApplication::class);
    }
}
