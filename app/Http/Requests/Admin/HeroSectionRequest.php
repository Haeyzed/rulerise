<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\BaseRequest;

class HeroSectionRequest extends BaseRequest
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
             * The title of the hero section.
             *
             * @var string $title
             * @example "Founder's Vision"
             */
            'title' => ['required', 'string', 'max:255'],

            /**
             * The subtitle of the hero section.
             *
             * @var string|null $subtitle
             * @example "We are raising champions form the slum"
             */
            'subtitle' => ['nullable', 'string', 'max:255'],

            /**
             * The main image for the hero section.
             *
             * @var string|null $image
             * @example "hero-image.jpg"
             */
            'image' => [
                'nullable',
                'image',
                'mimes:jpeg,png,jpg,gif'
            ],

            /**
             * The display order of the hero section.
             *
             * @var int $order
             * @example 1
             */
            'order' => ['nullable', 'integer', 'min:0'],

            /**
             * Whether the hero section is active.
             *
             * @var bool $is_active
             * @example true
             */
            'is_active' => ['nullable', 'boolean'],

            /**
             * The related images for the hero section.
             *
             * @var array|null $images
             * @example ["image1.jpg", "image2.png"]
             */
            'images' => ['nullable', 'array', 'max:5'],

            /**
             * Each related image must be a valid image file.
             *
             * @var string|null $images.*
             * @example "image1.jpg"
             */
            'images.*' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif'],
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
            'title' => 'Title',
            'subtitle' => 'Subtitle',
            'image' => 'Main image',
            'order' => 'Display order',
            'is_active' => 'Active status',
            'images.*' => 'Related image',
            'images' => 'Related images',
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
            'image.max' => 'The main image must not be larger than 2MB.',
            'images.*.max' => 'Each related image must not be larger than 2MB.',
            'images.max' => 'You can upload a maximum of 5 related images.',
        ];
    }
}
