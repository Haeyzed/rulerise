<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RoleRequest;
use App\Models\User;
use App\Services\AdminService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\DB;
use Exception;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controller for managing roles.
 *
 * @package App\Http\Controllers\Admin
 */
class RolesController extends Controller
{
    /**
     * The admin service instance.
     *
     * @var AdminService
     */
    protected AdminService $adminService;

    /**
     * Create a new controller instance.
     *
     * @param AdminService $adminService
     * @return void
     */
    public function __construct(AdminService $adminService)
    {
        $this->adminService = $adminService;
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
                ->when($search, function ($query, $search) {
                    return $query->where('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                })
                ->orderBy($sortBy, $sortOrder)
                ->paginate($perPage);

            return response()->paginatedSuccess($roles,'Roles retrieved successfully');
        } catch (Exception $e) {
            return response()->internalServerError($e->getMessage());
        }
    }

    /**
     * Display the specified role.
     *
     * @param  string  $roleName
     * @return JsonResponse
     */
    public function show(string $roleName): JsonResponse
    {
        try {
            $role = Role::with('permissions')->where('name', $roleName)->first();

            if (!$role) {
                return response()->notFound('Role not found');
            }

            return response()->success($role, 'Role retrieved successfully');
        } catch (Exception $e) {
            return response()->internalServerError($e->getMessage());
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

            // Create the role
            $role = Role::query()->create([
                'name' => $request->name,
                'description' => $request->description,
                'guard_name' => 'api'
            ]);

            // Attach permissions to the role
            $permissions = Permission::query()->whereIn('id', $request->permissions)->get();
            $role->syncPermissions($permissions);

            DB::commit();

            return response()->created($role->load('permissions'), 'Role created successfully');
        } catch (Exception $e) {
            DB::rollBack();
            return response()->internalServerError($e->getMessage());
        }
    }

    /**
     * Update the specified role in storage.
     *
     * @param RoleRequest $request
     * @return JsonResponse
     */
    public function update(RoleRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $role = Role::query()->findById($request->id, 'api');

            if (!$role) {
                return response()->notFound('Role not found');
            }

            // Update the role
            $role->update([
                'name' => $request->name,
                'description' => $request->description
            ]);

            // Sync permissions
            $permissions = Permission::query()->whereIn('id', $request->permissions)->get();
            $role->syncPermissions($permissions);

            DB::commit();

            return response()->success($role->load('permissions'), 'Role updated successfully');
        } catch (Exception $e) {
            DB::rollBack();

            return response()->internalServerError($e->getMessage());
        }
    }

    /**
     * Remove the specified role from storage.
     *
     * @param  string  $roleName
     * @return JsonResponse
     */
    public function delete(string $roleName): JsonResponse
    {
        try {
            DB::beginTransaction();

            $role = Role::query()->where('name', $roleName)->first();

            if (!$role) {
                return response()->notFound('Role not found');
            }

            // Check if the role is assigned to any users
            if ($role->users()->count() > 0) {
                return response()->badRequest('Role cannot be deleted it is assigned to user');
            }

            // Delete the role
            $role->delete();

            DB::commit();

            return response()->success(null, 'Role deleted successfully');
        } catch (Exception $e) {
            DB::rollBack();
            return response()->internalServerError($e->getMessage());
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
            $permissions = Permission::all();

            return response()->success($permissions, 'Permissions retrieved successfully');
        } catch (Exception $e) {
            return response()->internalServerError($e->getMessage());
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
            $role = Role::where('name', $request->role_name)->first();

            $user->assignRole($role);

            return response()->success($user, 'Role assigned successfully');
        } catch (Exception $e) {
            return response()->internalServerError($e->getMessage());
        }
    }

    /**
     * Remove role from user.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function removeRoleFromUser(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'user_id' => 'required|exists:users,id',
                'role_name' => 'required|exists:roles,name'
            ]);

            $user = User::query()->findOrFail($request->user_id);
            $role = Role::query()->where('name', $request->role_name)->first();

            $user->removeRole($role);

            return response()->success($user, 'Role removed successfully');
        } catch (Exception $e) {
            return response()->internalServerError($e->getMessage());
        }
    }
}
