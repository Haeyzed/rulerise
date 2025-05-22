<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Clear existing permissions and roles
        Role::query()->delete();

        // Create roles
        $superAdmin = Role::create([
            'name' => 'super_admin',
            'description' => 'Super Administrator with all permissions',
            'guard_name' => 'api',
        ]);

        $admin = Role::create([
            'name' => 'admin',
            'description' => 'Administrator with full access',
            'guard_name' => 'api',
        ]);

        $manager = Role::create([
            'name' => 'manager',
            'description' => 'Manager with limited access',
            'guard_name' => 'api',
        ]);

        $recruiter = Role::create([
            'name' => 'recruiter',
            'description' => 'Recruiter for hiring operations',
            'guard_name' => 'api',
        ]);

        // Assign all permissions to super admin and admin
        $allPermissions = Permission::all();
        $superAdmin->syncPermissions($allPermissions);
        $admin->syncPermissions($allPermissions);

        // Manager and recruiter can have customized permissions assigned later
    }
}
