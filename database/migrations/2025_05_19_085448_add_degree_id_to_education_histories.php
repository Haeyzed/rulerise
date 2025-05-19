<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('education_histories', function (Blueprint $table) {
            // Add degree_id column
            $table->foreignId('degree_id')->nullable()->after('candidate_id')
                ->constrained('degrees')->nullOnDelete();

            // Keep the original degree column for backward compatibility
            // but mark it as nullable since we'll be using degree_id going forward
            $table->string('degree')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('education_histories', function (Blueprint $table) {
            $table->dropForeign(['degree_id']);
            $table->dropColumn('degree_id');

            // Restore the original degree column as required
            $table->string('degree')->nullable(false)->change();
        });
    }
};
