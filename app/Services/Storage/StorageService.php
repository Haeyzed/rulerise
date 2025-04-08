<?php

namespace App\Services\Storage;

use Illuminate\Http\UploadedFile;

class StorageService
{
    /**
     * @var StorageAdapterInterface
     */
    protected StorageAdapterInterface $adapter;

    /**
     * StorageService constructor.
     *
     * @param StorageAdapterInterface $adapter
     */
    public function __construct(StorageAdapterInterface $adapter)
    {
        $this->adapter = $adapter;
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
        return $this->adapter->upload($file, $path, $options);
    }

    /**
     * Delete a file from storage.
     *
     * @param string $path
     * @return bool
     */
    public function delete(string $path): bool
    {
        return $this->adapter->delete($path);
    }

    /**
     * Get the URL for a file.
     *
     * @param string $path
     * @return string
     */
    public function url(string $path): string
    {
        return $this->adapter->url($path);
    }

    /**
     * Check if a file exists.
     *
     * @param string $path
     * @return bool
     */
    public function exists(string $path): bool
    {
        return $this->adapter->exists($path);
    }

    /**
     * Get the storage adapter.
     *
     * @return StorageAdapterInterface
     */
    public function getAdapter(): StorageAdapterInterface
    {
        return $this->adapter;
    }

    /**
     * Set the storage adapter.
     *
     * @param StorageAdapterInterface $adapter
     * @return void
     */
    public function setAdapter(StorageAdapterInterface $adapter): void
    {
        $this->adapter = $adapter;
    }
}
