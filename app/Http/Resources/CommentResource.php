<?php
// =====================================================================
// app/Http/Resources/CommentResource.php
// =====================================================================
 
namespace App\Http\Resources;
 
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
 
class CommentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $userId = $request->user()?->id;
 
        return [
            'id'            => $this->id,
            'post_id'       => $this->post_id,
            'parent_id'     => $this->parent_id,
            'content'       => $this->deleted_at ? null : $this->content, // masquer contenu si supprimé
            'is_deleted'    => !is_null($this->deleted_at),
            'replies_count' => $this->replies_count,
            'is_own'        => $userId === $this->author_id,
            'created_at'    => $this->created_at,
            'updated_at'    => $this->updated_at,
 
            'author' => $this->whenLoaded('author', fn() => [
                'id'         => $this->author->id,
                'name'       => $this->author->name,
                'role'       => $this->author->role,
                'avatar_url' => $this->author->info?->avatar_url,
            ]),
 
            // Replies chargées (niveau 1 seulement)
            'replies' => $this->whenLoaded('replies',
                fn() => CommentResource::collection($this->replies)
            ),
        ];
    }
}