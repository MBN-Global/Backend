<?php
// database/migrations/xxxx_create_blog_categories_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blog_categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->string('color', 7)->default('#0038BC'); // hex color
            $table->integer('display_order')->default(0);
            $table->timestamps();

            $table->index('slug');
            $table->index('display_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_categories');
    }
};