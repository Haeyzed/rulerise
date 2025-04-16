<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CredentialResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type,
            'issue_date' => $this->issue_date,
            'expiration_date' => $this->expiration_date,
            'credential_id' => $this->credential_id,
            'credential_url' => $this->credential_url,
            'description' => $this->description,
            'candidate' => new CandidateResource($this->whenLoaded('candidate')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
