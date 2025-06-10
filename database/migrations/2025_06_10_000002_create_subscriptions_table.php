<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employer_id')->constrained()->onDelete('cascade');
            $table->foreignId('subscription_plan_id')->constrained()->onDelete('cascade');
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->decimal('amount_paid', 10, 2)->default(0);
            $table->string('currency', 3)->default('CAD');
            $table->string('payment_method')->nullable(); // stripe, paypal
            $table->string('transaction_id')->nullable();
            $table->string('payment_reference')->nullable();
            $table->string('subscription_id')->nullable(); // External subscription ID
            $table->string('receipt_path')->nullable();
            $table->integer('job_posts_left')->default(0);
            $table->integer('featured_jobs_left')->default(0);
            $table->integer('cv_downloads_left')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_suspended')->default(false);
            $table->boolean('used_trial')->default(false);
            $table->enum('payment_type', ['one_time', 'recurring'])->default('one_time');
            $table->json('subscriber_info')->nullable();
            $table->json('billing_info')->nullable();
            $table->string('external_status')->nullable();
            $table->timestamp('status_update_time')->nullable();
            $table->date('next_billing_date')->nullable();
            $table->timestamps();
            
            $table->index(['employer_id', 'is_active']);
            $table->index('subscription_id');
            $table->index('transaction_id');
            $table->index('external_status');
            $table->index('payment_method');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
