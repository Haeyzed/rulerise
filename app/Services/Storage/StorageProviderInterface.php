<?php

namespace App\Services\Storage;

use Illuminate\Http\UploadedFile;

interface StorageProviderInterface
{
    /**
     * Upload a file to storage.
     *
     * @param UploadedFile|string $file
     * @param string $path
     * @param string|null $filename
     * @param array $options
     * @return string
     */
    public function upload($file, string $path, ?string $filename = null, array $options = []): string;

    /**
     * Delete a file from storage.
     *
     * @param string $path
     * @return bool
     */
    public function delete(string $path): bool;

    /**
     * Get the URL for a file.
     *
     * @param string $path
     * @return string
     */
    public function url(string $path): string;

    /**
     * Check if a file exists.
     *
     * @param string $path
     * @return bool
     */
    public function exists(string $path): bool;
}
