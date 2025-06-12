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
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employer_id')->constrained()->onDelete('cascade');
            $table->foreignId('plan_id')->constrained()->onDelete('cascade');
            $table->string('subscription_id')->unique();
            $table->enum('payment_provider', ['stripe', 'paypal']);
            $table->string('status');//, ['active', 'canceled', 'expired', 'past_due', 'incomplete']);
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('CAD');
            $table->timestamp('start_date');
            $table->timestamp('end_date')->nullable();
            $table->timestamp('next_billing_date')->nullable();
            $table->timestamp('trial_start_date')->nullable();
            $table->timestamp('trial_end_date')->nullable();
            $table->boolean('is_trial')->default(false);
            $table->boolean('trial_ended')->default(false);
            $table->integer('cv_downloads_left')->default(0);
            $table->timestamp('canceled_at')->nullable();
            $table->json('metadata')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['employer_id', 'is_active']);
            $table->index(['status', 'next_billing_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
