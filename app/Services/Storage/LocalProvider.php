<?php

namespace App\Services\Storage\Providers;

use App\Services\Storage\StorageProviderInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class LocalProvider implements StorageProviderInterface
{
    /**
     * The disk name.
     *
     * @var string
     */
    protected string $disk = 'public';

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
        $fullPath = trim($path, '/') . '/' . $filename;

        if ($file instanceof UploadedFile) {
            $stream = fopen($file->getRealPath(), 'r');
            Storage::disk($this->disk)->put($fullPath, $stream, $options);
            if (is_resource($stream)) {
                fclose($stream);
            }
        } else {
            Storage::disk($this->disk)->put($fullPath, file_get_contents($file), $options);
        }

        return $fullPath;
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
     * Get the size of a file.
     *
     * @param string $path
     * @return int|null
     */
    public function size(string $path): ?int
    {
        return Storage::disk($this->disk)->size($path);
    }

    /**
     * Get the mime type of a file.
     *
     * @param string $path
     * @return string|null
     */
    public function mimeType(string $path): ?string
    {
        return Storage::disk($this->disk)->mimeType($path);
    }

    /**
     * Get the last modified time of a file.
     *
     * @param string $path
     * @return int|null
     */
    public function lastModified(string $path): ?int
    {
        return Storage::disk($this->disk)->lastModified($path);
    }

    /**
     * Get a temporary URL for a file.
     *
     * @param string $path
     * @param \DateTimeInterface $expiration
     * @param array $options
     * @return string
     */
    public function temporaryUrl(string $path, \DateTimeInterface $expiration, array $options = []): string
    {
        // Local storage doesn't support temporary URLs
        return $this->url($path);
    }

    /**
     * Copy a file to a new location.
     *
     * @param string $from
     * @param string $to
     * @return bool
     */
    public function copy(string $from, string $to): bool
    {
        return Storage::disk($this->disk)->copy($from, $to);
    }

    /**
     * Move a file to a new location.
     *
     * @param string $from
     * @param string $to
     * @return bool
     */
    public function move(string $from, string $to): bool
    {
        return Storage::disk($this->disk)->move($from, $to);
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