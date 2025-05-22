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

        // Create 20 Resume Package (One-time)
        SubscriptionPlan::query()->create([
            'name' => '20 Resume Package',
            'description' => 'One-time purchase of 20 resume views with 7-day free trial, no expiration',
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
            'features' => json_encode([
                'Access to 20 full resumes',
                'Basic job posting (5 posts)',
                '1 featured job posting',
                'Standard candidate search',
                'Email support',
                'Job alerts',
                '7-day free trial'
            ])
        ]);

        // Create Monthly Unlimited Resumes Subscription
        SubscriptionPlan::query()->create([
            'name' => 'Unlimited Resume Access',
            'description' => 'Monthly subscription with unlimited resume views, 7-day free trial, and enhanced features',
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
            'features' => json_encode([
                'Unlimited resume access',
                'Advanced candidate search filters',
                'Premium job posting (15 posts)',
                '5 featured job postings',
                'Priority support',
                'Advanced analytics',
                'Candidate recommendations',
                'Custom job alerts',
                '7-day free trial'
            ])
        ]);

//        // Create Basic CV Package (keeping from original but updated)
//        SubscriptionPlan::query()->create([
//            'name' => 'Basic CV Package',
//            'description' => 'Access to basic CV search and candidate profiles',
//            'price' => 19.99,
//            'currency' => 'CAD',
//            'duration_days' => 30, // 1 month
//            'job_posts_limit' => 5,
//            'featured_jobs_limit' => 1,
//            'resume_views_limit' => 20,
//            'job_alerts' => false,
//            'candidate_search' => true,
//            'resume_access' => true,
//            'company_profile' => true,
//            'support_level' => 'basic',
//            'is_active' => true,
//            'is_featured' => false,
//            'payment_type' => SubscriptionPlan::PAYMENT_TYPE_RECURRING,
//            'features' => json_encode([
//                'Basic CV search',
//                'Limited candidate profiles',
//                'Standard job listings',
//                'Email support'
//            ])
//        ]);
//
//        // Create Pro CV Package (keeping from original but updated)
//        SubscriptionPlan::query()->create([
//            'name' => 'Pro CV Package',
//            'description' => 'Enhanced access to premium CV search and candidate profiles',
//            'price' => 49.99,
//            'currency' => 'CAD',
//            'duration_days' => 30, // 1 month
//            'job_posts_limit' => 15,
//            'featured_jobs_limit' => 5,
//            'resume_views_limit' => 50,
//            'job_alerts' => true,
//            'candidate_search' => true,
//            'resume_access' => true,
//            'company_profile' => true,
//            'support_level' => 'standard',
//            'is_active' => true,
//            'is_featured' => true,
//            'payment_type' => SubscriptionPlan::PAYMENT_TYPE_RECURRING,
//            'features' => json_encode([
//                'Advanced CV search',
//                'Full candidate profiles',
//                'Featured job listings',
//                'Priority email support',
//                'Basic analytics',
//                'Candidate pools'
//            ])
//        ]);
//
//        // Create Enterprise CV Package (one-time annual package)
//        SubscriptionPlan::query()->create([
//            'name' => 'Enterprise CV Package',
//            'description' => 'Unlimited access to all CV search features and candidate profiles',
//            'price' => 999.99,
//            'currency' => 'CAD',
//            'duration_days' => 365, // 1 year
//            'job_posts_limit' => 50,
//            'featured_jobs_limit' => 20,
//            'resume_views_limit' => 200,
//            'job_alerts' => true,
//            'candidate_search' => true,
//            'resume_access' => true,
//            'company_profile' => true,
//            'support_level' => 'premium',
//            'is_active' => true,
//            'is_featured' => true,
//            'payment_type' => SubscriptionPlan::PAYMENT_TYPE_ONE_TIME,
//            'features' => json_encode([
//                'Unlimited CV search',
//                'Premium candidate profiles',
//                'Priority job listings',
//                'Dedicated account manager',
//                'Advanced analytics',
//                'Custom reporting',
//                'API access',
//                'Bulk actions',
//                'Team collaboration tools'
//            ])
//        ]);
    }
}
