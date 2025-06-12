<?php

namespace Database\Seeders;

use App\Models\GeneralSetting;
use Illuminate\Database\Seeder;

class GeneralSettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run(): void
    {
        $settings = [
            // Account Settings
            [
                'key' => 'account_delete_candidate_account',
                'value' => false,
                'description' => 'Allow candidates to delete their account'
            ],
            [
                'key' => 'account_delete_employer_account',
                'value' => false,
                'description' => 'Allow employers to delete their account'
            ],
            [
                'key' => 'account_email_notification',
                'value' => true,
                'description' => 'Allow users to receive email notification'
            ],
            [
                'key' => 'account_email_verification',
                'value' => true,
                'description' => 'Send email to users after registration'
            ],

            // Security Settings
            [
                'key' => 'security_session_lifetime',
                'value' => 120,
                'description' => 'Session lifetime in minutes'
            ],
            [
                'key' => 'security_max_login_attempts',
                'value' => 5,
                'description' => 'Maximum login attempts before lockout'
            ],
            [
                'key' => 'security_password_expiry_days',
                'value' => 90,
                'description' => 'Password expiry in days (0 for never)'
            ],
            [
                'key' => 'security_require_strong_password',
                'value' => true,
                'description' => 'Require strong passwords'
            ],

            // Currency Configuration
            [
                'key' => 'default_currency',
                'value' => 'USD',
                'description' => 'Default currency for the application'
            ],

            // OldSubscription Settings
            [
                'key' => 'subscription_trial_days',
                'value' => 14,
                'description' => 'Trial period in days'
            ],
            [
                'key' => 'subscription_allow_cancellation',
                'value' => true,
                'description' => 'Allow users to cancel subscription'
            ],

            // Notification Settings
            [
                'key' => 'notification_admin_new_user',
                'value' => true,
                'description' => 'Notify admin when new user registers'
            ],
            [
                'key' => 'notification_admin_new_employer',
                'value' => true,
                'description' => 'Notify admin when new employer registers'
            ],
            [
                'key' => 'notification_admin_new_candidate',
                'value' => true,
                'description' => 'Notify admin when new candidate registers'
            ],

            // CV Package Settings
            [
                'key' => 'cv_package_basic_enabled',
                'value' => true,
                'description' => 'Enable Basic CV Package'
            ],
            [
                'key' => 'cv_package_pro_enabled',
                'value' => true,
                'description' => 'Enable Pro CV Package'
            ],
            [
                'key' => 'cv_package_enterprise_enabled',
                'value' => true,
                'description' => 'Enable Enterprise CV Package'
            ],
            [
                'key' => 'cv_package_20_resume_enabled',
                'value' => true,
                'description' => 'Enable 20 Resume Package'
            ],
            [
                'key' => 'cv_package_unlimited_resume_enabled',
                'value' => true,
                'description' => 'Enable Unlimited Resume Access'
            ],

            // System Settings
            [
                'key' => 'system_site_name',
                'value' => 'Job Portal',
                'description' => 'Site name'
            ],
            [
                'key' => 'system_contact_email',
                'value' => 'contact@example.com',
                'description' => 'Contact email address'
            ],
            [
                'key' => 'system_support_phone',
                'value' => '+1234567890',
                'description' => 'Support phone number'
            ],
            [
                'key' => 'system_timezone',
                'value' => 'UTC',
                'description' => 'System timezone'
            ],
            [
                'key' => 'system_date_format',
                'value' => 'Y-m-d',
                'description' => 'Date format'
            ],
            [
                'key' => 'system_time_format',
                'value' => 'H:i',
                'description' => 'Time format'
            ],

            // SEO Settings
            [
                'key' => 'seo_meta_title',
                'value' => 'Job Portal - Find Your Dream Job',
                'description' => 'Default meta title'
            ],
            [
                'key' => 'seo_meta_description',
                'value' => 'Find your dream job or hire the perfect candidate with our job portal.',
                'description' => 'Default meta description'
            ],
            [
                'key' => 'seo_meta_keywords',
                'value' => 'jobs, career, employment, hiring, recruitment',
                'description' => 'Default meta keywords'
            ],
        ];

        foreach ($settings as $setting) {
            GeneralSetting::updateOrCreate(
                ['key' => $setting['key']],
                [
                    'value' => $setting['value'],
                    'description' => $setting['description']
                ]
            );
        }

        $this->command->info('General settings seeded successfully!');
    }
}
