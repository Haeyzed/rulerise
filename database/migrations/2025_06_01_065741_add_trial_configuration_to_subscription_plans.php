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
        Schema::table('subscription_plans', function (Blueprint $table) {
            // Trial configuration
            $table->boolean('has_trial')->default(true)->after('payment_type');
            $table->integer('trial_period_days')->default(7)->after('has_trial');

            // Payment gateway configuration
            $table->json('payment_gateway_config')->nullable()->after('trial_period_days');

            // Billing cycle configuration for more flexibility
            $table->string('interval_unit')->default('DAY')->after('payment_gateway_config'); // DAY, WEEK, MONTH, YEAR
            $table->integer('interval_count')->default(1)->after('interval_unit');
            $table->integer('total_cycles')->default(0)->after('interval_count'); // 0 = infinite

            // Additional metadata
            $table->json('metadata')->nullable()->after('total_cycles');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->dropColumn([
                'has_trial',
                'trial_period_days',
                'payment_gateway_config',
                'interval_unit',
                'interval_count',
                'total_cycles',
                'metadata'
            ]);
        });
    }
};
