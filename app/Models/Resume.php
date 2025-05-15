<?php

namespace App\Models;

use App\Services\Storage\StorageService;
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
 * @property string $document
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

    protected $appends = ['document_url'];

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'candidate_id',
        'name',
        'document',
        'is_primary',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
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

    /**
     * Get the URL of the client's logo.
     *
     * @return string|null
     */
    public function getDocumentUrlAttribute():  ?string
    {
        if (!$this->document) {
            return null;
        }

        return app(StorageService::class)->url($this->document);
    }
}
