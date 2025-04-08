<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run(): void
    {
        // Create admin user
        $admin = User::query()->create([
            'first_name' => 'Admin',
            'last_name' => 'User',
            'other_name' => null,
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'phone' => '08123456789',
            'phone_country_code' => '234',
            'country' => 'Nigeria',
            'state' => 'Lagos',
            'city' => 'Ikeja',
            'profile_picture' => null,
            'title' => 'Mr',
            'user_type' => 'admin',
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        // Assign admin role
        $admin->assignRole('admin');
    }
}
