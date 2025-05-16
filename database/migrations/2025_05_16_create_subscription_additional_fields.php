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
            // Only add columns if they don't exist
            if (!Schema::hasColumn('subscriptions', 'subscriber_info')) {
                $table->json('subscriber_info')->nullable()->after('is_suspended');
            }
            
            if (!Schema::hasColumn('subscriptions', 'billing_info')) {
                $table->json('billing_info')->nullable()->after('subscriber_info');
            }
            
            if (!Schema::hasColumn('subscriptions', 'external_status')) {
                $table->string('external_status')->nullable()->after('billing_info');
            }
            
            if (!Schema::hasColumn('subscriptions', 'status_update_time')) {
                $table->timestamp('status_update_time')->nullable()->after('external_status');
            }
            
            if (!Schema::hasColumn('subscriptions', 'next_billing_date')) {
                $table->timestamp('next_billing_date')->nullable()->after('status_update_time');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn([
                'subscriber_info',
                'billing_info',
                'external_status',
                'status_update_time',
                'next_billing_date'
            ]);
        });
    }
};
