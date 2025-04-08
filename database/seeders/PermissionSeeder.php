<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run(): void
    {
        // Define permissions by module
        $permissions = [
            // User management
            'view_users',
            'create_users',
            'edit_users',
            'delete_users',

            // Role management
            'view_roles',
            'create_roles',
            'edit_roles',
            'delete_roles',

            // Candidate management
            'view_candidates',
            'edit_candidates',
            'delete_candidates',
            'moderate_candidates',

            // Employer management
            'view_employers',
            'edit_employers',
            'delete_employers',
            'moderate_employers',

            // Job management
            'view_jobs',
            'create_jobs',
            'edit_jobs',
            'delete_jobs',
            'moderate_jobs',

            // Job category management
            'view_job_categories',
            'create_job_categories',
            'edit_job_categories',
            'delete_job_categories',

            // Subscription plan management
            'view_subscription_plans',
            'create_subscription_plans',
            'edit_subscription_plans',
            'delete_subscription_plans',

            // Website customization
            'view_website_customizations',
            'edit_website_customizations',

            // General settings
            'view_general_settings',
            'edit_general_settings',
        ];

        // Create permissions
        foreach ($permissions as $permission) {
            Permission::create(['name' => $permission, 'guard_name' => 'api']);
        }
    }
}
