<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PermissionRequest;
use App\Services\ACLService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Spatie\Permission\Models\Permission;

/**
 * Controller for managing permissions.
 *
 * @package App\Http\Controllers\Admin
 */
class PermissionsController extends Controller implements HasMiddleware
{
    /**
     * The ACL service instance.
     *
     * @var ACLService
     */
    protected ACLService $aclService;

    /**
     * Create a new controller instance.
     *
     * @param ACLService $aclService
     * @return void
     */
    public function __construct(ACLService $aclService)
    {
        $this->aclService = $aclService;
    }

    /**
     * Get the middleware that should be assigned to the controller.
     */
    public static function middleware(): array
    {
        return [
            new Middleware(['auth:api', 'role:admin']),
        ];
    }

    /**
     * Display a listing of the permissions.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $perPage = $request->input('per_page', 10);
            $search = $request->input('search', '');
            $sortBy = $request->input('sort_by', 'name');
            $sortOrder = $request->input('sort_order', 'asc');

            $permissions = Permission::query()
                ->when($search, function ($query, $search) {
                    return $query->where('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                })
                ->orderBy($sortBy, $sortOrder)
                ->paginate($perPage);

            return response()->paginatedSuccess($permissions, 'Permissions retrieved successfully');
        } catch (Exception $e) {
            return response()->internalServerError($e->getMessage());
        }
    }

    /**
     * Store a newly created permission in storage.
     *
     * @param PermissionRequest $request
     * @return JsonResponse
     */
    public function store(PermissionRequest $request): JsonResponse
    {
        try {
            $permission = $this->aclService->createPermission(
                $request->name,
                $request->description ?? null
            );

            return response()->created($permission, 'Permission created successfully');
        } catch (Exception $e) {
            return response()->internalServerError($e->getMessage());
        }
    }

    /**
     * Display the specified permission.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        try {
            $permission = Permission::findOrFail($id);

            return response()->success($permission, 'Permission retrieved successfully');
        } catch (Exception $e) {
            return response()->internalServerError($e->getMessage());
        }
    }

    /**
     * Update the specified permission in storage.
     *
     * @param PermissionRequest $request
     * @param int $id
     * @return JsonResponse
     */
    public function update(PermissionRequest $request, int $id): JsonResponse
    {
        try {
            $permission = Permission::findOrFail($id);
            
            $permission->update([
                'name' => $request->name,
                'description' => $request->description ?? null
            ]);

            return response()->success($permission, 'Permission updated successfully');
        } catch (Exception $e) {
            return response()->internalServerError($e->getMessage());
        }
    }

    /**
     * Remove the specified permission from storage.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $permission = Permission::findOrFail($id);
            
            // Check if the permission is assigned to any roles
            if ($permission->roles()->count() > 0) {
                return response()->badRequest('Permission cannot be deleted as it is assigned to one or more roles');
            }
            
            $permission->delete();

            return response()->success(null, 'Permission deleted successfully');
        } catch (Exception $e) {
            return response()->internalServerError($e->getMessage());
        }
    }
}
