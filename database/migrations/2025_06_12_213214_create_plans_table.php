<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2);
            $table->string('currency')->default('CAD');
            $table->enum('billing_cycle', ['monthly', 'yearly', 'one_time']);
            $table->integer('job_posts_limit')->nullable();
            $table->integer('featured_jobs_limit')->default(0);
            $table->boolean('candidate_database_access')->default(false);
            $table->boolean('analytics_access')->default(false);
            $table->boolean('priority_support')->default(false);
            $table->integer('resume_views_limit')->default(0);
            $table->json('features')->nullable();
            $table->string('stripe_price_id')->nullable();
            $table->string('paypal_plan_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_popular')->default(false);
            $table->integer('trial_days')->default(0);
            $table->boolean('has_trial')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
