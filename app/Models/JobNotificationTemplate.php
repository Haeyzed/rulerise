<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * JobNotificationTemplate model representing email templates for job notifications
 *
 * @property int $id
 * @property int $employer_id
 * @property string $name
 * @property string $subject
 * @property string $content
 * @property string $type
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 *
 * @property-read \App\Models\Employer $employer
 */
class JobNotificationTemplate extends Model
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
        'subject',
        'content',
        'type',
    ];

    /**
     * Get the employer that owns the notification template.
     */
    public function employer(): BelongsTo
    {
        return $this->belongsTo(Employer::class);
    }
}
