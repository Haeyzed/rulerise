<?php

namespace App\Http\Resources;

use App\Models\HeroSectionImage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Class HeroSectionImageResource
 *
 * @package App\Http\Resources
 *
 * @property HeroSectionImage $resource
 */
class HeroSectionImageResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param Request $request The incoming request instance.
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            /**
             * The unique identifier of the hero section image.
             *
             * @var int $id
             * @example 1
             */
            'id' => $this->id,

            /**
             * The file path of the image.
             *
             * @var string $image_path
             * @example "uploads/hero/12345.jpg"
             */
            'image_path' => $this->image_path,

            /**
             * The full URL of the image.
             *
             * @var string $image_url
             * @example "https://example.com/storage/uploads/hero/12345.jpg"
             */
            'image_url' => $this->image_url,

            /**
             * The display order of the image in the hero section.
             *
             * @var int $order
             * @example 1
             */
            'order' => $this->order,

            /**
             * The creation timestamp of the hero section image.
             *
             * @var string|null $created_at
             * @example "2024-05-07 12:30:00"
             */
            'created_at' => $this->created_at,

            /**
             * The last update timestamp of the hero section image.
             *
             * @var string|null $updated_at
             * @example "2024-05-07 15:45:00"
             */
            'updated_at' => $this->updated_at,
        ];
    }
}
