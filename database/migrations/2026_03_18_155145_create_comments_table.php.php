<?php
// database/migrations/xxxx_create_comments_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('post_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('author_id')->constrained('users')->cascadeOnDelete();

            // Thread : null = commentaire racine, uuid = réponse à un commentaire
            $table->foreignUuid('parent_id')
                  ->nullable()
                  ->constrained('comments')
                  ->cascadeOnDelete();

            $table->text('content');
            $table->unsignedInteger('replies_count')->default(0);

            $table->softDeletes(); // garde la structure du thread si commentaire supprimé
            $table->timestamps();

            $table->index('post_id');
            $table->index('author_id');
            $table->index('parent_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comments');
    }
};