<?php

namespace App\Http\Resources;

use App\Models\BlogPost;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Class BlogPostImageResource
 *
 * Represents a blog post resource.
 *
 * @package App\Http\Resources
 *
 * @property BlogPost $resource
 */
class BlogPostResource extends JsonResource
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
             * The unique identifier of the blog post.
             *
             * @var int $id
             * @example 1
             */
            'id' => $this->id,

            /**
             * The title of the blog post.
             *
             * @var string $title
             * @example "How to Build a Laravel API"
             */
            'title' => $this->title,

            /**
             * The subtitle of the blog post.
             *
             * @var string|null $subtitle
             * @example "A step-by-step guide"
             */
            'subtitle' => $this->subtitle,

            /**
             * The main body content of the blog post.
             *
             * @var string $body
             * @example "In this tutorial, we will explore..."
             */
            'body' => $this->body,

            /**
             * The filename of the banner image.
             *
             * @var string|null $banner_image
             * @example "banner.jpg"
             */
            'banner_image' => $this->banner_image,

            /**
             * The full URL of the banner image.
             *
             * @var string|null $banner_image_url
             * @example "https://example.com/storage/banner.jpg"
             */
            'banner_image_url' => $this->banner_image_url,

            /**
             * The caption for the banner image.
             *
             * @var string|null $caption
             * @example "A beautiful sunset over the ocean"
             */
            'caption' => $this->caption,

            /**
             * The read time for the blog post.
             *
             * @var string|null $read_time
             * @example "5"
             */
            'read_time' => $this->read_time,

            /**
             * The status of the blog post.
             *
             * @var string $status
             * @example "published"
             */
            'status' => $this->status,

            /**
             * The label of the blog post status.
             *
             * @var string|null $status_label
             * @example "Published"
             */
            'status_label' => $this->status ? $this->status->label() : null,

            /**
             * The color code associated with the blog post status.
             *
             * @var string|null $status_color
             * @example "#28a745"
             */
            'status_color' => $this->status ? $this->status->color() : null,

            /**
             * The user details of the blog post author.
             *
             * @var array|null $user
             * @example {"id": 3, "name": "John Doe", "email": "johndoe@example.com"}
             */
            'user' => $this->whenLoaded('user', function () {
                return [
                    'id' => $this->user->id,
                    'first_name' => $this->user->first_name,
                    'last_name' => $this->user->last_name,
                    'full_name' => $this->user->full_name,
                    'email' => $this->user->email,
                ];
            }),

            /**
             * The category of the blog post.
             *
             * @var array|null $category
             * @example {"id": 1, "name": "Technology", "slug": "technology"}
             */
            'category' => $this->whenLoaded('category', function () {
                // Check if category is an object with id property
                if (is_object($this->category) && isset($this->category->id)) {
                    return [
                        'id' => $this->category->id,
                        'name' => $this->category->name,
                    ];
                }
                // Return null if category is not a valid object
                return null;
            }),

            /**
             * The images related to the blog post.
             *
             * @var array $images
             * @example [{"id": 1, "url": "https://example.com/image1.jpg"}, {"id": 2, "url": "https://example.com/image2.jpg"}]
             */
            'images' => BlogPostImageResource::collection($this->whenLoaded('images')),

            /**
             * The total count of related images.
             *
             * @var int $images_count
             * @example 5
             */
            'images_count' => $this->whenCounted('images'),

            /**
             * The creation timestamp of the blog post.
             *
             * @var string|null $created_at
             * @example "2024-03-04 12:30:00"
             */
            'created_at' => $this->created_at,

            /**
             * The last update timestamp of the blog post.
             *
             * @var string|null $updated_at
             * @example "2024-03-05 15:45:00"
             */
            'updated_at' => $this->updated_at,

            /**
             * The deletion timestamp of the blog post (if soft deleted).
             *
             * @var string|null $deleted_at
             * @example null
             */
            'deleted_at' => $this->deleted_at,

            /**
             * The formatted creation date of the blog post.
             *
             * @var string|null $formatted_created_at
             * @example "March 4, 2024"
             */
            'formatted_created_at' => $this->created_at ? $this->created_at->format('F j, Y') : null,

            /**
             * The formatted last update date of the blog post.
             *
             * @var string|null $formatted_updated_at
             * @example "March 5, 2024"
             */
            'formatted_updated_at' => $this->updated_at ? $this->updated_at->format('F j, Y') : null,
        ];
    }
}
