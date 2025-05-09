<?php

namespace App\Services;

use App\Models\Upload;
use App\Services\Storage\StorageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class UploadService
{
    /**
     * @var StorageService
     */
    protected StorageService $storageService;

    /**
     * UploadService constructor.
     *
     * @param StorageService $storageService
     */
    public function __construct(StorageService $storageService)
    {
        $this->storageService = $storageService;
    }

    /**
     * Upload a single file.
     *
     * @param UploadedFile $file
     * @param array $options
     * @return Upload
     */
    public function uploadFile(UploadedFile $file, array $options = []): Upload
    {
        // Extract options
        $path = $options['path'] ?? 'uploads/' . date('Y/m/d');
        $disk = $options['disk'] ?? config('filestorage.default_disk', 'public');
        $isPublic = $options['is_public'] ?? true;
        $userId = $options['user_id'] ?? Auth::id();
        $collection = $options['collection'] ?? null;
        $metadata = $options['metadata'] ?? [];

        // Generate a unique filename
        $originalFilename = $file->getClientOriginalName();
        $extension = $file->getClientOriginalExtension();
        $filename = Str::uuid() . ($extension ? '.' . $extension : '');

        // Upload the file
        $storedPath = $this->storageService->upload($file, $path, $filename, [
            'disk' => $disk,
            'visibility' => $isPublic ? 'public' : 'private',
        ]);

        // Create upload record
        return Upload::create([
            'user_id' => $userId,
            'filename' => $filename,
            'original_filename' => $originalFilename,
            'path' => $path,
            'disk' => $disk,
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'is_public' => $isPublic,
            'metadata' => $metadata,
            'collection' => $collection,
        ]);
    }

    /**
     * Upload multiple files.
     *
     * @param array $files
     * @param array $options
     * @return array
     */
    public function uploadFiles(array $files, array $options = []): array
    {
        $uploads = [];

        foreach ($files as $file) {
            if ($file instanceof UploadedFile) {
                $uploads[] = $this->uploadFile($file, $options);
            }
        }

        return $uploads;
    }

    /**
     * Delete an upload.
     *
     * @param Upload $upload
     * @param bool $forceDelete
     * @return bool
     */
    public function deleteUpload(Upload $upload, bool $forceDelete = false): bool
    {
        // Delete the file from storage
        $this->storageService->delete($upload->full_path);

        // Delete the upload record
        if ($forceDelete) {
            return $upload->forceDelete();
        }

        return $upload->delete();
    }

    /**
     * Get uploads by collection.
     *
     * @param string $collection
     * @param array $options
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getUploadsByCollection(string $collection, array $options = [])
    {
        $query = Upload::collection($collection);

        if (isset($options['user_id'])) {
            $query->forUser($options['user_id']);
        }

        if (isset($options['is_public'])) {
            $query->where('is_public', $options['is_public']);
        }

        if (isset($options['mime_type'])) {
            $query->ofType($options['mime_type']);
        }

        return $query->get();
    }
}
