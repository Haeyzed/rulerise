<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyBenefit extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'employer_id',
        'benefit',
    ];

    /**
     * Get the employer that owns the benefit.
     */
    public function employer(): BelongsTo
    {
        return $this->belongsTo(Employer::class);
    }
}