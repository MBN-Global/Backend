<?php
// app/Http/Resources/ArticleResource.php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ArticleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $userId = $request->user()?->id;

        return [
            'id'                  => $this->id,
            'category_id'         => $this->category_id,
            'author_id'           => $this->author_id,

            // Content
            'title'               => $this->title,
            'slug'                => $this->slug,
            'description'         => $this->description,
            'content'             => $this->content,
            'cover_image_url'     => $this->cover_image_url,

            // Metadata
            'difficulty'          => $this->difficulty,
            'estimated_read_time' => $this->estimated_read_time,
            'target_audience'     => $this->target_audience,

            // Rich blocs JSON — retournés tels quels (déjà castés en array)
            'related_links'       => $this->related_links,
            'attachments'         => $this->attachments,
            'checklist'           => $this->checklist,
            'timeline'            => $this->timeline,
            'costs'               => $this->costs,

            // Versioning
            'version'             => $this->version,
            'changelog'           => $this->changelog,
            'last_reviewed_at'    => $this->last_reviewed_at,

            // Stats
            'views_count'         => $this->views_count,
            'helpful_count'       => $this->helpful_count,
            'comments_count'      => $this->comments_count,

            // Status
            'is_published'        => $this->is_published,
            'is_featured'         => $this->is_featured,

            // Computed — utile côté frontend pour le bouton "Utile"
            'has_voted_helpful'   => $userId ? $this->hasUserVoted($userId) : false,

            // Timestamps
            'created_at'          => $this->created_at,
            'updated_at'          => $this->updated_at,

            // Relations chargées conditionnellement
            'category' => $this->whenLoaded('category',
                fn() => new ArticleCategoryResource($this->category)
            ),

            'author' => $this->whenLoaded('author', fn() => [
                'id'         => $this->author->id,
                'name'       => $this->author->name,
                'role'       => $this->author->role,
                'avatar_url' => $this->author->info?->avatar_url,
            ]),
        ];
    }
}