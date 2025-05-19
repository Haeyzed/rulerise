<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * EducationHistory model representing a candidate's education background
 *
 * @property int $id
 * @property int $candidate_id
 * @property int|null $degree_id
 * @property string|null $degree
 * @property string $institution
 * @property string|null $field_of_study
 * @property Carbon $start_date
 * @property Carbon|null $end_date
 * @property bool $is_current
 * @property float|null $grade
 * @property string|null $description
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @property-read Candidate $candidate
 */
class EducationHistory extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'candidate_id',
        'degree_id',
        'degree',
        'institution',
        'field_of_study',
        'start_date',
        'end_date',
        'is_current',
        'grade',
        'description',
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
        'grade' => 'float',
    ];

    /**
     * Get the candidate that owns the education history.
     */
    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }

    /**
     * Get the degree associated with this education history.
     */
    public function degree(): BelongsTo
    {
        return $this->belongsTo(Degree::class);
    }
}
