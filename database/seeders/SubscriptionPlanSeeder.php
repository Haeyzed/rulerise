<?php

namespace Database\Seeders;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Seeder;

class SubscriptionPlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Clear existing plans if needed
        SubscriptionPlan::query()->delete();

        // Create Basic CV Package
        SubscriptionPlan::query()->create([
            'name' => 'Basic CV Package',
            'description' => 'Access to basic CV search and candidate profiles',
            'price' => 1999,
            'currency' => 'USD',
            'duration_days' => 30, // 1 month
            'job_posts' => 5,
            'featured_jobs' => 1,
            'cv_downloads' => 20,
            'can_view_candidates' => true,
            'can_create_candidate_pools' => false,
            'is_active' => true,
        ]);

        // Create Pro CV Package
        SubscriptionPlan::query()->create([
            'name' => 'Pro CV Package',
            'description' => 'Enhanced access to premium CV search and candidate profiles',
            'price' => 4999,
            'currency' => 'USD',
            'duration_days' => 30, // 1 month
            'job_posts' => 15,
            'featured_jobs' => 5,
            'cv_downloads' => 50,
            'can_view_candidates' => true,
            'can_create_candidate_pools' => true,
            'is_active' => true,
        ]);

        // Create Enterprise CV Package
        SubscriptionPlan::query()->create([
            'name' => 'Enterprise CV Package',
            'description' => 'Unlimited access to all CV search features and candidate profiles',
            'price' => 9999,
            'currency' => 'USD',
            'duration_days' => 365, // 1 year
            'job_posts' => 50,
            'featured_jobs' => 20,
            'cv_downloads' => 200,
            'can_view_candidates' => true,
            'can_create_candidate_pools' => true,
            'is_active' => true,
        ]);

        // Create a Free Trial Package (optional)
        SubscriptionPlan::query()->create([
            'name' => 'Free Trial',
            'description' => 'Try our platform for 7 days with limited features',
            'price' => 0,
            'currency' => 'USD',
            'duration_days' => 7, // 1 week
            'job_posts' => 1,
            'featured_jobs' => 0,
            'cv_downloads' => 3,
            'can_view_candidates' => true,
            'can_create_candidate_pools' => false,
            'is_active' => true,
        ]);
    }
}
