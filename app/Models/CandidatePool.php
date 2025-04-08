<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * CandidatePool model representing a collection of candidates saved by an employer
 *
 * @property int $id
 * @property int $employer_id
 * @property string $name
 * @property string|null $description
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 *
 * @property-read \App\Models\Employer $employer
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Candidate[] $candidates
 */
class CandidatePool extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'employer_id',
        'name',
        'description',
    ];

    /**
     * Get the employer that owns the candidate pool.
     */
    public function employer(): BelongsTo
    {
        return $this->belongsTo(Employer::class);
    }

    /**
     * Get the candidates in the pool.
     */
    public function candidates()
    {
        return $this->belongsToMany(Candidate::class, 'candidate_pool_candidate')
            ->withPivot('notes')
            ->withTimestamps();
    }
}
