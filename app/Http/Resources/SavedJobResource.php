<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SavedJobResource extends JsonResource
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
            'is_saved' => $this->is_saved,
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),
            'saved_at_human' => $this->created_at->diffForHumans(),

            // Related resources
            'job' => new JobResource($this->whenLoaded('job')),
            'candidate' => new CandidateResource($this->whenLoaded('candidate')),

            // Additional information
            'has_applied' => $this->when($this->relationLoaded('job') && $this->relationLoaded('candidate'), function() {
                return $this->job->applications()
                    ->where('candidate_id', $this->candidate_id)
                    ->exists();
            }),
        ];
    }
}
