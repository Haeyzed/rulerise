<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

class BlogPostCategoryRequest extends BaseRequest
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
             * The name of the blog post category.
             *
             * @var string $name
             * @example "Technology"
             */
            'name' => ['required', 'string', 'max:255'],

            /**
             * The slug of the blog post category.
             * If not provided, it will be automatically generated from the name.
             *
             * @var string|null $slug
             * @example "technology"
             */
            'slug' => [
                'nullable',
                'string',
                'max:255',
                'alpha_dash',
                Rule::unique('blog_post_categories')->ignore($this->route('blogPostCategory')),
            ],

            /**
             * The description of the blog post category.
             *
             * @var string|null $description
             * @example "Articles about technology and innovation"
             */
            'description' => ['nullable', 'string'],

            /**
             * The icon for the blog post category.
             *
             * @var string|null $icon
             * @example "fa-laptop"
             */
            'icon' => ['nullable', 'string', 'max:255'],

            /**
             * Whether the category is active.
             *
             * @var bool $is_active
             * @example true
             */
            'is_active' => ['sometimes', 'boolean'],

            /**
             * The display order of the category.
             *
             * @var int $order
             * @example 1
             */
            'order' => ['sometimes', 'integer', 'min:0'],
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
            'name' => 'Category name',
            'slug' => 'Category slug',
            'description' => 'Category description',
            'icon' => 'Category icon',
            'is_active' => 'Active status',
            'order' => 'Display order',
        ];
    }
}
