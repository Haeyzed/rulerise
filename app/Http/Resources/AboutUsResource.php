<?php

namespace App\Http\Resources;

use App\Models\AboutUs;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Class AboutUsResource
 *
 * @package App\Http\Resources
 *
 * @property AboutUs $resource
 */
class AboutUsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            /**
             * The unique identifier of the about us section.
             *
             * @var int $id
             * @example 1
             */
            'id' => $this->id,

            /**
             * The headline of the about us section.
             *
             * @var string $headline
             * @example "Our Mission"
             */
            'headline' => $this->headline,

            /**
             * The sub-headline of the about us section.
             *
             * @var string|null $sub_headline
             * @example "Transforming lives through education"
             */
            'sub_headline' => $this->sub_headline,

            /**
             * The main body content of the about us section.
             *
             * @var string $body
             * @example "Our organization was founded with the mission to..."
             */
            'body' => $this->body,

            /**
             * Whether the about us section is active.
             *
             * @var bool $is_active
             * @example true
             */
            'is_active' => $this->is_active,

            /**
             * The images related to the about us section.
             *
             * @var array $images
             * @example [{"id": 1, "url": "https://example.com/image1.jpg"}, {"id": 2, "url": "https://example.com/image2.jpg"}]
             */
            'images' => AboutUsImageResource::collection($this->whenLoaded('images')),

            /**
             * The total count of related images.
             *
             * @var int $images_count
             * @example 5
             */
            'images_count' => $this->whenCounted('images'),

            /**
             * The creation timestamp of the about us section.
             *
             * @var string|null $created_at
             * @example "2024-05-07 12:30:00"
             */
            'created_at' => $this->created_at,

            /**
             * The last update timestamp of the about us section.
             *
             * @var string|null $updated_at
             * @example "2024-05-07 15:45:00"
             */
            'updated_at' => $this->updated_at,

            /**
             * The deletion timestamp of the about us section (if soft deleted).
             *
             * @var string|null $deleted_at
             * @example null
             */
            'deleted_at' => $this->deleted_at,
        ];
    }
}
