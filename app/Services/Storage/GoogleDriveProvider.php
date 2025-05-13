<?php

namespace App\Services\Storage\Providers;

use App\Services\Storage\StorageProviderInterface;
use Google\Client;
use Google\Service\Drive;
use Google\Service\Drive\DriveFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class GoogleDriveProvider implements StorageProviderInterface
{
    /**
     * The disk name.
     *
     * @var string
     */
    protected string $disk = 'google';

    /**
     * The Google Drive service.
     *
     * @var Drive
     */
    protected Drive $service;

    /**
     * Create a new Google Drive provider instance.
     *
     * @return void
     */
    public function __construct()
    {
        $client = new Client();
        $client->setAuthConfig(storage_path('app/google-service-account.json'));
        $client->addScope(Drive::DRIVE);
        $this->service = new Drive($client);
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
        $fullPath = trim($path, '/') . '/' . $filename;

        // Check if the folder exists, create if not
        $folderId = $this->getFolderId($path);

        $fileMetadata = new DriveFile([
            'name' => $filename,
            'parents' => [$folderId],
        ]);

        $content = $file instanceof UploadedFile ? file_get_contents($file->getRealPath()) : file_get_contents($file);
        $mimeType = $file instanceof UploadedFile ? $file->getMimeType() : mime_content_type($file);

        $uploadedFile = $this->service->files->create($fileMetadata, [
            'data' => $content,
            'mimeType' => $mimeType,
            'uploadType' => 'multipart',
            'fields' => 'id',
        ]);

        // Store the file ID in a mapping table for future reference
        $this->storeFileIdMapping($fullPath, $uploadedFile->id);

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
        $fileId = $this->getFileId($path);
        if (!$fileId) {
            return false;
        }

        try {
            $this->service->files->delete($fileId);
            $this->removeFileIdMapping($path);
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
        $fileId = $this->getFileId($path);
        if (!$fileId) {
            return '';
        }

        // Make the file publicly accessible
        try {
            $this->service->permissions->create($fileId, new \Google\Service\Drive\Permission([
                'type' => 'anyone',
                'role' => 'reader',
            ]));
        } catch (\Exception $e) {
            // Permission might already exist
        }

        return "https://drive.google.com/uc?id={$fileId}";
    }

    /**
     * Check if a file exists.
     *
     * @param string $path
     * @return bool
     */
    public function exists(string $path): bool
    {
        $fileId = $this->getFileId($path);
        if (!$fileId) {
            return false;
        }

        try {
            $this->service->files->get($fileId);
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
        $fileId = $this->getFileId($path);
        if (!$fileId) {
            return null;
        }

        try {
            $file = $this->service->files->get($fileId, ['fields' => 'size']);
            return (int) $file->getSize();
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
        $fileId = $this->getFileId($path);
        if (!$fileId) {
            return null;
        }

        try {
            $file = $this->service->files->get($fileId, ['fields' => 'mimeType']);
            return $file->getMimeType();
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
        $fileId = $this->getFileId($path);
        if (!$fileId) {
            return null;
        }

        try {
            $file = $this->service->files->get($fileId, ['fields' => 'modifiedTime']);
            return strtotime($file->getModifiedTime());
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
        // Google Drive doesn't support temporary URLs directly
        // We'll just return the regular URL
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
        $fileId = $this->getFileId($from);
        if (!$fileId) {
            return false;
        }

        $toPathInfo = pathinfo($to);
        $toFolder = $toPathInfo['dirname'];
        $toFilename = $toPathInfo['basename'];

        $folderId = $this->getFolderId($toFolder);

        try {
            $copiedFile = $this->service->files->copy($fileId, new DriveFile([
                'name' => $toFilename,
                'parents' => [$folderId],
            ]));

            $this->storeFileIdMapping($to, $copiedFile->id);
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
            return Str::random(40) . '.' . $file->getClientOriginalExtension();
        }

        return Str::random(40) . '.' . pathinfo($file, PATHINFO_EXTENSION);
    }

    /**
     * Get the folder ID for a path, creating folders if they don't exist.
     *
     * @param string $path
     * @return string
     */
    protected function getFolderId(string $path): string
    {
        $path = trim($path, '/');
        
        if (empty($path)) {
            // Return the root folder ID
            return config('filestorage.providers.google.root_folder_id', 'root');
        }

        $folders = explode('/', $path);
        $parentId = config('filestorage.providers.google.root_folder_id', 'root');

        foreach ($folders as $folder) {
            $query = "name = '{$folder}' and mimeType = 'application/vnd.google-apps.folder' and '{$parentId}' in parents and trashed = false";
            $results = $this->service->files->listFiles([
                'q' => $query,
                'spaces' => 'drive',
                'fields' => 'files(id, name)',
            ]);

            if (count($results->getFiles()) === 0) {
                // Folder doesn't exist, create it
                $folderMetadata = new DriveFile([
                    'name' => $folder,
                    'mimeType' => 'application/vnd.google-apps.folder',
                    'parents' => [$parentId],
                ]);
                $createdFolder = $this->service->files->create($folderMetadata, [
                    'fields' => 'id',
                ]);
                $parentId = $createdFolder->id;
            } else {
                $parentId = $results->getFiles()[0]->getId();
            }
        }

        return $parentId;
    }

    /**
     * Store a mapping between a file path and its Google Drive file ID.
     *
     * @param string $path
     * @param string $fileId
     * @return void
     */
    protected function storeFileIdMapping(string $path, string $fileId): void
    {
        // In a real application, you would store this in a database
        // For simplicity, we'll use a JSON file
        $mappingsFile = storage_path('app/google_drive_mappings.json');
        $mappings = [];

        if (file_exists($mappingsFile)) {
            $mappings = json_decode(file_get_contents($mappingsFile), true);
        }

        $mappings[$path] = $fileId;
        file_put_contents($mappingsFile, json_encode($mappings));
    }

    /**
     * Get the Google Drive file ID for a path.
     *
     * @param string $path
     * @return string|null
     */
    protected function getFileId(string $path): ?string
    {
        $mappingsFile = storage_path('app/google_drive_mappings.json');
        
        if (!file_exists($mappingsFile)) {
            return null;
        }

        $mappings = json_decode(file_get_contents($mappingsFile), true);
        return $mappings[$path] ?? null;
    }

    /**
     * Remove a file ID mapping.
     *
     * @param string $path
     * @return void
     */
    protected function removeFileIdMapping(string $path): void
    {
        $mappingsFile = storage_path('app/google_drive_mappings.json');
        
        if (!file_exists($mappingsFile)) {
            return;
        }

        $mappings = json_decode(file_get_contents($mappingsFile), true);
        unset($mappings[$path]);
        file_put_contents($mappingsFile, json_encode($mappings));
    }
}