<?php
// =====================================================================
// app/Models/BlogCategory.php
// =====================================================================

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BlogCategory extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = ['name', 'slug', 'color', 'display_order'];

    public function posts()
    {
        return $this->hasMany(Post::class, 'category_id');
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('display_order')->orderBy('name');
    }
}