<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * JobCategory model representing categories of jobs
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property string|null $icon
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 *
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Job[] $jobs
 */
class JobCategory extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'slug',
        'description',
        'icon',
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
     * Get the jobs for the job category.
     */
    public function jobs(): HasMany
    {
        return $this->hasMany(Job::class);
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($category) {
            if (empty($category->slug)) {
                $slug = Str::slug($category->name);
                $originalSlug = $slug;
                $i = 1;

                // Check for uniqueness
                while (self::query()->where('slug', $slug)->exists()) {
                    $slug = $originalSlug . '-' . $i++;
                }

                $category->slug = $slug;
            }
        });

        static::updating(function ($category) {
            if ($category->isDirty('name')) {
                $slug = Str::slug($category->name);
                $originalSlug = $slug;
                $i = 1;

                // Ensure slug uniqueness on update too
                while (self::query()->where('slug', $slug)->where('id', '!=', $category->id)->exists()) {
                    $slug = $originalSlug . '-' . $i++;
                }

                $category->slug = $slug;
            }
        });
    }
}
