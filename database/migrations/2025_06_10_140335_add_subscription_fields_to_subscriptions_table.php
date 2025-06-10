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
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->string('payment_type')->default('recurring')->after('is_active');
            $table->boolean('is_suspended')->default(false)->after('payment_type');
            $table->boolean('used_trial')->default(false)->after('is_suspended');
            $table->json('subscriber_info')->nullable()->after('used_trial');
            $table->json('billing_info')->nullable()->after('subscriber_info');
            $table->string('external_status')->nullable()->after('billing_info');
            $table->timestamp('status_update_time')->nullable()->after('external_status');
            $table->date('next_billing_date')->nullable()->after('status_update_time');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn([
                'payment_type',
                'is_suspended',
                'used_trial',
                'subscriber_info',
                'billing_info',
                'external_status',
                'status_update_time',
                'next_billing_date'
            ]);
        });
    }
};
