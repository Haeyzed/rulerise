<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Degree model representing education degree types
 *
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property int $level
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 *
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\EducationHistory[] $educationHistories
 */
class Degree extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'description',
        'level',
    ];

    /**
     * Get the education histories that use this degree.
     */
    public function educationHistories(): HasMany
    {
        return $this->hasMany(EducationHistory::class);
    }
}
