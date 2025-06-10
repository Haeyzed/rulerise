<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employers', function (Blueprint $table) {
            if (!Schema::hasColumn('employers', 'stripe_customer_id')) {
                $table->string('stripe_customer_id')->nullable()->after('company_facebook_url');
            }
            if (!Schema::hasColumn('employers', 'paypal_customer_id')) {
                $table->string('paypal_customer_id')->nullable()->after('stripe_customer_id');
            }
            if (!Schema::hasColumn('employers', 'has_used_trial')) {
                $table->boolean('has_used_trial')->default(false)->after('paypal_customer_id');
            }
            if (!Schema::hasColumn('employers', 'trial_used_at')) {
                $table->timestamp('trial_used_at')->nullable()->after('has_used_trial');
            }
        });
    }

    public function down(): void
    {
        Schema::table('employers', function (Blueprint $table) {
            $table->dropColumn(['stripe_customer_id', 'paypal_customer_id', 'has_used_trial', 'trial_used_at']);
        });
    }
};
