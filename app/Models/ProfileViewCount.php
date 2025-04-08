<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * ProfileViewCount model representing views of a candidate's profile
 *
 * @property int $id
 * @property int $candidate_id
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property int|null $employer_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @property-read Candidate $candidate
 * @property-read Employer|null $employer
 */
class ProfileViewCount extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'candidate_id',
        'ip_address',
        'user_agent',
        'employer_id',
    ];

    /**
     * Get the candidate that owns the profile view count.
     */
    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }

    /**
     * Get the employer that viewed the profile.
     */
    public function employer(): BelongsTo
    {
        return $this->belongsTo(Employer::class);
    }
}
