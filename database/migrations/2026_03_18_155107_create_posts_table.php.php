<?php
// database/migrations/xxxx_create_posts_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('author_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('category_id')->nullable()->constrained('blog_categories')->nullOnDelete();

            // Content
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('excerpt')->nullable();  // résumé auto ou manuel
            $table->longText('content');            // HTML Tiptap
            $table->string('cover_image_url')->nullable();

            // Tags (JSON array of strings)
            $table->json('tags')->nullable();

            // Stats
            $table->unsignedInteger('views_count')->default(0);
            $table->unsignedInteger('comments_count')->default(0);
            // Compteurs par type de réaction
            $table->unsignedInteger('likes_count')->default(0);
            $table->unsignedInteger('useful_count')->default(0);
            $table->unsignedInteger('bravo_count')->default(0);

            // Status
            $table->enum('status', ['draft', 'published'])->default('draft');
            $table->timestamp('published_at')->nullable();

            $table->softDeletes();
            $table->timestamps();

            $table->index('author_id');
            $table->index('category_id');
            $table->index('slug');
            $table->index('status');
            $table->index('published_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};