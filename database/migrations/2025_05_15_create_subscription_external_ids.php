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
            $table->string('external_paypal_id')->nullable()->after('is_featured');
            $table->string('external_stripe_id')->nullable()->after('external_paypal_id');
        });

        Schema::table('employers', function (Blueprint $table) {
            $table->string('stripe_customer_id')->nullable()->after('is_featured');
            $table->string('paypal_customer_id')->nullable()->after('stripe_customer_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->dropColumn(['external_paypal_id', 'external_stripe_id']);
        });

        Schema::table('employers', function (Blueprint $table) {
            $table->dropColumn(['stripe_customer_id', 'paypal_customer_id']);
        });
    }
};