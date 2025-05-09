<?php

namespace App\Services\Storage;

use Illuminate\Support\Facades\Storage;

class CloudinaryAdapter
{
    protected string $disk;

    public function __construct(string $disk = 'cloudinary')
    {
        $this->disk = $disk;
    }

    /**
     * Store a file in storage.
     *
     * @param string $path
     * @param $file
     * @return string|null
     */
    public function store(string $path, $file): ?string
    {
        return Storage::disk($this->disk)->putFile($path, $file);
    }

    /**
     * Store a file in storage with original name.
     *
     * @param string $path
     * @param $file
     * @param string $name
     * @return string|null
     */
    public function storeAs(string $path, $file, string $name): ?string
    {
        return Storage::disk($this->disk)->putFileAs($path, $file, $name);
    }

    /**
     * Get the URL for a file in storage.
     *
     * @param string $path
     * @return string
     */
    public function url(string $path): string
    {
        return Storage::disk($this->disk)->url($path);
    }

    /**
     * Delete a file from storage.
     *
     * @param string $path
     * @return bool
     */
    public function delete(string $path): bool
    {
        try {
            // Check if the path is empty or null
            if (empty($path)) {
                \Log::warning('Attempted to delete an empty path from Cloudinary');
                return true; // Return true to allow record deletion to proceed
            }

            return Storage::disk($this->disk)->delete($path);
        } catch (\Exception $e) {
            // Log the error but return true to allow record deletion to proceed
            \Log::error('Failed to delete file from Cloudinary: ' . $e->getMessage(), [
                'path' => $path,
                'exception' => $e
            ]);
            return true;
        }
    }
}
