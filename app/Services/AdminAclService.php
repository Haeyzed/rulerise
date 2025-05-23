<?php

namespace App\Services;

use Illuminate\Support\Facades\Auth;

class AdminAclService
{
    /**
     * Check if the authenticated user has permission to perform an action.
     * Users with 'superadmin' role automatically have all permissions.
     *
     * @param string $permission The permission to check
     * @param string $message Optional custom error message
     * @return array [bool $hasPermission, ?string $errorMessage]
     */
    public function hasPermission(string $permission): array
    {
        $user = Auth::user();

        // Superadmins have all permissions
        if ($user && $user->hasRole('super_admin')) {
            return [true, null];
        }

        // Check specific permission
        if ($user && $user->hasPermissionTo($permission)) {
            return [true, null];
        }

        // User doesn't have permission
        $errorMessage = "You do not have permission to {$this->getReadableAction($permission)}";
        return [false, $errorMessage];
    }

    /**
     * Convert permission name to a readable action description.
     *
     * @param string $permission
     * @return string
     */
    private function getReadableAction(string $permission): string
    {
        $actions = [
            'view' => 'view this resource',
            'create' => 'create new resources',
            'update' => 'update this resource',
            'delete' => 'delete this resource',
            'export' => 'export data',
            'restore' => 'restore deleted resources',
        ];

        return $actions[$permission] ?? "perform this action";
    }

    /**
     * Check if a role can be assigned to a user.
     * Prevents 'superadmin' role from being assigned during user creation.
     *
     * @param string $role
     * @return bool
     */
    public function canAssignRole(string $role): bool
    {
        // Prevent 'super_admin' role from being assigned
        if (strtolower($role) === 'super_admin') {
            return false;
        }

        return true;
    }
}
