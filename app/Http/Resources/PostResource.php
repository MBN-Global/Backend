<?php
// =====================================================================
// app/Http/Resources/PostResource.php
// =====================================================================
 
namespace App\Http\Resources;
 
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
 
class PostResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $userId = $request->user()?->id;
 
        return [
            'id'              => $this->id,
            'author_id'       => $this->author_id,
            'category_id'     => $this->category_id,
 
            // Content
            'title'           => $this->title,
            'slug'            => $this->slug,
            'excerpt'         => $this->excerpt,
            'content'         => $this->content,
            'cover_image_url' => $this->cover_image_url,
            'tags'            => $this->tags ?? [],
 
            // Stats
            'views_count'    => $this->views_count,
            'comments_count' => $this->comments_count,
            'likes_count'    => $this->likes_count,
            'useful_count'   => $this->useful_count,
            'bravo_count'    => $this->bravo_count,
 
            // Réaction de l'utilisateur connecté
            'user_reaction'  => $userId ? $this->getUserReaction($userId) : null,
 
            // Propriétaire ?
            'is_own_post'    => $userId === $this->author_id,
 
            // Status
            'status'         => $this->status,
            'published_at'   => $this->published_at,
            'created_at'     => $this->created_at,
            'updated_at'     => $this->updated_at,
 
            // Relations
            'author'   => $this->whenLoaded('author', fn() => [
                'id'         => $this->author->id,
                'name'       => $this->author->name,
                'role'       => $this->author->role,
                'avatar_url' => $this->author->info?->avatar_url,
            ]),
            'category' => $this->whenLoaded('category',
                fn() => new BlogCategoryResource($this->category)
            ),
        ];
    }
}