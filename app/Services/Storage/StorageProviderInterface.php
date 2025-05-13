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

    /**
     * Get the size of a file.
     *
     * @param string $path
     * @return int|null
     */
    public function size(string $path): ?int;

    /**
     * Get the mime type of a file.
     *
     * @param string $path
     * @return string|null
     */
    public function mimeType(string $path): ?string;

    /**
     * Get the last modified time of a file.
     *
     * @param string $path
     * @return int|null
     */
    public function lastModified(string $path): ?int;

    /**
     * Get a temporary URL for a file.
     *
     * @param string $path
     * @param \DateTimeInterface $expiration
     * @param array $options
     * @return string
     */
    public function temporaryUrl(string $path, \DateTimeInterface $expiration, array $options = []): string;

    /**
     * Copy a file to a new location.
     *
     * @param string $from
     * @param string $to
     * @return bool
     */
    public function copy(string $from, string $to): bool;

    /**
     * Move a file to a new location.
     *
     * @param string $from
     * @param string $to
     * @return bool
     */
    public function move(string $from, string $to): bool;
}