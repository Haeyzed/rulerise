<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            // Add missing columns if they don't exist
            if (!Schema::hasColumn('subscription_plans', 'external_paypal_id')) {
                $table->string('external_paypal_id')->nullable()->after('metadata');
            }
            if (!Schema::hasColumn('subscription_plans', 'external_stripe_id')) {
                $table->string('external_stripe_id')->nullable()->after('external_paypal_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->dropColumn(['external_paypal_id', 'external_stripe_id']);
        });
    }
};
