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
            // Add subscription_id field for recurring subscriptions
            $table->string('subscription_id')->nullable()->after('payment_reference');

            // Rename transaction_reference to payment_reference if needed
            if (Schema::hasColumn('subscriptions', 'transaction_reference') && !Schema::hasColumn('subscriptions', 'payment_reference')) {
                $table->renameColumn('transaction_reference', 'payment_reference');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn('subscription_id');

            // Revert rename if needed
            if (Schema::hasColumn('subscriptions', 'payment_reference') && !Schema::hasColumn('subscriptions', 'transaction_reference')) {
                $table->renameColumn('payment_reference', 'transaction_reference');
            }
        });
    }
};
