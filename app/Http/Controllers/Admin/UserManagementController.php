<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CreateUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Notifications\SendPasswordNotification;
use App\Services\ACLService;
use App\Services\AdminAclService;
use App\Services\AdminService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Controller for managing users.
 *
 * @package App\Http\Controllers\Admin
 */
class UserManagementController extends Controller implements HasMiddleware
{
    /**
     * The admin service instance.
     *
     * @var AdminService
     */
    protected AdminService $adminService;

    /**
     * The ACL service instance.
     *
     * @var ACLService
     */
    protected ACLService $aclService;

    /**
     * The Admin ACL service instance.
     *
     * @var AdminAclService
     */
    protected AdminAclService $adminAclService;

    /**
     * Create a new controller instance.
     *
     * @param AdminService $adminService
     * @param ACLService $aclService
     * @param AdminAclService $adminAclService
     * @return void
     */
    public function __construct(
        AdminService $adminService,
        ACLService $aclService,
        AdminAclService $adminAclService
    ) {
        $this->adminService = $adminService;
        $this->aclService = $aclService;
        $this->adminAclService = $adminAclService;
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
     * Display a listing of the users.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            // Check permission using AdminAclService
            [$hasPermission, $errorMessage] = $this->adminAclService->hasPermission('view');
            if (!$hasPermission) {
                return response()->forbidden($errorMessage);
            }

            $perPage = $request->input('per_page', 10);
            $search = $request->input('search', '');
            $sortBy = $request->input('sort_by', 'created_at');
            $sortOrder = $request->input('sort_order', 'desc');
            $userType = $request->input('user_type', '');
            $isActive = $request->has('is_active') ? $request->boolean('is_active') : null;

            $users = User::with(['roles', 'permissions'])
                ->when($search, function ($query, $search) {
                    return $query->where(function ($q) use ($search) {
                        $q->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
                })
                ->when($userType, function ($query, $userType) {
                    return $query->where('user_type', $userType);
                })
                ->when(!is_null($isActive), function ($query) use ($isActive) {
                    return $query->where('is_active', $isActive);
                })
                ->orderBy($sortBy, $sortOrder)
                ->paginate($perPage);
            return response()->paginatedSuccess(UserResource::collection($users), 'Users retrieved successfully');
        } catch (Exception $e) {
            return response()->serverError($e->getMessage());
        }
    }

    /**
     * Store a newly created user in storage.
     *
     * @param CreateUserRequest $request
     * @return JsonResponse
     */
    public function store(CreateUserRequest $request): JsonResponse
    {
        try {
            // Check permission using AdminAclService
            [$hasPermission, $errorMessage] = $this->adminAclService->hasPermission('create');
            if (!$hasPermission) {
                return response()->forbidden($errorMessage);
            }

            DB::beginTransaction();

            $data = $request->validated();

            // Capture the plain password before hashing
            $plainPassword = Str::password(8);

            // Check if user is trying to register as admin
//            if (isset($data['user_type']) && $data['user_type'] === 'admin') {
//                // Only existing admins can create new admins
//                if (!auth()->check() || !auth()->user()->hasRole('admin')) {
//                    return response()->forbidden('You do not have permission to create admin accounts');
//                }
//            }

            // Create user with hashed password
            $user = User::create([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $data['email'],
                'password' => Hash::make($plainPassword),
                'user_type' => $data['user_type'] ?? 'admin',
                'is_active' => $data['is_active'] ?? true,
                'email_verified_at' => now()
            ]);

            // Assign role if provided, but prevent super admin role assignment
            if (!empty($data['role'])) {
                if (!$this->adminAclService->canAssignRole($data['role'])) {
                    DB::rollBack();
                    return response()->forbidden('The super admin role cannot be assigned');
                }

                $this->aclService->assignRole($user, $data['role']);
            }

            // Send password notification with plaintext password
            $user->notify(new SendPasswordNotification($plainPassword));

            DB::commit();

            return response()->created(
                new UserResource($user->load(['roles', 'permissions'])),
                'User created successfully'
            );
        } catch (Exception $e) {
            DB::rollBack();
            return response()->serverError($e->getMessage());
        }
    }

    /**
     * Display the specified user.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        try {
            // Check permission using AdminAclService
            [$hasPermission, $errorMessage] = $this->adminAclService->hasPermission('view');
            if (!$hasPermission) {
                return response()->forbidden($errorMessage);
            }

            $user = User::with(['roles', 'permissions'])->findOrFail($id);

            return response()->success(new UserResource($user), 'User retrieved successfully');
        } catch (Exception $e) {
            return response()->serverError($e->getMessage());
        }
    }

    /**
     * Update the specified user in storage.
     *
     * @param UpdateUserRequest $request
     * @param int $id
     * @return JsonResponse
     */
    public function update(UpdateUserRequest $request, int $id): JsonResponse
    {
        try {
            // Check permission using AdminAclService
            [$hasPermission, $errorMessage] = $this->adminAclService->hasPermission('update');
            if (!$hasPermission) {
                return response()->forbidden($errorMessage);
            }

            DB::beginTransaction();

            $user = User::findOrFail($id);
            $data = $request->validated();

            // Update user data
            $user->fill([
                'first_name' => $data['first_name'] ?? $user->first_name,
                'last_name' => $data['last_name'] ?? $user->last_name,
                'email' => $data['email'] ?? $user->email,
                'is_active' => $data['is_active'] ?? $user->is_active,
            ]);

            // Update password if provided
            if (!empty($data['password'])) {
                $user->password = Hash::make($data['password']);
            }

            $user->save();

            // Update role if provided, but prevent super admin role assignment
            if (isset($data['role'])) {
                if (!$this->adminAclService->canAssignRole($data['role'])) {
                    DB::rollBack();
                    return response()->forbidden('The super admin role cannot be assigned');
                }

                $this->aclService->assignRole($user, $data['role']);
            }

            DB::commit();

            return response()->success(
                new UserResource($user->load(['roles', 'permissions'])),
                'User updated successfully'
            );
        } catch (Exception $e) {
            DB::rollBack();
            return response()->serverError($e->getMessage());
        }
    }

    /**
     * Remove the specified user from storage.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            // Check permission using AdminAclService
            [$hasPermission, $errorMessage] = $this->adminAclService->hasPermission('delete');
            if (!$hasPermission) {
                return response()->forbidden($errorMessage);
            }

            $user = User::findOrFail($id);

            // Prevent deleting yourself
            if (auth()->id() === $user->id) {
                return response()->badRequest('You cannot delete your own account');
            }

            $user->forceDelete();

            return response()->success(null, 'User deleted successfully');
        } catch (Exception $e) {
            return response()->serverError($e->getMessage());
        }
    }

    /**
     * Update user status (active/inactive).
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        try {
            // Check permission using AdminAclService
            [$hasPermission, $errorMessage] = $this->adminAclService->hasPermission('update');
            if (!$hasPermission) {
                return response()->forbidden($errorMessage);
            }

            $request->validate([
                'is_active' => 'required|boolean',
            ]);

            $user = User::findOrFail($id);

            // Prevent deactivating yourself
            if (auth()->id() === $user->id && !$request->is_active) {
                return response()->badRequest('You cannot deactivate your own account');
            }

            $user->is_active = $request->is_active;
            $user->save();

            $status = $request->is_active ? 'activated' : 'deactivated';
            return response()->success(new UserResource($user), "User {$status} successfully");
        } catch (Exception $e) {
            return response()->serverError($e->getMessage());
        }
    }

    /**
     * Get all available roles.
     *
     * @return JsonResponse
     */
    public function getRoles(): JsonResponse
    {
        try {
            // Check permission using AdminAclService
            [$hasPermission, $errorMessage] = $this->adminAclService->hasPermission('view');
            if (!$hasPermission) {
                return response()->forbidden($errorMessage);
            }

            $roles = $this->aclService->getAllRoles();
            return response()->success($roles, 'Roles retrieved successfully');
        } catch (Exception $e) {
            return response()->serverError($e->getMessage());
        }
    }

    /**
     * Get all available permissions.
     *
     * @return JsonResponse
     */
    public function getPermissions(): JsonResponse
    {
        try {
            // Check permission using AdminAclService
            [$hasPermission, $errorMessage] = $this->adminAclService->hasPermission('view');
            if (!$hasPermission) {
                return response()->forbidden($errorMessage);
            }

            $permissions = $this->aclService->getAllPermissions();
            return response()->success($permissions, 'Permissions retrieved successfully');
        } catch (Exception $e) {
            return response()->serverError($e->getMessage());
        }
    }

    /**
     * Export users data.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function export(Request $request): JsonResponse
    {
        try {
            // Check permission using AdminAclService
            [$hasPermission, $errorMessage] = $this->adminAclService->hasPermission('export');
            if (!$hasPermission) {
                return response()->forbidden($errorMessage);
            }

            // Implementation for exporting users data
            // This would typically generate a CSV or Excel file with user data

            return response()->success(['url' => 'path/to/exported/file.csv'], 'Users data exported successfully');
        } catch (Exception $e) {
            return response()->serverError($e->getMessage());
        }
    }

    /**
     * Restore a soft-deleted user.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function restore(int $id): JsonResponse
    {
        try {
            // Check permission using AdminAclService
            [$hasPermission, $errorMessage] = $this->adminAclService->hasPermission('restore');
            if (!$hasPermission) {
                return response()->forbidden($errorMessage);
            }

            $user = User::withTrashed()->findOrFail($id);

            if (!$user->trashed()) {
                return response()->badRequest('User is not deleted');
            }

            $user->restore();

            return response()->success(new UserResource($user), 'User restored successfully');
        } catch (Exception $e) {
            return response()->serverError($e->getMessage());
        }
    }
}
