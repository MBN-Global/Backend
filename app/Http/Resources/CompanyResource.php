<?php
// app/Http/Resources/CompanyResource.php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'siret' => $this->siret,
            'logo_url' => $this->logo_url ? url("storage/{$this->logo_url}") : null,
            'website' => $this->website,
            'linkedin_url' => $this->linkedin_url,
            'description' => $this->description,
            'industry' => $this->industry,
            'size' => $this->size,
            'headquarters_city' => $this->headquarters_city,
            'headquarters_country' => $this->headquarters_country,
            'is_partner' => $this->is_partner,
            'is_verified' => $this->is_verified,
            'verified_at' => $this->verified_at?->toISOString(),
            'jobs_posted' => $this->jobs_posted,
            'active_jobs' => $this->active_jobs,
            
            // Relations (when loaded)
            'jobs' => JobResource::collection($this->whenLoaded('jobs')),
            
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}