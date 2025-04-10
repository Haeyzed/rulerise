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
        Schema::create('job_listings', function (Blueprint $table) {
            $table->id();

            // Foreign keys
            $table->foreignId('employer_id')->constrained()->onDelete('cascade');
            $table->foreignId('job_category_id')->nullable()->constrained()->onDelete('cascade');

            // Basic info
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('short_description')->nullable(); // Optional if not explicitly required
            $table->text('description');
            $table->string('job_type');
            $table->string('employment_type')->nullable();
            $table->string('job_industry')->nullable();
            $table->string('location');
            $table->string('job_level')->nullable();
            $table->string('experience_level');
            $table->json('skills_required')->nullable();


            // Salary
            $table->decimal('salary', 10, 2)->nullable();
            $table->string('salary_payment_mode')->nullable();

            // Application
            $table->string('email_to_apply')->nullable();
            $table->boolean('easy_apply')->default(false);
            $table->boolean('email_apply')->default(false);
            $table->integer('vacancies')->default(1);
            $table->date('deadline')->nullable();

            // Flags
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_approved')->default(false);

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('job_listings');
    }
};
