<?php
// database/migrations/xxxx_xx_xx_create_article_helpful_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('article_helpful', function (Blueprint $table) {
            $table->foreignUuid('article_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->primary(['article_id', 'user_id']); // clé composite → 1 vote par user par article
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('article_helpful');
    }
};