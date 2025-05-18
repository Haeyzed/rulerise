<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Hash;

/**
 * Service class for user management operations
 */
class UserManagementService
{
    /**
     * The ACL service instance.
     *
     * @var ACLService
     */
    protected ACLService $aclService;

    /**
     * Create a new service instance.
     *
     * @param ACLService $aclService
     * @return void
     */
    public function __construct(ACLService $aclService)
    {
        $this->aclService = $aclService;
    }

    /**
     * Create a new user
     *
     * @param array $data
     * @return User
     */
    public function createUser(array $data): User
    {
        // Create the user
        $user = User::create([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'user_type' => $data['user_type'] ?? 'admin',
            'is_active' => $data['is_active'] ?? true,
        ]);

        // Assign role if provided
        if (!empty($data['role'])) {
            $this->aclService->assignRole($user, $data['role']);
        }

        // Assign permissions if provided
        if (!empty($data['permissions'])) {
            $this->aclService->assignPermissions($user, $data['permissions']);
        }

        return $user;
    }

    /**
     * Update an existing user
     *
     * @param User $user
     * @param array $data
     * @return User
     */
    public function updateUser(User $user, array $data): User
    {
        // Update basic user data
        if (isset($data['first_name'])) {
            $user->first_name = $data['first_name'];
        }
        
        if (isset($data['last_name'])) {
            $user->last_name = $data['last_name'];
        }
        
        if (isset($data['email'])) {
            $user->email = $data['email'];
        }
        
        if (isset($data['is_active'])) {
            $user->is_active = $data['is_active'];
        }

        // Update password if provided
        if (!empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }

        $user->save();

        // Update role if provided
        if (isset($data['role'])) {
            $this->aclService->assignRole($user, $data['role']);
        }

        // Update permissions if provided
        if (isset($data['permissions'])) {
            $this->aclService->syncPermissions($user, $data['permissions']);
        }

        return $user;
    }

    /**
     * Get users by role
     *
     * @param string $role
     * @return Collection
     */
    public function getUsersByRole(string $role): Collection
    {
        return User::role($role)->get();
    }

    /**
     * Get users by permission
     *
     * @param string $permission
     * @return Collection
     */
    public function getUsersByPermission(string $permission): Collection
    {
        return User::permission($permission)->get();
    }

    /**
     * Set user active status
     *
     * @param User $user
     * @param bool $isActive
     * @return User
     */
    public function setUserActiveStatus(User $user, bool $isActive): User
    {
        $user->is_active = $isActive;
        $user->save();
        
        return $user;
    }

    /**
     * Delete a user
     *
     * @param User $user
     * @return bool
     */
    public function deleteUser(User $user): bool
    {
        return $user->delete();
    }
}
