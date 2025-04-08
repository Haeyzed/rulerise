<?php

namespace App\Services\Storage;

use Illuminate\Http\UploadedFile;

interface StorageAdapterInterface
{
    /**
     * Upload a file to storage.
     *
     * @param UploadedFile $file
     * @param string $path
     * @param array $options
     * @return string
     */
    public function upload(UploadedFile $file, string $path, array $options = []): string;

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
