<?php

namespace App\Services\Storage\Providers;

use App\Services\Storage\StorageProviderInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AwsS3Provider implements StorageProviderInterface
{
    /**
     * The disk name.
     *
     * @var string
     */
    protected string $disk = 's3';

    /**
     * Upload a file to storage.
     *
     * @param UploadedFile|string $file
     * @param string $path
     * @param string|null $filename
     * @param array $options
     * @return string
     */
    public function upload($file, string $path, ?string $filename = null, array $options = []): string
    {
        $filename = $filename ?? $this->generateFilename($file);
        $path = trim($path, '/');

        if ($file instanceof UploadedFile) {
            // Use storeAs() for UploadedFile instances
            $storedPath = $file->storeAs(
                $path,
                $filename,
                [
                    'disk' => $this->disk,
                    'visibility' => $options['visibility'] ?? 'public'
                ]
            );

            return $storedPath;
        } else {
            // For strings (file paths or URLs)
            $fullPath = $path . '/' . $filename;
            Storage::disk($this->disk)->put($fullPath, file_get_contents($file), $options);
            return $fullPath;
        }
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

    /**
     * Generate a unique filename for the file.
     *
     * @param UploadedFile|string $file
     * @return string
     */
    protected function generateFilename($file): string
    {
        if ($file instanceof UploadedFile) {
            return Str::random(40) . '.' . $file->getClientOriginalExtension();
        }

        return Str::random(40) . '.' . pathinfo($file, PATHINFO_EXTENSION);
    }
}
