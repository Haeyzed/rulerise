<?php

namespace App\Models;

use App\Services\Storage\StorageService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\App;

class Upload extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'filename',
        'original_filename',
        'path',
        'disk',
        'mime_type',
        'size',
        'is_public',
        'metadata',
        'collection',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'size' => 'integer',
        'is_public' => 'boolean',
        'metadata' => 'array',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = [
        'url',
        'full_path',
    ];

    /**
     * Get the user that owns the upload.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the URL for the file.
     *
     * @return string
     */
    public function getUrlAttribute(): string
    {
        return App::make(StorageService::class)->url($this->full_path);
    }

    /**
     * Get the full path for the file.
     *
     * @return string
     */
    public function getFullPathAttribute(): string
    {
        return $this->path . '/' . $this->filename;
    }

    /**
     * Scope a query to only include uploads for a specific collection.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $collection
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeCollection($query, string $collection)
    {
        return $query->where('collection', $collection);
    }

    /**
     * Scope a query to only include public uploads.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopePublic($query)
    {
        return $query->where('is_public', true);
    }

    /**
     * Scope a query to only include uploads for a specific user.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $userId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope a query to only include uploads with a specific mime type.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string|array $mimeType
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeOfType($query, $mimeType)
    {
        return is_array($mimeType) 
            ? $query->whereIn('mime_type', $mimeType)
            : $query->where('mime_type', 'like', $mimeType . '%');
    }
}
