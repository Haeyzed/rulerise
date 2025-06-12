<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        // Clear existing plans if needed
        Plan::query()->delete();
        $plans = [
            [
                'name' => 'Basic',
                'slug' => 'basic',
                'description' => 'Perfect for small businesses',
                'price' => 29.99,
                'currency' => 'CAD',
                'billing_cycle' => 'monthly',
                'job_posts_limit' => 5,
                'featured_jobs_limit' => 1,
                'candidate_database_access' => false,
                'analytics_access' => false,
                'priority_support' => false,
                'resume_views_limit' => 20,
                'features' => [
                    '5 job posts per month',
                    '1 featured job',
                    'Basic support',
                    'Standard job visibility'
                ],
                'is_active' => true,
                'is_popular' => false,
                'trial_days' => 7,
                'has_trial' => true,
            ],
            [
                'name' => 'Professional',
                'slug' => 'professional',
                'description' => 'Most popular for growing companies',
                'price' => 79.99,
                'currency' => 'CAD',
                'billing_cycle' => 'monthly',
                'job_posts_limit' => 20,
                'featured_jobs_limit' => 5,
                'candidate_database_access' => true,
                'resume_views_limit' => 999999,
                'analytics_access' => true,
                'priority_support' => false,
                'features' => [
                    '20 job posts per month',
                    '5 featured jobs',
                    'Candidate database access',
                    'Analytics dashboard',
                    'Priority job visibility'
                ],
                'is_active' => true,
                'is_popular' => true,
                'trial_days' => 14,
                'has_trial' => true,
            ],
            [
                'name' => 'Enterprise',
                'slug' => 'enterprise',
                'description' => 'For large organizations',
                'price' => 199.99,
                'currency' => 'CAD',
                'billing_cycle' => 'monthly',
                'job_posts_limit' => null, // unlimited
                'featured_jobs_limit' => 20,
                'candidate_database_access' => true,
                'analytics_access' => true,
                'priority_support' => true,
                'resume_views_limit' => 0,
                'features' => [
                    'Unlimited job posts',
                    '20 featured jobs',
                    'Full candidate database access',
                    'Advanced analytics',
                    'Priority support',
                    'Custom branding'
                ],
                'is_active' => true,
                'is_popular' => false,
                'trial_days' => 30,
                'has_trial' => true,
            ],
            [
                'name' => 'Single Job Post',
                'slug' => 'single-job',
                'description' => 'One-time job posting',
                'price' => 49.99,
                'currency' => 'CAD',
                'billing_cycle' => 'one_time',
                'job_posts_limit' => 1,
                'featured_jobs_limit' => 0,
                'candidate_database_access' => false,
                'analytics_access' => false,
                'priority_support' => false,
                'resume_views_limit' => 20,
                'features' => [
                    '1 job post',
                    '30 days visibility',
                    'Basic support'
                ],
                'is_active' => true,
                'is_popular' => false,
                'trial_days' => 0,
                'has_trial' => false,
            ],
        ];

        foreach ($plans as $planData) {
            Plan::create($planData);
        }
    }
}
