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
                'paypal' => [
                    'setup_fee_failure_action' => 'CONTINUE',
                    'payment_failure_threshold' => 3
                ],
                'stripe' => [
                    'payment_method_types' => ['card'],
                    'allow_promotion_codes' => true
                ]
            ],
            'features' => [
                'Access to 20 full resumes',
                'Basic job posting (5 posts)',
                '1 featured job posting',
                'Standard candidate search',
                'Email support',
                'Job alerts',
                'Configurable trial period'
            ],
            'metadata' => [
                'category' => 'one_time',
                'target_audience' => 'small_business',
                'recommended' => false
            ]
        ]);

        // Create Monthly Unlimited Resumes Subscription with configurable trial
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
                'paypal' => [
                    'auto_bill_outstanding' => true,
                    'setup_fee_failure_action' => 'CONTINUE',
                    'payment_failure_threshold' => 3
                ],
                'stripe' => [
                    'payment_method_types' => ['card'],
                    'allow_promotion_codes' => true,
                    'automatic_tax' => ['enabled' => true]
                ]
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
            'metadata' => [
                'category' => 'recurring',
                'target_audience' => 'enterprise',
                'recommended' => true
            ]
        ]);

        // Create Weekly Basic Plan with short trial
//        SubscriptionPlan::query()->create([
//            'name' => 'Weekly Basic',
//            'description' => 'Weekly subscription for small businesses with basic features',
//            'price' => 50.00,
//            'currency' => 'CAD',
//            'duration_days' => 7, // Weekly
//            'job_posts_limit' => 3,
//            'featured_jobs_limit' => 0,
//            'resume_views_limit' => 10,
//            'job_alerts' => true,
//            'candidate_search' => false,
//            'resume_access' => true,
//            'company_profile' => false,
//            'support_level' => 'basic',
//            'is_active' => true,
//            'is_featured' => false,
//            'payment_type' => SubscriptionPlan::PAYMENT_TYPE_RECURRING,
//            'has_trial' => true,
//            'trial_period_days' => 3, // Short trial for weekly plan
//            'interval_unit' => SubscriptionPlan::INTERVAL_UNIT_WEEK,
//            'interval_count' => 1,
//            'total_cycles' => 0, // Infinite cycles
//            'payment_gateway_config' => [
//                'paypal' => [
//                    'auto_bill_outstanding' => true,
//                    'setup_fee_failure_action' => 'CANCEL',
//                    'payment_failure_threshold' => 2
//                ],
//                'stripe' => [
//                    'payment_method_types' => ['card'],
//                    'allow_promotion_codes' => false
//                ]
//            ],
//            'features' => [
//                'Access to 10 resumes',
//                'Basic job posting (3 posts)',
//                'Standard support',
//                'Job alerts',
//                'Short trial period'
//            ],
//            'metadata' => [
//                'category' => 'recurring',
//                'target_audience' => 'startup',
//                'recommended' => false
//            ]
//        ]);

        // Create Premium Annual Plan with extended trial
//        SubscriptionPlan::query()->create([
//            'name' => 'Annual Premium',
//            'description' => 'Annual subscription with all features and extended trial period',
//            'price' => 2400.00,
//            'currency' => 'CAD',
//            'duration_days' => 365, // Annual
//            'job_posts_limit' => 100,
//            'featured_jobs_limit' => 20,
//            'resume_views_limit' => 999999, // Unlimited
//            'job_alerts' => true,
//            'candidate_search' => true,
//            'resume_access' => true,
//            'company_profile' => true,
//            'support_level' => 'enterprise',
//            'is_active' => true,
//            'is_featured' => true,
//            'payment_type' => SubscriptionPlan::PAYMENT_TYPE_RECURRING,
//            'has_trial' => true,
//            'trial_period_days' => 30, // Extended trial for annual plan
//            'interval_unit' => SubscriptionPlan::INTERVAL_UNIT_YEAR,
//            'interval_count' => 1,
//            'total_cycles' => 0, // Infinite cycles
//            'payment_gateway_config' => [
//                'paypal' => [
//                    'auto_bill_outstanding' => true,
//                    'setup_fee_failure_action' => 'CONTINUE',
//                    'payment_failure_threshold' => 5
//                ],
//                'stripe' => [
//                    'payment_method_types' => ['card', 'us_bank_account'],
//                    'allow_promotion_codes' => true,
//                    'automatic_tax' => ['enabled' => true],
//                    'invoice_creation' => ['enabled' => true]
//                ]
//            ],
//            'features' => [
//                'Unlimited resume access',
//                'Advanced candidate search',
//                'Premium job posting (100 posts)',
//                '20 featured job postings',
//                'Enterprise support',
//                'Advanced analytics & reporting',
//                'API access',
//                'Custom integrations',
//                'Dedicated account manager',
//                'Extended trial period'
//            ],
//            'metadata' => [
//                'category' => 'recurring',
//                'target_audience' => 'enterprise',
//                'recommended' => true,
//                'discount_percentage' => 20 // 20% discount compared to monthly
//            ]
//        ]);

        // Create No-Trial Plan for testing
//        SubscriptionPlan::query()->create([
//            'name' => 'Instant Access',
//            'description' => 'Monthly plan with no trial period for immediate access',
//            'price' => 150.00,
//            'currency' => 'CAD',
//            'duration_days' => 30,
//            'job_posts_limit' => 8,
//            'featured_jobs_limit' => 2,
//            'resume_views_limit' => 50,
//            'job_alerts' => true,
//            'candidate_search' => true,
//            'resume_access' => true,
//            'company_profile' => true,
//            'support_level' => 'standard',
//            'is_active' => true,
//            'is_featured' => false,
//            'payment_type' => SubscriptionPlan::PAYMENT_TYPE_RECURRING,
//            'has_trial' => false, // No trial period
//            'trial_period_days' => 0,
//            'interval_unit' => SubscriptionPlan::INTERVAL_UNIT_MONTH,
//            'interval_count' => 1,
//            'total_cycles' => 0,
//            'payment_gateway_config' => [
//                'paypal' => [
//                    'auto_bill_outstanding' => true,
//                    'setup_fee_failure_action' => 'CONTINUE',
//                    'payment_failure_threshold' => 3
//                ],
//                'stripe' => [
//                    'payment_method_types' => ['card'],
//                    'allow_promotion_codes' => false
//                ]
//            ],
//            'features' => [
//                'Access to 50 resumes',
//                'Standard job posting (8 posts)',
//                '2 featured job postings',
//                'Standard support',
//                'Job alerts',
//                'No trial period - instant access'
//            ],
//            'metadata' => [
//                'category' => 'recurring',
//                'target_audience' => 'medium_business',
//                'recommended' => false
//            ]
//        ]);
    }
}
