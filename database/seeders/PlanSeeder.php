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
                'name' => 'Unlimited Resume Access',
                'slug' => 'unlimited-resume-access',
                'description' => 'Monthly subscription with unlimited resume views, configurable trial period, and enhanced features',
                'price' => 1,
                'currency' => 'CAD',
                'billing_cycle' => 'monthly',
                'job_posts_limit' => 5,
                'featured_jobs_limit' => 1,
                'candidate_search' => true,
                'analytics_access' => true,
                'resume_access' => true,
                'priority_support' => true,
                'resume_views_limit' => 999999,
                'features' => [
                    '5 job posts per month',
                    '1 featured job',
                    'Basic support',
                    'Standard job visibility'
                ],
                'is_active' => true,
                'is_popular' => false,
                'trial_days' => 1,
                'has_trial' => true,
            ],
            [
                'name' => '20 Resume Package',
                'slug' => '20-resume-package',
                'description' => 'One-time purchase of 20 resume views with configurable trial period, no expiration',
                'price' => 1,
                'currency' => 'CAD',
                'billing_cycle' => 'one_time',
                'job_posts_limit' => 1,
                'featured_jobs_limit' => 0,
                'candidate_search' => true,
                'analytics_access' => false,
                'resume_access' => false,
                'priority_support' => false,
                'resume_views_limit' => 20,
                'features' => [
                    '1 job post',
                    '30 days visibility',
                    'Basic support'
                ],
                'is_active' => true,
                'is_popular' => false,
                'trial_days' => 1,
                'has_trial' => false,
            ],
        ];

        foreach ($plans as $planData) {
            Plan::create($planData);
        }
    }
}
