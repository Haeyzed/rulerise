<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployerResource extends JsonResource
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
            'company_name' => $this->company_name,
            'company_email' => $this->company_email,
            'company_logo_url' => $this->company_logo_url,
            'company_description' => $this->company_description,
            'company_industry' => $this->company_industry,
            'company_size' => $this->company_size,
            'company_founded' => $this->company_founded,
            'company_country' => $this->company_country,
            'company_state' => $this->company_state,
            'company_address' => $this->company_address,
            'company_phone_number' => $this->company_phone_number,
            'company_website' => $this->company_website,
            'company_linkedin_url' => $this->company_linkedin_url,
            'company_twitter_url' => $this->company_twitter_url,
            'company_facebook_url' => $this->company_facebook_url,
            'is_verified' => $this->is_verified,
            'is_featured' => $this->is_featured,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'company_benefits' => $this->company_benefits,

            // Job counts
            'jobs_count' => $this->when(isset($this->jobs_count), $this->jobs_count),
            'active_jobs_count' => $this->when(isset($this->active_jobs_count), $this->active_jobs_count),
            'featured_jobs_count' => $this->when(isset($this->featured_jobs_count), $this->featured_jobs_count),
            'draft_jobs_count' => $this->when(isset($this->draft_jobs_count), $this->draft_jobs_count),
            'expired_jobs_count' => $this->when(isset($this->expired_jobs_count), $this->expired_jobs_count),

            // Application statistics
            'total_applications_count' => $this->when(isset($this->total_applications_count), $this->total_applications_count),
            'application_status_counts' => $this->when(isset($this->application_status_counts), $this->application_status_counts),

            // Active subscription
            'active_subscription' => new SubscriptionResource($this->whenLoaded('activeSubscription')),

            // Related data
            'user' => new UserResource($this->whenLoaded('user')),
            'jobs' => JobResource::collection($this->whenLoaded('jobs')),
            'subscriptions' => SubscriptionResource::collection($this->whenLoaded('subscriptions')),
            'notification_templates' => JobNotificationTemplateResource::collection($this->whenLoaded('notificationTemplates')),
            'candidate_pools' => CandidatePoolResource::collection($this->whenLoaded('candidatePools')),

            // Applications and hired candidates
            'applications' => JobApplicationResource::collection($this->whenLoaded('applications')),
            'hired_candidates' => $this->when(isset($this->hired_candidates),
                JobApplicationResource::collection($this->hired_candidates), null
            ),
        ];
    }
}
