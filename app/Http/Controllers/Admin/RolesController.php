<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RoleRequest;
use App\Http\Resources\RoleResource;
use App\Models\User;
use App\Services\ACLService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\DB;
use Exception;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * Controller for managing roles.
 *
 * @package App\Http\Controllers\Admin
 */
class RolesController extends Controller implements HasMiddleware
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
     * Display a listing of the roles.
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

            $roles = Role::with('permissions')
                ->whereNot('name', 'super_admin') // Exclude super_admin
                ->when($search, function ($query, $search) {
                    return $query->where(function ($query) use ($search) {
                        $query->whereLike('name',"%{$search}%")
                            ->orWhereLike('description',"%{$search}%");
                    });
                })
                ->orderBy($sortBy, $sortOrder)
                ->paginate($perPage);

            return response()->paginatedSuccess(RoleResource::collection($roles), 'Roles retrieved successfully');
        } catch (Exception $e) {
            return response()->serverError($e->getMessage());
        }
    }

    /**
     * Display the specified role.
     *
     * @param  int  $id
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        try {
            $role = Role::with('permissions')->findOrFail($id);

            return response()->success(new RoleResource($role), 'Role retrieved successfully');
        } catch (Exception $e) {
            return response()->serverError($e->getMessage());
        }
    }

    /**
     * Store a newly created role in storage.
     *
     * @param RoleRequest $request
     * @return JsonResponse
     */
    public function store(RoleRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            // Create the role using ACL service
            $role = $this->aclService->createRole(
                $request->name,
                $request->description ?? null,
                $request->permissions ?? []
            );

            DB::commit();

            return response()->created(new RoleResource($role->load('permissions')), 'Role created successfully');
        } catch (Exception $e) {
            DB::rollBack();
            return response()->serverError($e->getMessage());
        }
    }

    /**
     * Update the specified role in storage.
     *
     * @param RoleRequest $request
     * @param int $id
     * @return JsonResponse
     */
    public function update(RoleRequest $request, int $id): JsonResponse
    {
        try {
            DB::beginTransaction();

            $role = Role::findOrFail($id);

            // Update the role
            $role->update([
                'name' => $request->name,
                'description' => $request->description ?? null
            ]);

            // Sync permissions using ACL service
            if ($request->has('permissions')) {
                $this->aclService->syncRolePermissions($role->name, $request->permissions);
            }

            DB::commit();

            return response()->success(new RoleResource($role->load('permissions')), 'Role updated successfully');
        } catch (Exception $e) {
            DB::rollBack();
            return response()->serverError($e->getMessage());
        }
    }

    /**
     * Remove the specified role from storage.
     *
     * @param  int  $id
     * @return JsonResponse
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            DB::beginTransaction();

            $role = Role::findOrFail($id);

            // Check if the role is assigned to any users
            if ($role->users()->count() > 0) {
                return response()->badRequest('Role cannot be deleted as it is assigned to one or more users');
            }

            // Delete the role
            $role->delete();

            DB::commit();

            return response()->success(null, 'Role deleted successfully');
        } catch (Exception $e) {
            DB::rollBack();
            return response()->serverError($e->getMessage());
        }
    }

    /**
     * Get all available permissions.
     *
     * @return JsonResponse
     */
    public function getAllPermissions(): JsonResponse
    {
        try {
            $permissions = $this->aclService->getAllPermissions();

            return response()->success($permissions, 'Permissions retrieved successfully');
        } catch (Exception $e) {
            return response()->serverError($e->getMessage());
        }
    }

    /**
     * Assign role to user.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function assignRoleToUser(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'user_id' => 'required|exists:users,id',
                'role_name' => 'required|exists:roles,name'
            ]);

            $user = User::findOrFail($request->user_id);

            // Use ACL service to assign role (ensuring only one role per user)
            $this->aclService->assignRole($user, $request->role_name);

            return response()->success($user->load('roles'), 'Role assigned successfully');
        } catch (Exception $e) {
            return response()->serverError($e->getMessage());
        }
    }

    /**
     * Assign permissions to user.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function assignPermissionsToUser(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'user_id' => 'required|exists:users,id',
                'permissions' => 'required|array',
                'permissions.*' => 'exists:permissions,name'
            ]);

            $user = User::findOrFail($request->user_id);

            // Use ACL service to assign permissions
            $this->aclService->syncPermissions($user, $request->permissions);

            return response()->success($user->load('permissions'), 'Permissions assigned successfully');
        } catch (Exception $e) {
            return response()->serverError($e->getMessage());
        }
    }
}
