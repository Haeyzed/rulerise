<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UploadRequest;
use App\Http\Resources\UploadResource;
use App\Models\Upload;
use App\Services\UploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class UploadController extends Controller implements HasMiddleware
{
    /**
     * @var UploadService
     */
    protected UploadService $uploadService;

    /**
     * UploadController constructor.
     *
     * @param UploadService $uploadService
     */
    public function __construct(UploadService $uploadService)
    {
        $this->uploadService = $uploadService;
    }

    /**
     * Get the middleware that should be assigned to the controller.
     */
    public static function middleware(): array
    {
        return [
            new Middleware('auth:api'),
        ];
    }

    /**
     * Upload a single file.
     *
     * @param UploadRequest $request
     * @return JsonResponse
     */
    public function upload(UploadRequest $request): JsonResponse
    {
        $file = $request->file('file');
        $options = $this->getUploadOptions($request);

        $upload = $this->uploadService->uploadFile($file, $options);

        return response()->success(
            new UploadResource($upload),
            'File uploaded successfully'
        );
    }

    /**
     * Upload multiple files.
     *
     * @param UploadRequest $request
     * @return JsonResponse
     */
    public function uploadMultiple(UploadRequest $request): JsonResponse
    {
        $files = $request->file('files');
        $options = $this->getUploadOptions($request);

        $uploads = $this->uploadService->uploadFiles($files, $options);

        return response()->success(
            UploadResource::collection($uploads),
            count($uploads) . ' files uploaded successfully'
        );
    }

    /**
     * Get all uploads.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $query = Upload::query();

        // Apply filters
        if ($request->has('collection')) {
            $query->collection($request->input('collection'));
        }

        if ($request->has('mime_type')) {
            $query->ofType($request->input('mime_type'));
        }

        if ($request->has('is_public')) {
            $query->where('is_public', $request->boolean('is_public'));
        }

        // Apply sorting
        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Paginate results
        $perPage = $request->input('per_page', 15);
        $uploads = $query->paginate($perPage);

        return response()->paginatedSuccess(
            UploadResource::collection($uploads),
            'Uploads retrieved successfully'
        );
    }

    /**
     * Get a specific upload.
     *
     * @param Upload $upload
     * @return JsonResponse
     */
    public function show(Upload $upload): JsonResponse
    {
        return response()->success(
            new UploadResource($upload),
            'Upload retrieved successfully'
        );
    }

    /**
     * Delete an upload.
     *
     * @param Upload $upload
     * @return JsonResponse
     */
    public function destroy(Upload $upload): JsonResponse
    {
        $this->uploadService->deleteUpload($upload);

        return response()->success(
            null,
            'Upload deleted successfully'
        );
    }

    /**
     * Get uploads by collection.
     *
     * @param Request $request
     * @param string $collection
     * @return JsonResponse
     */
    public function getByCollection(Request $request, string $collection): JsonResponse
    {
        $options = [
            'user_id' => $request->input('user_id'),
            'is_public' => $request->boolean('is_public', null),
            'mime_type' => $request->input('mime_type'),
        ];

        $uploads = $this->uploadService->getUploadsByCollection($collection, $options);

        return response()->success(
            UploadResource::collection($uploads),
            'Uploads retrieved successfully'
        );
    }

    /**
     * Get upload options from request.
     *
     * @param Request $request
     * @return array
     */
    protected function getUploadOptions(Request $request): array
    {
        return [
            'path' => $request->input('path', 'uploads/' . date('Y/m/d')),
            'disk' => $request->input('disk'),
            'is_public' => $request->boolean('is_public', true),
            'user_id' => auth()->id(),
            'collection' => $request->input('collection'),
            'metadata' => $request->input('metadata', []),
        ];
    }
}
