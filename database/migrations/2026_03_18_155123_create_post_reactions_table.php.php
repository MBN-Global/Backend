<?php
// database/migrations/xxxx_create_post_reactions_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('post_reactions', function (Blueprint $table) {
            $table->foreignUuid('post_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['like', 'useful', 'bravo']);
            $table->timestamp('created_at')->useCurrent();

            // Un user ne peut avoir qu'UNE réaction par post
            $table->primary(['post_id', 'user_id']);
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_reactions');
    }
};