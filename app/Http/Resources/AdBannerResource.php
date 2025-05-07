<?php

namespace App\Http\Resources;

use App\Models\AdBanner;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Class AdBannerResource
 *
 * @package App\Http\Resources
 *
 * @property AdBanner $resource
 */
class AdBannerResource extends JsonResource
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
             * The unique identifier of the ad banner.
             *
             * @var int $id
             * @example 1
             */
            'id' => $this->id,

            /**
             * The title of the ad banner.
             *
             * @var string $title
             * @example "Founder's Vision"
             */
            'title' => $this->title,

            /**
             * The link of the ad banner.
             *
             * @var string|null $link
             * @example "https://example.com/promo"
             */
            'link' => $this->link,

            /**
             * The filename of the main image.
             *
             * @var string|null $image_path
             * @example "ad-banner.jpg"
             */
            'image_path' => $this->image_path,

            /**
             * The full URL of the main image.
             *
             * @var string|null $image_url
             * @example "https://example.com/storage/ad-banner.jpg"
             */
            'image_url' => $this->image_url,

            /**
             * The display order of the ad banner.
             *
             * @var int $order
             * @example 1
             */
            'order' => $this->order,

            /**
             * Whether the ad banner is active.
             *
             * @var bool $is_active
             * @example true
             */
            'is_active' => $this->is_active,

            /**
             * Whether the ad banner is currently active based on dates.
             *
             * @var bool $is_currently_active
             * @example true
             */
            'is_currently_active' => $this->is_currently_active,

            /**
             * The start date of the ad banner.
             *
             * @var string|null $start_date
             * @example "2024-05-01 00:00:00"
             */
            'start_date' => $this->start_date,

            /**
             * The end date of the ad banner.
             *
             * @var string|null $end_date
             * @example "2024-05-31 23:59:59"
             */
            'end_date' => $this->end_date,

            /**
             * The images related to the ad banner.
             *
             * @var array $images
             * @example [{"id": 1, "url": "https://example.com/image1.jpg"}, {"id": 2, "url": "https://example.com/image2.jpg"}]
             */
            'images' => AdBannerImageResource::collection($this->whenLoaded('images')),

            /**
             * The total count of related images.
             *
             * @var int $images_count
             * @example 5
             */
            'images_count' => $this->whenCounted('images'),

            /**
             * The creation timestamp of the ad banner.
             *
             * @var string|null $created_at
             * @example "2024-05-07 12:30:00"
             */
            'created_at' => $this->created_at,

            /**
             * The last update timestamp of the ad banner.
             *
             * @var string|null $updated_at
             * @example "2024-05-07 15:45:00"
             */
            'updated_at' => $this->updated_at,

            /**
             * The deletion timestamp of the ad banner (if soft deleted).
             *
             * @var string|null $deleted_at
             * @example null
             */
            'deleted_at' => $this->deleted_at,
        ];
    }
}
