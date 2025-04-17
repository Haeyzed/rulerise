<?php

use App\Enums\BlogPostStatusEnum;
use App\Models\BlogPost;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('blog_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(User::class)->constrained()->onDelete('cascade');
            $table->string('title');
            $table->string('subtitle')->nullable();
            $table->text('body');
            $table->string('banner_image')->nullable();
            $table->string('category')->nullable();
            $table->string('caption')->nullable();
            $table->string('status')->default(BlogPostStatusEnum::DRAFT->value);
            $table->integer('views')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('blog_post_images', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(BlogPost::class)->constrained()->onDelete('cascade');
            $table->string('image_path');
            $table->integer('order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('blog_post_images');
        Schema::dropIfExists('blog_posts');
    }
};
