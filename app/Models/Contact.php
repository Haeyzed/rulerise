<?php

namespace App\Models;

use App\Traits\HasDateFilter;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Contact extends Model
{
    use HasFactory, SoftDeletes, HasDateFilter;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'title',
        'value',
        'type',
        'order',
        'is_active',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
        'order' => 'integer',
    ];

    /**
     * Get the formatted value based on type.
     *
     * @return string
     */
    public function getFormattedValueAttribute(): string
    {
        switch ($this->type) {
            case 'email':
                return 'mailto:' . $this->value;
            case 'phone':
                return 'tel:' . $this->value;
            case 'whatsapp':
                return 'https://wa.me/' . preg_replace('/[^0-9]/', '', $this->value);
            default:
                return $this->value;
        }
    }
}
