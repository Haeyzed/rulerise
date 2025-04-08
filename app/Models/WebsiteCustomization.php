<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * WebsiteCustomization model representing customizable elements of the website
 *
 * @property int $id
 * @property string $type
 * @property string $key
 * @property string|null $value
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class WebsiteCustomization extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'type',
        'key',
        'value',
        'is_active',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get customization by type and key.
     *
     * @param string $type
     * @param string $key
     * @return mixed
     */
    public static function getCustomization(string $type, string $key): mixed
    {
        $customization = self::query()->where('type', $type)
            ->where('key', $key)
            ->where('is_active', true)
            ->first();

        return $customization ? $customization->value : null;
    }
}
