<?php

// =====================================================================
// app/Models/Post.php
// =====================================================================

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Post extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'author_id', 'category_id',
        'title', 'slug', 'excerpt', 'content', 'cover_image_url',
        'tags', 'status', 'published_at',
        'views_count', 'comments_count',
        'likes_count', 'useful_count', 'bravo_count',
    ];

    protected $casts = [
        'tags'         => 'array',
        'published_at' => 'datetime',
        'views_count'  => 'integer',
        'comments_count' => 'integer',
        'likes_count'  => 'integer',
        'useful_count' => 'integer',
        'bravo_count'  => 'integer',
    ];

    // ── Boot ─────────────────────────────────────────────────────────────────

    protected static function booted(): void
    {
        static::creating(function (Post $post) {
            if (empty($post->slug)) {
                $post->slug = static::generateUniqueSlug($post->title);
            }
            // Auto-excerpt depuis le contenu si absent
            if (empty($post->excerpt) && $post->content) {
                $post->excerpt = Str::limit(strip_tags($post->content), 160);
            }
            if ($post->status === 'published' && !$post->published_at) {
                $post->published_at = now();
            }
        });

        static::updating(function (Post $post) {
            if ($post->isDirty('status') && $post->status === 'published' && !$post->published_at) {
                $post->published_at = now();
            }
            if ($post->isDirty('content') && empty($post->excerpt)) {
                $post->excerpt = Str::limit(strip_tags($post->content), 160);
            }
        });
    }

    // ── Slug ─────────────────────────────────────────────────────────────────

    public static function generateUniqueSlug(string $title, ?string $excludeId = null): string
    {
        $base  = Str::slug($title);
        $slug  = $base;
        $count = 1;

        while (
            static::where('slug', $slug)
                  ->when($excludeId, fn($q) => $q->where('id', '!=', $excludeId))
                  ->withTrashed()
                  ->exists()
        ) {
            $slug = "{$base}-{$count}";
            $count++;
        }

        return $slug;
    }

    // ── Relations ─────────────────────────────────────────────────────────────

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function category()
    {
        return $this->belongsTo(BlogCategory::class, 'category_id');
    }

    public function comments()
    {
        return $this->hasMany(Comment::class);
    }

    public function rootComments()
    {
        return $this->hasMany(Comment::class)->whereNull('parent_id')->latest();
    }

    public function reactions()
    {
        return $this->hasMany(PostReaction::class);
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopePublished($query)
    {
        return $query->where('status', 'published')
                     ->whereNotNull('published_at')
                     ->where('published_at', '<=', now());
    }

    public function scopeSearch($query, string $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('title',   'LIKE', "%{$term}%")
              ->orWhere('excerpt', 'LIKE', "%{$term}%")
              ->orWhere('content', 'LIKE', "%{$term}%");
        });
    }

    public function scopeByCategory($query, string $slug)
    {
        return $query->whereHas('category', fn($q) => $q->where('slug', $slug));
    }

    public function scopeByTag($query, string $tag)
    {
        return $query->whereJsonContains('tags', $tag);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Réaction de l'utilisateur connecté sur ce post
     */
    public function getUserReaction(?string $userId): ?string
    {
        if (!$userId) return null;
        return $this->reactions()->where('user_id', $userId)->value('type');
    }
}