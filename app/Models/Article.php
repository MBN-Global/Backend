<?php
// app/Models/Article.php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Article extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'category_id',
        'author_id',
        'title',
        'slug',
        'description',
        'content',
        'cover_image_url',
        'difficulty',
        'estimated_read_time',
        'target_audience',
        'related_links',
        'attachments',
        'checklist',
        'timeline',
        'costs',
        'version',
        'changelog',
        'last_reviewed_at',
        'views_count',
        'helpful_count',
        'comments_count',
        'is_published',
        'is_featured',
    ];

    protected $casts = [
        'target_audience'     => 'array',
        'related_links'       => 'array',
        'attachments'         => 'array',
        'checklist'           => 'array',
        'timeline'            => 'array',
        'costs'               => 'array',
        'version'             => 'integer',
        'views_count'         => 'integer',
        'helpful_count'       => 'integer',
        'comments_count'      => 'integer',
        'is_published'        => 'boolean',
        'is_featured'         => 'boolean',
        'last_reviewed_at'    => 'datetime',
    ];

    // ── Boot : auto-slug ──────────────────────────────────────────────────────

    protected static function booted(): void
    {
        static::creating(function (Article $article) {
            if (empty($article->slug)) {
                $article->slug = static::generateUniqueSlug($article->title);
            }
        });

        static::updating(function (Article $article) {
            // Incrémenter la version et mettre à jour last_reviewed_at
            // seulement si le contenu ou le titre change
            if ($article->isDirty(['title', 'content'])) {
                $article->version         = $article->version + 1;
                $article->last_reviewed_at = now();
            }
        });
    }

    // ── Slug helper ───────────────────────────────────────────────────────────

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

    public function category()
    {
        return $this->belongsTo(ArticleCategory::class, 'category_id');
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * Utilisateurs ayant voté "utile"
     */
    public function helpfulVoters()
    {
        return $this->belongsToMany(User::class, 'article_helpful');
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    public function scopeByCategory($query, string $categorySlug)
    {
        return $query->whereHas('category', fn($q) => $q->where('slug', $categorySlug));
    }

    public function scopeSearch($query, string $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('title',       'LIKE', "%{$term}%")
              ->orWhere('description', 'LIKE', "%{$term}%")
              ->orWhere('content',    'LIKE', "%{$term}%");
        });
    }

    // ── Accessors ─────────────────────────────────────────────────────────────

    /**
     * Vérifie si l'utilisateur connecté a déjà voté "utile"
     * Appelé depuis ArticleResource avec $this->whenLoaded ou manuellement
     */
    public function hasUserVoted(?string $userId): bool
    {
        if (!$userId) return false;
        return $this->helpfulVoters()->where('user_id', $userId)->exists();
    }
}