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
            'gender' => $this->gender,
            'date_of_birth' => optional($this->date_of_birth)->format('Y-m-d'),
            'is_available' => $this->is_available,
            'is_featured' => $this->is_featured,
            'is_verified' => $this->is_verified,
            'experience_level' => $this->experience_level,
            'job_title' => $this->job_title,
            'github' => $this->github,
            'linkedin' => $this->linkedin,
            'twitter' => $this->twitter,
            'portfolio_url' => $this->portfolio_url,
            'created_at' => optional($this->created_at)->format('Y-m-d H:i:s'),
            'updated_at' => optional($this->updated_at)->format('Y-m-d H:i:s'),
            'skills' => $this->skills,

            // Application counts
            'job_applications_count' => $this->whenCounted('jobApplications'),

            // Include relationships
            'qualification' => $this->whenLoaded('qualification'),
//            'work_experiences' => $this->whenLoaded('workExperiences'),
//            'education_histories' => $this->whenLoaded('educationHistories'),
//            'languages' => $this->whenLoaded('languages'),
            'portfolio' => $this->whenLoaded('portfolio'),
//            'credentials' => $this->whenLoaded('credentials'),
            'resumes' => $this->whenLoaded('resumes'),
//            'user' => $this->whenLoaded('user'),


            // Related data
            'user' => new UserResource($this->whenLoaded('user')),
//            'qualification' => new QualificationResource($this->whenLoaded('qualification')),
            'work_experiences' => WorkExperienceResource::collection($this->whenLoaded('workExperiences')),
            'education_histories' => EducationHistoryResource::collection($this->whenLoaded('educationHistories')),
            'languages' => LanguageResource::collection($this->whenLoaded('languages')),
//            'portfolio' => new PortfolioResource($this->whenLoaded('portfolio')),
            'credentials' => CredentialResource::collection($this->whenLoaded('credentials')),
            'job_applications' => JobApplicationResource::collection($this->whenLoaded('jobApplications')),
            'primary_resume' => new ResumeResource($this->whenLoaded('primaryResume')),
        ];
    }
}
