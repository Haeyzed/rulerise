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
            'email_verified_at' => optional($this->email_verified_at)->format('Y-m-d H:i:s'),
            'created_at' => optional($this->created_at)->format('Y-m-d H:i:s'),
            'updated_at' => optional($this->updated_at)->format('Y-m-d H:i:s'),
            'role' => $this->roles->first() ? [
                'id' => $this->roles->first()->id,
                'name' => $this->roles->first()->name,
                'description' => $this->roles->first()->description,
            ] : null,
            'permissions' => $this->getAllPermissions()->map(function ($permission) {
                return [
                    'id' => $permission->id,
                    'name' => $permission->name,
                    'description' => $permission->description,
                ];
            }),
            'candidate' => $this->when($this->isCandidate(), new CandidateResource($this->whenLoaded('candidate'))),
            'employer' => $this->when($this->isEmployer(), new EmployerResource($this->whenLoaded('employer'))),
        ];
    }
}
