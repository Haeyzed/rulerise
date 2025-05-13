<?php

namespace App\Services\Storage\Providers;

use App\Services\Storage\StorageProviderInterface;
use Cloudinary\Cloudinary;
use Cloudinary\Configuration\Configuration;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class CloudinaryProvider implements StorageProviderInterface
{
    /**
     * The Cloudinary instance.
     *
     * @var Cloudinary
     */
    protected Cloudinary $cloudinary;

    /**
     * Create a new Cloudinary provider instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->cloudinary = new Cloudinary(
            Configuration::instance([
                'cloud' => [
                    'cloud_name' => config('filestorage.providers.cloudinary.cloud_name'),
                    'api_key' => config('filestorage.providers.cloudinary.api_key'),
                    'api_secret' => config('filestorage.providers.cloudinary.api_secret'),
                ],
                'url' => [
                    'secure' => true,
                ],
            ])
        );
    }

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
        $folder = trim($path, '/');

        $uploadOptions = array_merge([
            'folder' => $folder,
            'public_id' => pathinfo($filename, PATHINFO_FILENAME),
        ], $options);

        if ($file instanceof UploadedFile) {
            $result = $this->cloudinary->uploadApi()->upload(
                $file->getRealPath(),
                $uploadOptions
            );
        } else {
            $result = $this->cloudinary->uploadApi()->upload(
                $file,
                $uploadOptions
            );
        }

        return $result['public_id'];
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
            $this->cloudinary->uploadApi()->destroy($path);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get the URL for a file.
     *
     * @param string $path
     * @return string
     */
    public function url(string $path): string
    {
        return $this->cloudinary->image($path)->toUrl();
    }

    /**
     * Check if a file exists.
     *
     * @param string $path
     * @return bool
     */
    public function exists(string $path): bool
    {
        try {
            $this->cloudinary->adminApi()->asset($path);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get the size of a file.
     *
     * @param string $path
     * @return int|null
     */
    public function size(string $path): ?int
    {
        try {
            $asset = $this->cloudinary->adminApi()->asset($path);
            return $asset['bytes'] ?? null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get the mime type of a file.
     *
     * @param string $path
     * @return string|null
     */
    public function mimeType(string $path): ?string
    {
        try {
            $asset = $this->cloudinary->adminApi()->asset($path);
            return $asset['resource_type'] . '/' . $asset['format'];
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get the last modified time of a file.
     *
     * @param string $path
     * @return int|null
     */
    public function lastModified(string $path): ?int
    {
        try {
            $asset = $this->cloudinary->adminApi()->asset($path);
            return strtotime($asset['created_at']);
        } catch (\Exception $e) {
            return null;
        }
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
        $timestamp = $expiration->getTimestamp();
        $signature = sha1(
            "public_id={$path}&timestamp={$timestamp}" .
            config('filestorage.providers.cloudinary.api_secret')
        );

        return $this->url($path) . "?timestamp={$timestamp}&signature={$signature}";
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
        try {
            $this->cloudinary->uploadApi()->rename($from, $to);
            return true;
        } catch (\Exception $e) {
            return false;
        }
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
        return $this->copy($from, $to) && $this->delete($from);
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
            return Str::random(40) . 'Storage' . $file->getClientOriginalExtension();
        }

        return Str::random(40) . 'Storage' . pathinfo($file, PATHINFO_EXTENSION);
    }
}
