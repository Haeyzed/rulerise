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
        Schema::create('candidates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('title')->nullable();
            $table->text('bio')->nullable();
            $table->string('current_position')->nullable();
            $table->string('current_company')->nullable();
            $table->string('location')->nullable();
            $table->decimal('expected_salary', 10, 2)->nullable();
            $table->string('currency')->default('USD');
            $table->enum('job_type', ['full_time', 'part_time', 'contract', 'internship', 'remote'])->nullable();
            $table->boolean('is_available')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_verified')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('candidates');
    }
};
