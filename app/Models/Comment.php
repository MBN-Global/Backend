<?php

// =====================================================================
// app/Models/Comment.php
// =====================================================================

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Comment extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = ['post_id', 'author_id', 'parent_id', 'content'];

    protected $casts = [
        'replies_count' => 'integer',
    ];

    // ── Boot ─────────────────────────────────────────────────────────────────

    protected static function booted(): void
    {
        // Incrémenter comments_count sur le post
        static::created(function (Comment $comment) {
            $comment->post()->incrementQuietly('comments_count');
            // Incrémenter replies_count sur le parent si réponse
            if ($comment->parent_id) {
                Comment::where('id', $comment->parent_id)->incrementQuietly('replies_count');
            }
        });

        // Décrémenter à la suppression
        static::deleted(function (Comment $comment) {
            $comment->post()->decrementQuietly('comments_count');
            if ($comment->parent_id) {
                Comment::where('id', $comment->parent_id)->decrementQuietly('replies_count');
            }
        });
    }

    // ── Relations ─────────────────────────────────────────────────────────────

    public function post()
    {
        return $this->belongsTo(Post::class);
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function parent()
    {
        return $this->belongsTo(Comment::class, 'parent_id');
    }

    public function replies()
    {
        return $this->hasMany(Comment::class, 'parent_id')->latest();
    }
}