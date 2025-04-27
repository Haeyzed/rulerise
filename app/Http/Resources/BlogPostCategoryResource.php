<?php

namespace App\Http\Resources;

use App\Models\BlogPostCategory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Class BlogPostCategoryResource
 *
 * Represents a blog post category resource.
 *
 * @package App\Http\Resources
 *
 * @property BlogPostCategory $resource
 */
class BlogPostCategoryResource extends JsonResource
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
             * The unique identifier of the blog post category.
             *
             * @var int $id
             * @example 1
             */
            'id' => $this->id,

            /**
             * The name of the blog post category.
             *
             * @var string $name
             * @example "Technology"
             */
            'name' => $this->name,

            /**
             * The slug of the blog post category.
             *
             * @var string $slug
             * @example "technology"
             */
            'slug' => $this->slug,

            /**
             * The description of the blog post category.
             *
             * @var string|null $description
             * @example "Articles about technology and innovation"
             */
            'description' => $this->description,

            /**
             * The icon for the blog post category.
             *
             * @var string|null $icon
             * @example "fa-laptop"
             */
            'icon' => $this->icon,

            /**
             * Whether the category is active.
             *
             * @var bool $is_active
             * @example true
             */
            'is_active' => $this->is_active,

            /**
             * The display order of the category.
             *
             * @var int $order
             * @example 1
             */
            'order' => $this->order,

            /**
             * The count of blog posts in this category.
             *
             * @var int $blog_posts_count
             * @example 15
             */
            'blog_posts_count' => $this->whenCounted('blogPosts'),

            /**
             * The creation timestamp of the category.
             *
             * @var string|null $created_at
             * @example "2024-03-04 12:30:00"
             */
            'created_at' => $this->created_at,

            /**
             * The last update timestamp of the category.
             *
             * @var string|null $updated_at
             * @example "2024-03-05 15:45:00"
             */
            'updated_at' => $this->updated_at,

            /**
             * The deletion timestamp of the category (if soft deleted).
             *
             * @var string|null $deleted_at
             * @example null
             */
            'deleted_at' => $this->deleted_at,

            /**
             * The formatted creation date of the category.
             *
             * @var string|null $formatted_created_at
             * @example "March 4, 2024"
             */
            'formatted_created_at' => $this->created_at ? $this->created_at->format('F j, Y') : null,

            /**
             * The formatted last update date of the category.
             *
             * @var string|null $formatted_updated_at
             * @example "March 5, 2024"
             */
            'formatted_updated_at' => $this->updated_at ? $this->updated_at->format('F j, Y') : null,
        ];
    }
}