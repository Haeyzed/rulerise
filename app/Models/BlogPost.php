<?php

namespace App\Models;

use App\Enums\BlogPostStatusEnum;
use App\Services\Storage\StorageService;
use App\Traits\HasDateFilter;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class BlogPost extends Model
{
    /** @use HasFactory<BlogPostFactory> */
    use HasFactory, SoftDeletes, HasDateFilter;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'title',
        'subtitle',
        'category',
        'body',
        'banner_image',
        'caption',
        'status',
        'user_id',
        'views',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'status' => BlogPostStatusEnum::class,
        'views' => 'integer',
    ];

    /**
     * Get the user that owns the blog post.
     *
     * @return BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the images for the blog post.
     *
     * @return HasMany
     */
    public function images(): HasMany
    {
        return $this->hasMany(BlogPostImage::class)->orderBy('order');
    }

    /**
     * Get the banner image URL.
     *
     * @return string|null
     */
    public function getBannerImageUrlAttribute(): ?string
    {
        if (!$this->banner_image) {
            return null;
        }

        return app(StorageService::class)->url($this->banner_image);
    }
}
