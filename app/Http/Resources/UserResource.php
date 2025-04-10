<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
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
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'other_name' => $this->other_name,
            'title' => $this->title,
            'email' => $this->email,
            'phone' => $this->phone,
            'phone_country_code' => $this->phone_country_code,
            'country' => $this->country,
            'state' => $this->state,
            'city' => $this->city,
            'profile_picture_url' => $this->profile_picture_url,
            'user_type' => $this->user_type,
            'is_active' => $this->is_active,
            'email_verified_at' => $this->email_verified_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'roles' => $this->getRoleNames(),
            'permissions' => $this->getAllPermissions()->pluck('name'),
            'candidate' => $this->when($this->isCandidate(), new CandidateResource($this->whenLoaded('candidate'))),
            'employer' => $this->when($this->isEmployer(), new EmployerResource($this->whenLoaded('employer'))),
        ];
    }
}
