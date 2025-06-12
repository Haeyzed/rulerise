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

        // Create 20 Resume Package (One-time) with configurable trial
        SubscriptionPlan::query()->create([
            'name' => '20 Resume Package',
            'description' => 'One-time purchase of 20 resume views with configurable trial period, no expiration',
            'price' => 200.00,
            'currency' => 'CAD',
            'duration_days' => null, // No expiration for one-time purchases
            'job_posts_limit' => 5,
            'featured_jobs_limit' => 1,
            'resume_views_limit' => 20,
            'job_alerts' => true,
            'candidate_search' => true,
            'resume_access' => true,
            'company_profile' => true,
            'support_level' => 'standard',
            'is_active' => true,
            'is_featured' => false,
            'payment_type' => SubscriptionPlan::PAYMENT_TYPE_ONE_TIME,
            'has_trial' => true,
            'trial_period_days' => 7, // Configurable trial period
            'interval_unit' => SubscriptionPlan::INTERVAL_UNIT_YEAR,
            'interval_count' => 1,
            'total_cycles' => 1, // One-time payment
            'payment_gateway_config' => [
            ],
            'features' => [
                'Access to 20 full resumes',
                'Basic job posting (5 posts)',
                '1 featured job posting',
                'Standard candidate search',
                'Email support',
                'Job alerts',
                'Configurable trial period'
            ]
        ]);

        // Create Monthly Unlimited Resumes OldSubscription with configurable trial
        SubscriptionPlan::query()->create([
            'name' => 'Unlimited Resume Access',
            'description' => 'Monthly subscription with unlimited resume views, configurable trial period, and enhanced features',
            'price' => 300.00,
            'currency' => 'CAD',
            'duration_days' => 30, // Monthly
            'job_posts_limit' => 15,
            'featured_jobs_limit' => 5,
            'resume_views_limit' => 999999, // Effectively unlimited
            'job_alerts' => true,
            'candidate_search' => true,
            'resume_access' => true,
            'company_profile' => true,
            'support_level' => 'premium',
            'is_active' => true,
            'is_featured' => true,
            'payment_type' => SubscriptionPlan::PAYMENT_TYPE_RECURRING,
            'has_trial' => true,
            'trial_period_days' => 7, // Different trial period for this plan
            'interval_unit' => SubscriptionPlan::INTERVAL_UNIT_MONTH,
            'interval_count' => 1,
            'total_cycles' => 0, // Infinite cycles for recurring
            'payment_gateway_config' => [
            ],
            'features' => [
                'Unlimited resume access',
                'Advanced candidate search filters',
                'Premium job posting (15 posts)',
                '5 featured job postings',
                'Priority support',
                'Advanced analytics',
                'Candidate recommendations',
                'Custom job alerts',
                'Extended trial period'
            ],
        ]);
    }
}
