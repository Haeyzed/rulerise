<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * GeneralSetting model representing general settings for the application
 *
 * @property int $id
 * @property string $key
 * @property string|null $value
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class GeneralSetting extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'key',
        'value',
    ];

    /**
     * Get setting by key.
     *
     * @param string $key
     * @param mixed|null $default
     * @return mixed
     */
    public static function getSetting(string $key, mixed $default = null): mixed
    {
        $setting = self::query()->where('key', $key)->first();
        return $setting ? $setting->value : $default;
    }

    /**
     * Set setting by key.
     *
     * @param string $key
     * @param mixed $value
     * @return bool
     */
    public static function setSetting(string $key, mixed $value): bool
    {
        $setting = self::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value]
        );

        return (bool) $setting;
    }
}
