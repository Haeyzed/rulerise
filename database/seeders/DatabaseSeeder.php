<?php

namespace Database\Seeders;

use App\Models\Degree;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run(): void
    {
        $this->call([
//            GeneralSettingsSeeder::class,
//            DegreeSeeder::class,
//            WebsiteSeeder::class,
//            PermissionSeeder::class,
//            RoleSeeder::class,
//            AdminSeeder::class,
//            JobCategorySeeder::class,
            SubscriptionPlanSeeder::class,
            PlanSeeder::class,
//            SubscriptionSeeder::class,
        ]);
    }
}
