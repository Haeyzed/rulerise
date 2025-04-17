<?php

namespace App\Http\Resources;

use App\Models\BlogPostImage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Class BlogPostImageResource
 *
 * Represents a blog post image resource.
 *
 * @package App\Http\Resources
 *
 * @property BlogPostImage $resource
 */
class BlogPostImageResource extends JsonResource
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
             * The unique identifier of the blog post image.
             *
             * @var int $id
             * @example 1
             */
            'id' => $this->id,

            /**
             * The ID of the related blog post.
             *
             * @var int $blog_post_id
             * @example 10
             */
            'blog_post_id' => $this->blog_post_id,

            /**
             * The file path of the image.
             *
             * @var string $image_path
             * @example "uploads/blog/12345.jpg"
             */
            'image_path' => $this->image_path,

            /**
             * The full URL of the image.
             *
             * @var string $image_url
             * @example "https://example.com/storage/uploads/blog/12345.jpg"
             */
            'image_url' => $this->image_url,

            /**
             * The display order of the image in the blog post.
             *
             * @var int $order
             * @example 1
             */
            'order' => $this->order,

            /**
             * The creation timestamp of the blog post image.
             *
             * @var string|null $created_at
             * @example "2024-03-04 12:30:00"
             */
            'created_at' => $this->created_at,

            /**
             * The last update timestamp of the blog post image.
             *
             * @var string|null $updated_at
             * @example "2024-03-05 15:45:00"
             */
            'updated_at' => $this->updated_at,
        ];
    }
}
