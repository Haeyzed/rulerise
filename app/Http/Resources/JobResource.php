<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class JobResource extends JsonResource
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
            'title' => $this->title,
            'slug' => $this->slug,
            'short_description' => $this->short_description,
            'description' => $this->description,

            // Job details
            'job_type' => $this->job_type,
            'employment_type' => $this->employment_type,
            'job_industry' => $this->job_industry,
            'location' => $this->location,
            'state' => $this->state,
            'language' => $this->language,
            'job_level' => $this->job_level,
            'experience_level' => $this->experience_level,
            'skills_required' => $this->skills_required,

            // Salary information
            'salary' => $this->salary,
            'salary_payment_mode' => $this->salary_payment_mode,
            'years_of_experience' => $this->years_of_experience,

            // Application details
//            'email_to_apply' => $this->when($request->user() && $request->user()->isCandidate(), $this->email_to_apply),
            'email_to_apply' => $this->email_to_apply,
            'easy_apply' => $this->easy_apply,
            'email_apply' => $this->email_apply,
            'vacancies' => $this->vacancies,
            'deadline' => $this->deadline ? $this->deadline->format('Y-m-d') : null,

            // Status flags
            'is_active' => $this->is_active,
            'is_draft' => $this->is_draft,
            'is_featured' => $this->is_featured,
            'is_remote' => $this->is_remote ?? false,

            // Relationships
            'employer' => [
                'id' => $this->whenLoaded('employer', $this->employer->id),
                'company_name' => $this->whenLoaded('employer', $this->employer->company_name),
                'logo' => $this->whenLoaded('employer', function () {
                    return $this->employer->company_logo_url ?? null;
                }),
            ],

            'category' => $this->whenLoaded('category', function () {
                return [
                    'id' => $this->category->id,
                    'name' => $this->category->name,
                ];
            }),

            // Status text for display
            'status' => $this->getStatusText(),

            // Counts
//            'applications_count' => $this->when(isset($this->applications_count), $this->applications_count),

            // Related data
//            'employer' => new EmployerResource($this->whenLoaded('employer')),
//            'category' => new JobCategoryResource($this->whenLoaded('category')),
//            'applications' => JobApplicationResource::collection($this->whenLoaded('applications')),

            // Meta information
            'applications_count' => $this->whenCounted('applications'),
            'views_count' => $this->when($this->relationLoaded('viewCounts'), $this->viewCounts->count()),
//            'pools' => $this->whenLoaded($this->relationLoaded('pools')),

            // User-specific information
            'has_applied' => $this->when(
                $request->user() && $request->user()->isCandidate(),
                function () use ($request) {
                    return $this->applications()
                        ->where('candidate_id', $request->user()->candidate->id)
                        ->exists();
                }
            ),

            'applied_at' => $this->when(
                $request->user() && $request->user()->isCandidate(),
                function () use ($request) {
                    $application = $this->applications()
                        ->where('candidate_id', $request->user()->candidate->id)
                        ->first();
                    return $application ? $application->created_at->format('Y-m-d H:i:s') : null;
                }
            ),

            'has_saved' => $this->when(
                $request->user() && $request->user()->isCandidate(),
                function () use ($request) {
                    return $this->savedJobs()
                        ->where('candidate_id', $request->user()->candidate->id)
                        ->exists();
                }
            ),

            'saved_at' => $this->when(
                $request->user() && $request->user()->isCandidate(),
                function () use ($request) {
                    $savedJob = $this->savedJobs()
                        ->where('candidate_id', $request->user()->candidate->id)
                        ->first();
                    return $savedJob ? $savedJob->created_at->format('Y-m-d H:i:s') : null;
                }
            ),

            'has_reported' => $this->when(
                $request->user() && $request->user()->isCandidate(),
                function () use ($request) {
                    return $this->reports()
                        ->where('candidate_id', $request->user()->candidate->id)
                        ->exists();
                }
            ),

            'reported_at' => $this->when(
                $request->user() && $request->user()->isCandidate(),
                function () use ($request) {
                    $report = $this->reports()
                        ->where('candidate_id', $request->user()->candidate->id)
                        ->first();
                    return $report ? $report->created_at->format('Y-m-d H:i:s') : null;
                }
            ),

            'applications' => JobApplicationResource::collection($this->whenLoaded('applications')),
            'pools' => $this->when($this->relationLoaded('employer') && $this->employer->relationLoaded('candidatePools'),
                CandidatePoolResource::collection($this->employer->candidatePools)),
            // Timestamps
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),
            'posted_at_human' => $this->created_at->diffForHumans(),
        ];
    }

    /**
     * Get status text for display
     *
     * @return string
     */
    private function getStatusText(): string
    {
        if ($this->is_draft) {
            return 'Draft';
        }

        if (!$this->is_active) {
            return 'Closed';
        }

        if ($this->deadline && $this->deadline < now()) {
            return 'Expired';
        }

        return 'Open';
    }
}
