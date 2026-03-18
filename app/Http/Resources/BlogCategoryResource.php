<?php
// =====================================================================
// app/Http/Resources/BlogCategoryResource.php
// =====================================================================
 
namespace App\Http\Resources;
 
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
 
class BlogCategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'name'          => $this->name,
            'slug'          => $this->slug,
            'color'         => $this->color,
            'display_order' => $this->display_order,
            'posts_count'   => $this->whenCounted('posts'),
        ];
    }
}