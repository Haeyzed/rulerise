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
        // Delete all existing permissions
        Permission::query()->delete();

        // Define generic permissions
        $permissions = [
            'view',
            'create',
            'update',
            'delete',
            'export',
            'restore',
        ];

        // Create fresh permissions
        foreach ($permissions as $permission) {
            Permission::create([
                'name' => $permission,
                'guard_name' => 'api',
            ]);
        }
    }
}
