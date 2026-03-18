<?php
// app/Models/ArticleCategory.php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ArticleCategory extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'name',
        'slug',
        'icon',
        'description',
        'display_order',
    ];

    // ── Relations ─────────────────────────────────────────────────────────────

    public function articles()
    {
        return $this->hasMany(Article::class, 'category_id');
    }

    public function publishedArticles()
    {
        return $this->hasMany(Article::class, 'category_id')
                    ->where('is_published', true)
                    ->whereNull('deleted_at');
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeOrdered($query)
    {
        return $query->orderBy('display_order')->orderBy('name');
    }
}