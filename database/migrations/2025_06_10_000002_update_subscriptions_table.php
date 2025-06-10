<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            // Add indexes for better performance
            $table->index(['employer_id', 'is_active']);
            $table->index(['subscription_id']);
            $table->index(['transaction_id']);
            $table->index(['external_status']);
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropIndex(['employer_id', 'is_active']);
            $table->dropIndex(['subscription_id']);
            $table->dropIndex(['transaction_id']);
            $table->dropIndex(['external_status']);
        });
    }
};
