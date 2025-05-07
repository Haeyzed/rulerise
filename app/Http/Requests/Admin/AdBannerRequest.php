<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\BaseRequest;

class AdBannerRequest extends BaseRequest
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
             * The title of the ad banner.
             *
             * @var string $title
             * @example "Founder's Vision"
             */
            'title' => ['required', 'string', 'max:255'],

            /**
             * The link of the ad banner.
             *
             * @var string|null $link
             * @example "https://example.com/promo"
             */
            'link' => ['nullable', 'string', 'url', 'max:255'],

            /**
             * The main image for the ad banner.
             *
             * @var string|null $image
             * @example "ad-banner.jpg"
             */
            'image' => [
                'nullable',
                'image',
                'mimes:jpeg,png,jpg,gif',
                'max:2048'
            ],

            /**
             * The display order of the ad banner.
             *
             * @var int $order
             * @example 1
             */
            'order' => ['nullable', 'integer', 'min:0'],

            /**
             * Whether the ad banner is active.
             *
             * @var bool $is_active
             * @example true
             */
            'is_active' => ['nullable', 'boolean'],

            /**
             * The start date of the ad banner.
             *
             * @var string|null $start_date
             * @example "2024-05-01"
             */
            'start_date' => ['nullable', 'date'],

            /**
             * The end date of the ad banner.
             *
             * @var string|null $end_date
             * @example "2024-05-31"
             */
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],

            /**
             * The related images for the ad banner.
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
            'images.*' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif', 'max:2048'],
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
            'link' => 'Link',
            'image' => 'Main image',
            'order' => 'Display order',
            'is_active' => 'Active status',
            'start_date' => 'Start date',
            'end_date' => 'End date',
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
            'end_date.after_or_equal' => 'The end date must be after or equal to the start date.',
        ];
    }
}
