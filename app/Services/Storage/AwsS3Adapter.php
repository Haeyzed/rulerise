<?php

namespace App\Services\Storage;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class AwsS3Adapter implements StorageAdapterInterface
{
    /**
     * @var string
     */
    protected string $disk;

    /**
     * AwsS3Adapter constructor.
     *
     * @param string|null $disk
     */
    public function __construct(?string $disk = null)
    {
        $this->disk = $disk ?? config('filestorage.disks.aws.disk', 's3');
    }

    /**
     * Upload a file to storage.
     *
     * @param UploadedFile $file
     * @param string $path
     * @param array $options
     * @return string
     */
    public function upload(UploadedFile $file, string $path, array $options = []): string
    {
        $visibility = $options['visibility'] ?? 'public';

        return $file->store($path, [
            'disk' => $this->disk,
            'visibility' => $visibility
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
