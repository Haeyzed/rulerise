<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            // Rename existing columns to match new schema
            $table->renameColumn('job_posts', 'job_posts_limit');
            $table->renameColumn('featured_jobs', 'featured_jobs_limit');
            $table->renameColumn('cv_downloads', 'resume_views_limit');
            
            // Add new columns
            $table->boolean('job_alerts')->default(0)->after('resume_views_limit');
            $table->boolean('candidate_search')->default(0)->after('job_alerts');
            $table->boolean('resume_access')->default(0)->after('candidate_search');
            $table->boolean('company_profile')->default(1)->after('resume_access');
            $table->string('support_level')->default('basic')->after('company_profile');
            $table->boolean('is_featured')->default(0)->after('is_active');
            $table->json('features')->nullable()->after('is_featured');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            // Rename columns back to original names
            $table->renameColumn('job_posts_limit', 'job_posts');
            $table->renameColumn('featured_jobs_limit', 'featured_jobs');
            $table->renameColumn('resume_views_limit', 'cv_downloads');
            
            // Drop new columns
            $table->dropColumn([
                'job_alerts',
                'candidate_search',
                'resume_access',
                'company_profile',
                'support_level',
                'is_featured',
                'features'
            ]);
        });
    }
};