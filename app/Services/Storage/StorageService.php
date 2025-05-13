<?php

namespace App\Services\Storage;

use App\Services\Storage\Providers\AwsS3Provider;
use App\Services\Storage\Providers\CloudinaryProvider;
use App\Services\Storage\Providers\DropboxProvider;
use App\Services\Storage\Providers\GoogleDriveProvider;
use App\Services\Storage\Providers\LocalProvider;
use Illuminate\Http\UploadedFile;
use InvalidArgumentException;
use Illuminate\Support\Facades\Log;

class StorageService
{
    /**
     * The storage provider instance.
     *
     * @var StorageProviderInterface
     */
    protected StorageProviderInterface $provider;

    /**
     * Create a new storage service instance.
     *
     * @return void
     */
    public function __construct()
    {
        $provider = env('STORAGE_PROVIDER', 'local');
        $this->setProvider($provider);
    }

    /**
     * Set the storage provider.
     *
     * @param string $provider
     * @return $this
     */
    public function setProvider(string $provider): self
    {
        try {
            $this->provider = match ($provider) {
                'aws', 's3' => new AwsS3Provider(),
                'cloudinary' => new CloudinaryProvider(),
                'dropbox' => new DropboxProvider(),
                'google' => new GoogleDriveProvider(),
                'local' => new LocalProvider(),
                default => throw new InvalidArgumentException("Unsupported storage provider: {$provider}"),
            };
        } catch (\Exception $e) {
            Log::error("Failed to initialize storage provider '{$provider}': " . $e->getMessage());
            // Fallback to local provider if the requested one fails
            $this->provider = new LocalProvider();
        }

        return $this;
    }

    /**
     * Get the current provider name.
     *
     * @return string
     */
    public function getProviderName(): string
    {
        return match (true) {
            $this->provider instanceof AwsS3Provider => 'aws',
            $this->provider instanceof CloudinaryProvider => 'cloudinary',
            $this->provider instanceof DropboxProvider => 'dropbox',
            $this->provider instanceof GoogleDriveProvider => 'google',
            $this->provider instanceof LocalProvider => 'local',
            default => 'unknown',
        };
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
        try {
            return $this->provider->upload($file, $path, $filename, $options);
        } catch (\Exception $e) {
            Log::error("File upload error: " . $e->getMessage());
            throw $e;
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
        try {
            return $this->provider->delete($path);
        } catch (\Exception $e) {
            Log::error("File deletion error: " . $e->getMessage());
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
        try {
            return $this->provider->url($path);
        } catch (\Exception $e) {
            Log::error("Error getting file URL: " . $e->getMessage());
            return '';
        }
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
            return $this->provider->exists($path);
        } catch (\Exception $e) {
            Log::error("Error checking if file exists: " . $e->getMessage());
            return false;
        }
    }
}
