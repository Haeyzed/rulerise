<?php

namespace Database\Seeders;

use App\Models\Employer;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SubscriptionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Clear existing subscriptions if needed
        Subscription::query()->delete();

        // Get all employers
        $employers = Employer::all();
        
        if ($employers->isEmpty()) {
            $this->command->info('No employers found. Please run EmployerSeeder first.');
            return;
        }

        // Get all subscription plans
        $plans = SubscriptionPlan::all();
        
        if ($plans->isEmpty()) {
            $this->command->info('No subscription plans found. Please run SubscriptionPlanSeeder first.');
            return;
        }

        // Payment methods
        $paymentMethods = ['credit_card', 'paypal', 'bank_transfer', 'stripe'];

        // Create active subscriptions for some employers
        foreach ($employers as $index => $employer) {
            // Assign different plans to different employers
            $plan = $plans[$index % count($plans)];
            
            // Create an active subscription
            $startDate = Carbon::now()->subDays(rand(1, 30));
            $endDate = $startDate->copy()->addDays($plan->duration_days);
            
            Subscription::create([
                'employer_id' => $employer->id,
                'subscription_plan_id' => $plan->id,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'amount_paid' => $plan->price,
                'currency' => $plan->currency,
                'payment_method' => $paymentMethods[array_rand($paymentMethods)],
                'transaction_id' => 'txn_' . Str::random(10),
                'payment_reference' => 'ref_' . Str::random(8),
                'subscription_id' => 'sub_' . Str::random(10),
                'receipt_path' => null,
                'job_posts_left' => $plan->job_posts_limit,
                'featured_jobs_left' => $plan->featured_jobs_limit,
                'cv_downloads_left' => $plan->resume_views_limit,
                'is_active' => true,
            ]);
            
            // For some employers, also create expired subscriptions
            if ($index % 3 == 0) {
                $oldStartDate = Carbon::now()->subDays(rand(60, 120));
                $oldEndDate = $oldStartDate->copy()->addDays($plan->duration_days);
                
                // Get a different plan for the expired subscription
                $oldPlan = $plans[($index + 1) % count($plans)];
                
                Subscription::create([
                    'employer_id' => $employer->id,
                    'subscription_plan_id' => $oldPlan->id,
                    'start_date' => $oldStartDate,
                    'end_date' => $oldEndDate,
                    'amount_paid' => $oldPlan->price,
                    'currency' => $oldPlan->currency,
                    'payment_method' => $paymentMethods[array_rand($paymentMethods)],
                    'transaction_id' => 'txn_' . Str::random(10),
                    'payment_reference' => 'ref_' . Str::random(8),
                    'subscription_id' => 'sub_' . Str::random(10),
                    'receipt_path' => null,
                    'job_posts_left' => 0, // Used all posts
                    'featured_jobs_left' => 0, // Used all featured posts
                    'cv_downloads_left' => 0, // Used all CV downloads
                    'is_active' => false,
                ]);
            }
            
            // For some employers, create cancelled subscriptions
            if ($index % 5 == 0) {
                $cancelledStartDate = Carbon::now()->subDays(rand(15, 45));
                $cancelledEndDate = $cancelledStartDate->copy()->addDays($plan->duration_days);
                
                // Get a different plan for the cancelled subscription
                $cancelledPlan = $plans[($index + 2) % count($plans)];
                
                Subscription::create([
                    'employer_id' => $employer->id,
                    'subscription_plan_id' => $cancelledPlan->id,
                    'start_date' => $cancelledStartDate,
                    'end_date' => $cancelledEndDate,
                    'amount_paid' => $cancelledPlan->price,
                    'currency' => $cancelledPlan->currency,
                    'payment_method' => $paymentMethods[array_rand($paymentMethods)],
                    'transaction_id' => 'txn_' . Str::random(10),
                    'payment_reference' => 'ref_' . Str::random(8),
                    'subscription_id' => 'sub_' . Str::random(10),
                    'receipt_path' => null,
                    'job_posts_left' => rand(0, $cancelledPlan->job_posts_limit),
                    'featured_jobs_left' => rand(0, $cancelledPlan->featured_jobs_limit),
                    'cv_downloads_left' => rand(0, $cancelledPlan->resume_views_limit),
                    'is_active' => false,
                ]);
            }
        }
        
        // Create a few free trial subscriptions
        $freePlan = SubscriptionPlan::where('name', 'Free Trial')->first();
        
        if ($freePlan) {
            // Get 3 random employers who don't have active subscriptions
            $employersWithoutSubs = Employer::whereDoesntHave('subscriptions', function ($query) {
                $query->where('is_active', true);
            })->inRandomOrder()->take(3)->get();
            
            foreach ($employersWithoutSubs as $employer) {
                $startDate = Carbon::now()->subDays(rand(1, 5));
                $endDate = $startDate->copy()->addDays($freePlan->duration_days);
                
                Subscription::create([
                    'employer_id' => $employer->id,
                    'subscription_plan_id' => $freePlan->id,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'amount_paid' => 0.00,
                    'currency' => $freePlan->currency,
                    'payment_method' => 'free_trial',
                    'transaction_id' => 'trial_' . Str::random(10),
                    'payment_reference' => null,
                    'subscription_id' => 'trial_' . Str::random(10),
                    'receipt_path' => null,
                    'job_posts_left' => $freePlan->job_posts_limit,
                    'featured_jobs_left' => $freePlan->featured_jobs_limit,
                    'cv_downloads_left' => $freePlan->resume_views_limit,
                    'is_active' => true,
                ]);
            }
        }
        
        $this->command->info('Subscriptions seeded successfully!');
    }
}
