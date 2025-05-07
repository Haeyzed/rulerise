<?php

namespace App\Models;

use App\Services\Storage\StorageService;
use App\Traits\HasDateFilter;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AboutUsImage extends Model
{
    use HasFactory, HasDateFilter;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'about_us_id',
        'image_path',
        'order',
    ];

    /**
     * Get the about us section that owns the image.
     *
     * @return BelongsTo
     */
    public function aboutUs(): BelongsTo
    {
        return $this->belongsTo(AboutUs::class);
    }

    /**
     * Get the image URL.
     *
     * @return string|null
     */
    public function getImageUrlAttribute(): ?string
    {
        if (!$this->image_path) {
            return null;
        }

        return app(StorageService::class)->url($this->image_path);
    }
}
