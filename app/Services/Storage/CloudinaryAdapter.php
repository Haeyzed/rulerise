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
        // Ensure the filename has an extension
        $extension = $file->getClientOriginalExtension();
        // If the name doesn't already have the extension, add it
        if (!empty($extension) && !Str::endsWith($name, '.' . $extension)) {
            $name = $name . '.' . $extension;
        }
        // Store the file with the specified name
        return Storage::disk($this->disk)->putFileAs('', $file, $name);
//        return $file->storeAs($path, $name, [
//            'disk' => $this->disk,
//            ...$options
//        ]);
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
