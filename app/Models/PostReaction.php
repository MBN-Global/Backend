<?php
// =====================================================================
// app/Models/PostReaction.php
// =====================================================================

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PostReaction extends Model
{
    public $timestamps  = false;
    public $incrementing = false;

    protected $fillable = ['post_id', 'user_id', 'type'];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    const TYPES = ['like', 'useful', 'bravo'];

    const LABELS = [
        'like'   => '👍',
        'useful' => '💡',
        'bravo'  => '👏',
    ];

    // Colonnes compteur correspondantes sur Post
    const COUNTERS = [
        'like'   => 'likes_count',
        'useful' => 'useful_count',
        'bravo'  => 'bravo_count',
    ];

    public function post()
    {
        return $this->belongsTo(Post::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}