<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class JobApplicationResource extends JsonResource
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
            'cover_letter' => $this->cover_letter,
            'status' => $this->status,
            'employer_notes' => $this->employer_notes,
            'apply_via' => $this->apply_via,
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),
            'applied_at_human' => $this->created_at->diffForHumans(),

            // Related resources
            'job' => new JobResource($this->whenLoaded('job')),
            'candidate' => new CandidateResource($this->whenLoaded('candidate')),
            'resume' => new ResumeResource($this->whenLoaded('resume')),

            // Status information
            'status_label' => $this->when($this->status, function() {
                $statusLabels = [
                    'applied' => 'Applied',
                    'screening' => 'Screening',
                    'interview' => 'Interview',
                    'shortlisted' => 'Shortlisted',
                    'rejected' => 'Rejected',
                    'hired' => 'Hired',
                    'withdrawn' => 'Withdrawn',
                ];

                return $statusLabels[$this->status] ?? ucfirst($this->status);
            }),

            // Status color for UI
            'status_color' => $this->when($this->status, function() {
                $statusColors = [
                    'applied' => 'blue',
                    'screening' => 'purple',
                    'interview' => 'orange',
                    'shortlisted' => 'green',
                    'rejected' => 'red',
                    'hired' => 'teal',
                    'withdrawn' => 'gray',
                ];

                return $statusColors[$this->status] ?? 'gray';
            }),
        ];
    }
}
