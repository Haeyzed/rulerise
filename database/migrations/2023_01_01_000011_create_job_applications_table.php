<?php

use App\Models\Job;
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
        Schema::create('resumes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_id')->constrained()->onDelete('cascade');
            $table->string('name')->nullable();
            $table->string('document');
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
        });

        Schema::create('job_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Job::class)->constrained()->onDelete('cascade');
            $table->foreignId('candidate_id')->constrained()->onDelete('cascade');
            $table->foreignId('resume_id')->nullable()->constrained()->onDelete('set null');
            $table->text('cover_letter')->nullable();
            $table->string('status')->default('unsorted');
            $table->text('employer_notes')->nullable();
            $table->enum('apply_via', ['custom_cv', 'profile_cv'])->default('profile_cv');
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
        Schema::dropIfExists('resumes');
        Schema::dropIfExists('job_applications');
    }
};
