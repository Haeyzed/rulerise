<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ResumeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     * @return array
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'document_url' => $this->document_url,
            'is_primary' => $this->is_primary,
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),

            // Related resources
            'candidate' => new CandidateResource($this->whenLoaded('candidate')),
            'job_applications' => JobApplicationResource::collection($this->whenLoaded('jobApplications')),
            'job_applications_count' => $this->whenCounted('jobApplications'),
        ];
    }
}
