<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\BaseRequest;

class AboutUsRequest extends BaseRequest
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
             * The headline of the about us section.
             *
             * @var string $headline
             * @example "Our Mission"
             */
            'headline' => ['required', 'string', 'max:255'],

            /**
             * The sub-headline of the about us section.
             *
             * @var string|null $sub_headline
             * @example "Transforming lives through education"
             */
            'sub_headline' => ['nullable', 'string', 'max:255'],

            /**
             * The main body content of the about us section.
             *
             * @var string $body
             * @example "Our organization was founded with the mission to..."
             */
            'body' => ['required', 'string'],

            /**
             * Whether the about us section is active.
             *
             * @var bool $is_active
             * @example true
             */
            'is_active' => ['nullable', 'boolean'],

            /**
             * The related images for the about us section.
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
            'headline' => 'Headline',
            'sub_headline' => 'Sub-headline',
            'body' => 'Body content',
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
            'images.*.max' => 'Each related image must not be larger than 2MB.',
            'images.max' => 'You can upload a maximum of 5 related images.',
        ];
    }
}
