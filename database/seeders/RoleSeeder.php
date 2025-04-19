<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run(): void
    {
        // Create roles
        $adminRole = Role::create([
            'name' => 'admin',
            'description' => 'Administrator with full access',
            'guard_name' => 'api'
        ]);

        $candidateRole = Role::create([
            'name' => 'candidate',
            'description' => 'Job seeker',
            'guard_name' => 'api'
        ]);

        $employerRole = Role::create([
            'name' => 'employer',
            'description' => 'Employer who can post jobs',
            'guard_name' => 'api'
        ]);

        $moderatorRole = Role::create([
            'name' => 'moderator',
            'description' => 'Content moderator',
            'guard_name' => 'api'
        ]);

        $employerStaffRole = Role::create([
            'name' => 'employer_staff',
            'description' => 'Employer staff',
            'guard_name' => 'api'
        ]);

        // Get all employer permissions
        $employerRole = Role::query()->where('name', 'employer')->first();
        if ($employerRole) {
            $permissions = $employerRole->permissions;

            // Assign the same permissions to employer_staff
            $employerStaffRole->syncPermissions($permissions);
        }

        // Assign permissions to roles

        // Admin gets all permissions
        $adminRole->givePermissionTo(Permission::all());

        // Moderator permissions
        $moderatorRole->givePermissionTo([
            'view_candidates',
            'moderate_candidates',
            'view_employers',
            'moderate_employers',
            'view_jobs',
            'moderate_jobs',
            'view_job_categories',
        ]);

        // Candidate and Employer roles don't need explicit permissions
        // as they will be handled by middleware and policies
    }
}
