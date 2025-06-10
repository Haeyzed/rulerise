<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2);
            $table->string('currency', 3)->default('CAD');
            $table->integer('duration_days')->nullable(); // null for one-time payments
            $table->integer('job_posts_limit')->default(0);
            $table->integer('featured_jobs_limit')->default(0);
            $table->integer('resume_views_limit')->default(0);
            $table->boolean('job_alerts')->default(false);
            $table->boolean('candidate_search')->default(false);
            $table->boolean('resume_access')->default(false);
            $table->boolean('company_profile')->default(false);
            $table->string('support_level')->default('basic');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->enum('payment_type', ['one_time', 'recurring'])->default('one_time');
            $table->boolean('has_trial')->default(false);
            $table->integer('trial_period_days')->default(0);
            $table->enum('interval_unit', ['DAY', 'WEEK', 'MONTH', 'YEAR'])->default('MONTH');
            $table->integer('interval_count')->default(1);
            $table->integer('total_cycles')->default(0); // 0 = infinite
            $table->json('features')->nullable();
            $table->json('payment_gateway_config')->nullable();
            $table->json('metadata')->nullable();
            $table->string('external_paypal_id')->nullable();
            $table->string('external_stripe_id')->nullable();
            $table->timestamps();
            
            $table->index(['is_active', 'payment_type']);
            $table->index('price');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_plans');
    }
};
