<?php
// app/Http/Resources/JobApplicationResource.php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class JobApplicationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'job_id' => $this->job_id,
            'user_id' => $this->user_id,
            
            'job'       => JobResource::make($this->whenLoaded('job')),
            'applicant' => UserResource::make($this->whenLoaded('user')),
            
            'cover_letter' => $this->cover_letter,
            'cv_url' => $this->cv_url,
            'additional_documents' => $this->additional_documents,
            
            'status' => $this->status,
            'notes' => $this->notes,
            
            'reviewed_at' => $this->reviewed_at?->toISOString(),
            'interview_at' => $this->interview_at?->toISOString(),
            'responded_at' => $this->responded_at?->toISOString(),
            
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}