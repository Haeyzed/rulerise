<?php

namespace App\Services\Storage;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CloudinaryAdapter implements StorageAdapterInterface
{
    /**
     * @var string
     */
    protected string $disk;

    /**
     * CloudinaryAdapter constructor.
     *
     * @param string|null $disk
     */
    public function __construct(?string $disk = null)
    {
        $this->disk = $disk ?? config('filestorage.disks.cloudinary.disk', 'cloudinary');
    }

    /**
     * Store a file in storage with a specific name.
     *
     * @param UploadedFile $file
     * @param string $path
     * @param string $name
     * @param array $options
     * @return string
     */
    public function upload(UploadedFile $file, string $path, string $name, array $options = []): string
    {
        $extension = $file->getClientOriginalExtension();
        $mimeType = $file->getMimeType();

        // Determine the resource type based on MIME type
        $resourceType = 'raw'; // Default
        if (str_starts_with($mimeType, 'image/')) {
            $resourceType = 'image';
        } elseif (str_starts_with($mimeType, 'video/')) {
            $resourceType = 'video';
        }

        // Ensure the name has the extension
        if (!empty($extension) && !str_ends_with($name, '.' . $extension)) {
            $name = $name . '.' . $extension;
        }

        // Prepare Cloudinary-specific options
        $cloudinaryOptions = [
            'disk' => $this->disk,
            'resource_type' => $resourceType,
            'use_filename' => true,
            'unique_filename' => true,
        ];

//        // If path is provided, use it as a folder
//        if (!empty($path)) {
//            $cloudinaryOptions['folder'] = $path;
//        }
//
//        // Merge with user-provided options (user options take precedence)
        $cloudinaryOptions = array_merge($cloudinaryOptions, $options);

        return $file->storeAs($path, $name, [
            'disk' => $this->disk,
            ...$cloudinaryOptions
        ]);
    }

    /**
     * Delete a file from storage.
     *
     * @param string $path
     * @return bool
     */
    public function delete(string $path): bool
    {
        return Storage::disk($this->disk)->delete($path);
    }

    /**
     * Get the URL for a file.
     *
     * @param string $path
     * @return string
     */
    public function url(string $path): string
    {
        return Storage::disk($this->disk)->url($path);
    }

    /**
     * Check if a file exists.
     *
     * @param string $path
     * @return bool
     */
    public function exists(string $path): bool
    {
        return Storage::disk($this->disk)->exists($path);
    }
}
