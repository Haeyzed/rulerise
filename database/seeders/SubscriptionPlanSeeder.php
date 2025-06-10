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

        SubscriptionPlan::query()->delete();

        // 20 Resume Package (One-time) with trial
        SubscriptionPlan::create([
            'name' => '20 Resume Package',
            'description' => 'One-time purchase of 20 resume views with 1-day trial period, no expiration',
            'price' => 1.00,
            'currency' => 'CAD',
            'duration_days' => null,
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
            'trial_period_days' => 1,
            'interval_unit' => SubscriptionPlan::INTERVAL_UNIT_YEAR,
            'interval_count' => 1,
            'total_cycles' => 1,
            'payment_gateway_config' => [
                'paypal' => [
                    'mode' => env('PAYPAL_MODE', 'sandbox'),
                    'client_id' => env('PAYPAL_CLIENT_ID'),
                    'client_secret' => env('PAYPAL_CLIENT_SECRET'),
                ],
                'stripe' => [
                    'publishable_key' => env('STRIPE_KEY'),
                    'secret_key' => env('STRIPE_SECRET'),
                    'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
                ]
            ],
            'features' => [
                'Access to 20 full resumes',
                'Basic job posting (5 posts)',
                '1 featured job posting',
                'Standard candidate search',
                'Email support',
                'Job alerts',
                '1-day free trial'
            ],
            'metadata' => [
                'category' => 'one_time',
                'target_audience' => 'small_business',
                'recommended' => false,
                'popular' => false
            ]
        ]);

        // Unlimited Resume Access (Monthly Recurring) with trial
        SubscriptionPlan::create([
            'name' => 'Unlimited Resume Access',
            'description' => 'Monthly subscription with unlimited resume views, 1-day trial period, and enhanced features',
            'price' => 1.00,
            'currency' => 'CAD',
            'duration_days' => 30,
            'job_posts_limit' => 15,
            'featured_jobs_limit' => 5,
            'resume_views_limit' => 999999,
            'job_alerts' => true,
            'candidate_search' => true,
            'resume_access' => true,
            'company_profile' => true,
            'support_level' => 'premium',
            'is_active' => true,
            'is_featured' => true,
            'payment_type' => SubscriptionPlan::PAYMENT_TYPE_RECURRING,
            'has_trial' => true,
            'trial_period_days' => 1,
            'interval_unit' => SubscriptionPlan::INTERVAL_UNIT_MONTH,
            'interval_count' => 1,
            'total_cycles' => 0,
            'payment_gateway_config' => [
                'paypal' => [
                    'mode' => env('PAYPAL_MODE', 'sandbox'),
                    'client_id' => env('PAYPAL_CLIENT_ID'),
                    'client_secret' => env('PAYPAL_CLIENT_SECRET'),
                ],
                'stripe' => [
                    'publishable_key' => env('STRIPE_KEY'),
                    'secret_key' => env('STRIPE_SECRET'),
                    'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
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
                '1-day free trial'
            ],
            'metadata' => [
                'category' => 'recurring',
                'target_audience' => 'enterprise',
                'recommended' => true,
                'popular' => true
            ]
        ]);
    }
}
