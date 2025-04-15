<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkExperienceResource extends JsonResource
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
            'candidate_id' => $this->candidate_id,
            'job_title' => $this->job_title,
            'company_name' => $this->company_name,
            'location' => $this->location,
            'start_date' => $this->start_date->format('Y-m-d'),
            'end_date' => $this->end_date ? $this->end_date->format('Y-m-d') : null,
            'is_current' => $this->is_current,
            'description' => $this->description,
            'achievements' => $this->achievements,
            'company_website' => $this->company_website,
            'employment_type' => $this->employment_type,
            'industry' => $this->industry,
            'experience_level' => $this->experience_level,
            'experience_level_text' => $this->experienceLevelText,
            'duration' => $this->getDurationText(),
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Get formatted duration text
     *
     * @return string
     */
    protected function getDurationText(): string
    {
        $startDate = $this->start_date;
        $endDate = $this->is_current ? now() : ($this->end_date ?? now());

        $years = $startDate->diffInYears($endDate);
        $months = $startDate->copy()->addYears($years)->diffInMonths($endDate);

        $result = [];
        if ($years > 0) {
            $result[] = $years . ' ' . ($years == 1 ? 'year' : 'years');
        }
        if ($months > 0 || count($result) === 0) {
            $result[] = $months . ' ' . ($months == 1 ? 'month' : 'months');
        }

        return implode(', ', $result);
    }
}
