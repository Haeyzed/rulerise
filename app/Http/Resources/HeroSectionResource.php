<?php

namespace App\Http\Resources;

use App\Models\HeroSection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Class HeroSectionResource
 *
 * @package App\Http\Resources
 *
 * @property HeroSection $resource
 */
class HeroSectionResource extends JsonResource
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
             * The unique identifier of the hero section.
             *
             * @var int $id
             * @example 1
             */
            'id' => $this->id,

            /**
             * The title of the hero section.
             *
             * @var string $title
             * @example "Founder's Vision"
             */
            'title' => $this->title,

            /**
             * The subtitle of the hero section.
             *
             * @var string|null $subtitle
             * @example "We are raising champions form the slum"
             */
            'subtitle' => $this->subtitle,

            /**
             * The filename of the main image.
             *
             * @var string|null $image_path
             * @example "hero-image.jpg"
             */
            'image_path' => $this->image_path,

            /**
             * The full URL of the main image.
             *
             * @var string|null $image_url
             * @example "https://example.com/storage/hero-image.jpg"
             */
            'image_url' => $this->image_url,

            /**
             * The display order of the hero section.
             *
             * @var int $order
             * @example 1
             */
            'order' => $this->order,

            /**
             * Whether the hero section is active.
             *
             * @var bool $is_active
             * @example true
             */
            'is_active' => $this->is_active,

            /**
             * The images related to the hero section.
             *
             * @var array $images
             * @example [{"id": 1, "url": "https://example.com/image1.jpg"}, {"id": 2, "url": "https://example.com/image2.jpg"}]
             */
            'images' => HeroSectionImageResource::collection($this->whenLoaded('images')),

            /**
             * The total count of related images.
             *
             * @var int $images_count
             * @example 5
             */
            'images_count' => $this->whenCounted('images'),

            /**
             * The creation timestamp of the hero section.
             *
             * @var string|null $created_at
             * @example "2024-05-07 12:30:00"
             */
            'created_at' => $this->created_at,

            /**
             * The last update timestamp of the hero section.
             *
             * @var string|null $updated_at
             * @example "2024-05-07 15:45:00"
             */
            'updated_at' => $this->updated_at,

            /**
             * The deletion timestamp of the hero section (if soft deleted).
             *
             * @var string|null $deleted_at
             * @example null
             */
            'deleted_at' => $this->deleted_at,
        ];
    }
}
