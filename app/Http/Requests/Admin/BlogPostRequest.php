<?php

namespace App\Http\Requests\Admin;

use App\Enums\BlogPostStatusEnum;
use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rules\Enum;

class BlogPostRequest extends BaseRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            /**
             * The title of the blog post.
             *
             * @var string $title
             * @example "How to Learn Laravel"
             */
            'title' => ['required', 'string', 'max:255'],

            /**
             * The subtitle of the blog post.
             *
             * @var string|null $subtitle
             * @example "A guide for beginners"
             */
            'subtitle' => ['nullable', 'string', 'max:255'],

            /**
             * The main body content of the blog post.
             *
             * @var string $body
             * @example "Laravel is a powerful PHP framework..."
             */
            'body' => ['required', 'string'],

            /**
             * The banner image for the blog post.
             *
             * This field is required for new posts and optional for updates.
             *
             * @var string|null $banner_image
             * @example "banner.jpg"
             */
            'banner_image' => [
                $this->isMethod('post') ? 'required' : 'nullable',
                'image',
                'mimes:jpeg,png,jpg,gif',
                'max:2048'
            ],

            /**
             * The caption for the banner image.
             *
             * @var string|null $caption
             * @example "A beautiful sunset over the mountains."
             */
            'caption' => ['nullable', 'string', 'max:255'],

            /**
             * The category ID for the blog post.
             *
             * @var int|null $category_id
             * @example 1
             */
            'category_id' => ['nullable', 'integer', 'exists:blog_post_categories,id'],

            /**
             * The read time for the blog post.
             *
             * @var string|null $read_time
             * @example "5"
             */
            'read_time' => ['nullable', 'string'],

            /**
             * The status of the blog post.
             *
             * @var BlogPostStatusEnum $status
             * @example \App\Enums\BlogPostStatus::PUBLISHED
             */
            'status' => ['sometimes', new Enum(BlogPostStatusEnum::class)],

            /**
             * The related images for the blog post.
             *
             * @var array|null $related_images
             * @example ["image1.jpg", "image2.png"]
             */
            'related_images' => ['nullable', 'array', 'max:5'],

            /**
             * Each related image must be a valid image file.
             *
             * @var string|null $related_images .*
             * @example "image1.jpg"
             */
            'related_images.*' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif', 'max:2048'],
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'title' => 'Story title',
            'subtitle' => 'Sub-title',
            'body' => 'Story body',
            'banner_image' => 'Banner image',
            'category_id' => 'Category',
            'related_images.*' => 'Related image',
            'related_images' => 'Related images',
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'banner_image.max' => 'The banner image must not be larger than 500KB.',
            'related_images.*.max' => 'Each related image must not be larger than 500KB.',
            'related_images.max' => 'You can upload a maximum of 5 related images.',
            'category_id.exists' => 'The selected category does not exist.',
        ];
    }
}
