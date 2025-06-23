<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlanResource extends JsonResource
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
            'name' => $this->name,
            'description' => $this->description,
            'price' => $this->price,
            'currency' => $this->currency,
            'duration_days' => $this->duration_days,
            'job_posts_limit' => $this->job_posts_limit,
            'featured_jobs_limit' => $this->featured_jobs_limit,
            'resume_views_limit' => $this->resume_views_limit,
            'job_alerts' => $this->job_alerts,
            'candidate_search' => $this->candidate_search,
            'resume_access' => $this->resume_access,
            'company_profile' => $this->company_profile,
            'support_level' => $this->support_level,
            'is_active' => $this->is_active,
            'is_featured' => $this->is_featured,
            'features' => $this->features,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            // Computed properties
            'formatted_duration' => $this->getFormattedDuration(),
            'formatted_price' => $this->getFormattedPrice(),
        ];
    }
}
