<?php
// database/migrations/2026_03_10_171636_create_companies_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('siret')->unique()->nullable();
            $table->string('logo_url')->nullable();
            $table->string('website')->nullable();
            $table->string('linkedin_url')->nullable();
            $table->text('description')->nullable();
            $table->string('industry')->nullable();
            
            // ✅ Fix: Utiliser des valeurs sans caractères spéciaux
            $table->enum('size', [
                '1-10',
                '11-50',
                '51-200',
                '201-500',
                '501-1000',
                '1001+' // ✅ Changé de '1000+' à '1001+'
            ])->nullable();
            
            $table->string('headquarters_city')->nullable();
            $table->string('headquarters_country')->default('France');
            $table->boolean('is_partner')->default(false);
            $table->boolean('is_verified')->default(false);
            $table->timestamp('verified_at')->nullable();
            $table->integer('jobs_posted')->default(0);
            $table->integer('active_jobs')->default(0);
            $table->timestamps();
            
            // Indexes
            $table->index('is_partner');
            $table->index('is_verified');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};