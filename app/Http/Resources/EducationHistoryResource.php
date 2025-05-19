<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EducationHistoryResource extends JsonResource
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
//            'degree' => $this->degree,
//            'degree_id' => $this->degree_id,
            'degree_details' => $this->when($this->relationLoaded('degree'), new DegreeResource($this->degree)),
            'institution' => $this->institution,
            'field_of_study' => $this->field_of_study,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'is_current' => $this->is_current,
            'grade' => $this->grade,
            'description' => $this->description,
            'candidate' => new CandidateResource($this->whenLoaded('candidate')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
