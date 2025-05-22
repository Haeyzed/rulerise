<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Service class for Access Control List operations
 */
class ACLService
{
    /**
     * Cache TTL in seconds (24 hours)
     */
    const CACHE_TTL = 86400;

    /**
     * Check if a user has access to a resource
     *
     * @param User $user
     * @param string $permission
     * @return bool
     */
    public function hasAccess(User $user, string $permission): bool
    {
        // Admin role has access to everything
        if ($user->hasRole('admin')) {
            return true;
        }

        // Check if user has the specific permission
        return $user->hasPermission($permission);
    }

    /**
     * Assign a role to a user
     * This will remove any existing roles first since a user can only have one role
     *
     * @param User $user
     * @param string $roleName
     * @return User
     */
    public function assignRole(User $user, string $roleName): User
    {
        // Remove all existing roles first
        $user->roles()->detach();

        // Assign the new role
        $user->assignRole($roleName);

        // Clear user permissions cache
        $this->clearUserPermissionsCache($user);

        return $user;
    }

    /**
     * Assign multiple permissions to a user
     *
     * @param User $user
     * @param array $permissionNames
     * @return User
     */
    public function assignPermissions(User $user, array $permissionNames): User
    {
        $user->givePermissionTo($permissionNames);

        // Clear user permissions cache
        $this->clearUserPermissionsCache($user);

        return $user;
    }

    /**
     * Remove permissions from a user
     *
     * @param User $user
     * @param array $permissionNames
     * @return User
     */
    public function removePermissions(User $user, array $permissionNames): User
    {
        $user->revokePermissionTo($permissionNames);

        // Clear user permissions cache
        $this->clearUserPermissionsCache($user);

        return $user;
    }

    /**
     * Sync permissions for a user
     *
     * @param User $user
     * @param array $permissionNames
     * @return User
     */
    public function syncPermissions(User $user, array $permissionNames): User
    {
        $user->syncPermissions($permissionNames);

        // Clear user permissions cache
        $this->clearUserPermissionsCache($user);

        return $user;
    }

    /**
     * Sync permissions for a role
     *
     * @param string $roleName
     * @param array $permissionNames
     * @return Role
     */
    public function syncRolePermissions(string $roleName, array $permissionNames): Role
    {
        $role = Role::findByName($roleName, 'api');
        $role->syncPermissions($permissionNames);

        // Clear role permissions cache
        $this->clearRolePermissionsCache($role);

        return $role;
    }

    /**
     * Get all available permissions
     *
     * @return array
     */
    public function getAllPermissions(): array
    {
        return Cache::remember('all_permissions', self::CACHE_TTL, function () {
            return Permission::all()->toArray();
        });
    }

    /**
     * Get all available roles
     *
     * @return array
     */
    public function getAllRoles(): array
    {
        return Cache::remember('all_roles', self::CACHE_TTL, function () {
            return Role::all()->toArray();
        });
    }

    /**
     * Create a new permission
     *
     * @param string $name
     * @param string|null $description
     * @return Permission
     */
    public function createPermission(string $name, ?string $description = null): Permission
    {
        $permission = Permission::create([
            'name' => $name,
            'description' => $description,
            'guard_name' => 'api'
        ]);

        // Clear permissions cache
        Cache::forget('all_permissions');

        return $permission;
    }

    /**
     * Create a new role
     *
     * @param string $name
     * @param string|null $description
     * @param array $permissions
     * @return Role
     */
    /**
     * Create a new role
     *
     * @param string $name
     * @param string|null $description
     * @param array $permissions
     * @return Role
     */
    public function createRole(string $name, ?string $description = null, array $permissions = []): Role
    {
        $role = Role::create([
            'name' => $name,
            'description' => $description,
            'guard_name' => 'api',
        ]);

        // Assign permissions
        if (!empty($permissions)) {
            $role->syncPermissions($permissions);
            $this->clearRolePermissionsCache($role);
        }

        // Clear cached roles
        Cache::forget('all_roles');

        return $role;
    }

    /**
     * Update an existing role's name, description, and permissions
     *
     * @param Role $role
     * @param string|null $name
     * @param string|null $description
     * @param array|null $permissions
     * @return Role
     */
    public function updateRole(Role $role, ?string $name = null, ?string $description = null, ?array $permissions = null): Role
    {
        if ($name !== null) {
            $role->name = $name;
        }

        if ($description !== null) {
            $role->description = $description;
        }

        $role->save();

        if (is_array($permissions)) {
            $role->syncPermissions($permissions);
            $this->clearRolePermissionsCache($role);
        }

        // Clear cached roles
        Cache::forget('all_roles');

        return $role;
    }


    /**
     * Clear user permissions cache
     *
     * @param User $user
     * @return void
     */
    private function clearUserPermissionsCache(User $user): void
    {
        Cache::forget('user_permissions_' . $user->id);
    }

    /**
     * Clear role permissions cache
     *
     * @param Role $role
     * @return void
     */
    private function clearRolePermissionsCache(Role $role): void
    {
        Cache::forget('role_permissions_' . $role->id);
    }
}
