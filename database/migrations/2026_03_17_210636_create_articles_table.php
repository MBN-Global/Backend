<?php
// database/migrations/xxxx_xx_xx_create_articles_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('articles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('category_id')->nullable()->constrained('article_categories')->nullOnDelete();
            $table->foreignUuid('author_id')->constrained('users')->cascadeOnDelete();

            // Content
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('description')->nullable();   // meta description
            $table->longText('content');                 // HTML (Tiptap output)

            // Cover image
            $table->string('cover_image_url')->nullable();

            // Metadata
            $table->enum('difficulty', ['easy', 'medium', 'complex'])->default('easy');
            $table->integer('estimated_read_time')->nullable();  // minutes
            $table->json('target_audience')->nullable();         // ["Étudiants EU", "Hors EU"]

            // Rich blocs JSON
            $table->json('related_links')->nullable();   // [{title, url, type}]
            $table->json('attachments')->nullable();     // [{title, description, file_url, type}]
            $table->json('checklist')->nullable();       // {title, items: [{text, is_optional}]}
            $table->json('timeline')->nullable();        // [{step, title, description, estimated_duration}]
            $table->json('costs')->nullable();           // [{item, amount, currency, is_variable}]

            // Versioning
            $table->integer('version')->default(1);
            $table->text('changelog')->nullable();
            $table->timestamp('last_reviewed_at')->nullable();

            // Stats
            $table->unsignedInteger('views_count')->default(0);
            $table->unsignedInteger('helpful_count')->default(0);
            $table->unsignedInteger('comments_count')->default(0);

            // Status
            $table->boolean('is_published')->default(false);
            $table->boolean('is_featured')->default(false);

            $table->softDeletes();
            $table->timestamps();

            // Indexes
            $table->index('category_id');
            $table->index('author_id');
            $table->index('slug');
            $table->index('is_published');
            $table->index('difficulty');
            $table->index('is_featured');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('articles');
    }
};