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
        Schema::create('employers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('company_name');
            $table->string('company_email')->nullable();
            $table->string('company_logo')->nullable();
            $table->text('company_description')->nullable();
            $table->string('company_industry')->nullable();
            $table->string('company_size')->nullable();
            $table->date('company_founded')->nullable();
            $table->string('company_country')->nullable();
            $table->string('company_state')->nullable();
            $table->string('company_address')->nullable();
            $table->string('company_phone_number')->nullable();
            $table->string('company_website')->nullable();
            $table->json('company_benefits')->nullable();
            $table->boolean('is_verified')->default(false);
            $table->boolean('is_featured')->default(false);
            $table->timestamps();
        });

        // Create company benefits table
//        Schema::create('company_benefits', function (Blueprint $table) {
//            $table->id();
//            $table->foreignId('employer_id')->constrained()->onDelete('cascade');
//            $table->string('benefit');
//            $table->timestamps();
//        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
//        Schema::dropIfExists('company_benefits');
        Schema::dropIfExists('employers');
    }
};
