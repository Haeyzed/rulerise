<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CandidateResource extends JsonResource
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
            'year_of_experience' => $this->year_of_experience,
            'highest_qualification' => $this->highest_qualification,
            'prefer_job_industry' => $this->prefer_job_industry,
            'available_to_work' => $this->available_to_work,
            'bio' => $this->bio,
            'current_position' => $this->current_position,
            'current_company' => $this->current_company,
            'location' => $this->location,
            'expected_salary' => $this->expected_salary,
            'currency' => $this->currency,
            'job_type' => $this->job_type,
            'is_available' => $this->is_available,
            'is_featured' => $this->is_featured,
            'is_verified' => $this->is_verified,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'skills' => SkillResource::collection($this->whenLoaded('skills')),
        ];
    }
}