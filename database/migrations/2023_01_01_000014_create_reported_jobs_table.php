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
        Schema::create('reported_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Job::class)->constrained()->onDelete('cascade');
            $table->foreignId('candidate_id')->constrained()->onDelete('cascade');
            $table->string('reason')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_resolved')->default(false);
            $table->text('admin_notes')->nullable();
            $table->timestamps();

            $table->unique(['job_id', 'candidate_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('reported_jobs');
    }
};
