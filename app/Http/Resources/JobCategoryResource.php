<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class JobCategoryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'icon' => $this->icon,
            'is_active' => $this->is_active,
            'is_featured' => $this->is_featured,
            'jobs_count' => $this->when(isset($this->jobs_count), $this->jobs_count),
            'total_jobs_count' => $this->when(isset($this->total_jobs_count), $this->total_jobs_count),
            'jobs' => JobResource::collection($this->whenLoaded('jobs')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
