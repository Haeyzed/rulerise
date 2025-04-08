<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Qualification model representing a candidate's professional qualifications
 *
 * @property int $id
 * @property int $candidate_id
 * @property string|null $professional_title
 * @property string|null $summary
 * @property array|null $skills
 * @property array|null $certifications
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 *
 * @property-read \App\Models\Candidate $candidate
 */
class Qualification extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'candidate_id',
        'professional_title',
        'summary',
        'skills',
        'certifications',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'skills' => 'array',
        'certifications' => 'array',
    ];

    /**
     * Get the candidate that owns the qualification.
     */
    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }
}
