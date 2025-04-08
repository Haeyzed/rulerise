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

            return response()->json([
                'success' => true,
                'message' => 'Roles retrieved successfully',
                'data' => $roles
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve roles',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
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
                return response()->json([
                    'success' => false,
                    'message' => 'Role not found'
                ], Response::HTTP_NOT_FOUND);
            }

            return response()->json([
                'success' => true,
                'message' => 'Role retrieved successfully',
                'data' => $role
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve role',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
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
            $role = Role::create([
                'name' => $request->name,
                'description' => $request->description,
                'guard_name' => 'api'
            ]);

            // Attach permissions to the role
            $permissions = Permission::whereIn('id', $request->permissions)->get();
            $role->syncPermissions($permissions);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Role created successfully',
                'data' => $role->load('permissions')
            ], Response::HTTP_CREATED);
        } catch (Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to create role',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
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

            $role = Role::findById($request->id, 'api');

            if (!$role) {
                return response()->json([
                    'success' => false,
                    'message' => 'Role not found'
                ], JsonResponse::HTTP_NOT_FOUND);
            }

            // Update the role
            $role->update([
                'name' => $request->name,
                'description' => $request->description
            ]);

            // Sync permissions
            $permissions = Permission::whereIn('id', $request->permissions)->get();
            $role->syncPermissions($permissions);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Role updated successfully',
                'data' => $role->load('permissions')
            ]);
        } catch (Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to update role',
                'error' => $e->getMessage()
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
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

            $role = Role::where('name', $roleName)->first();

            if (!$role) {
                return response()->json([
                    'success' => false,
                    'message' => 'Role not found'
                ], JsonResponse::HTTP_NOT_FOUND);
            }

            // Check if the role is assigned to any users
            if ($role->users()->count() > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete role as it is assigned to users'
                ], JsonResponse::HTTP_BAD_REQUEST);
            }

            // Delete the role
            $role->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Role deleted successfully'
            ]);
        } catch (Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete role',
                'error' => $e->getMessage()
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
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

            return response()->json([
                'success' => true,
                'message' => 'Permissions retrieved successfully',
                'data' => $permissions
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve permissions',
                'error' => $e->getMessage()
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
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

            return response()->json([
                'success' => true,
                'message' => 'Role assigned to user successfully'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to assign role to user',
                'error' => $e->getMessage()
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
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

            $user = User::findOrFail($request->user_id);
            $role = Role::where('name', $request->role_name)->first();

            $user->removeRole($role);

            return response()->json([
                'success' => true,
                'message' => 'Role removed from user successfully'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to remove role from user',
                'error' => $e->getMessage()
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
