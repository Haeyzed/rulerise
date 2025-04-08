<?php

namespace App\Http\Requests\Employer;

use App\Http\Requests\BaseRequest;

/**
 * Request for updating employer profile.
 *
 * @package App\Http\Requests\Employer
 */
class UpdateProfileRequest extends BaseRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'company_name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|string|email|max:255|unique:users,email,' . auth()->id(),
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'zip_code' => 'nullable|string|max:20',
            'company_website' => 'nullable|string|url|max:255',
            'company_size' => 'nullable|string|in:1-10,11-50,51-200,201-500,501-1000,1001-5000,5001+',
            'industry' => 'nullable|string|max:255',
            'founded_year' => 'nullable|integer|min:1800|max:' . date('Y'),
            'company_description' => 'nullable|string|max:5000',
            'company_logo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'company_banner' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'facebook_url' => 'nullable|string|url|max:255',
            'twitter_url' => 'nullable|string|url|max:255',
            'linkedin_url' => 'nullable|string|url|max:255',
            'instagram_url' => 'nullable|string|url|max:255',
            'company_culture' => 'nullable|string|max:1000',
            'benefits' => 'nullable|string|max:1000',
            'mission_statement' => 'nullable|string|max:1000',
            'vision_statement' => 'nullable|string|max:1000',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array
     */
    public function messages(): array
    {
        return [
            'company_name.required' => 'The company name field is required.',
            'email.required' => 'The email field is required.',
            'email.email' => 'Please enter a valid email address.',
            'email.unique' => 'This email is already registered.',
            'company_website.url' => 'Please enter a valid company website URL.',
            'company_size.in' => 'The selected company size is invalid.',
            'founded_year.integer' => 'The founded year must be an integer.',
            'founded_year.min' => 'The founded year must be at least 1800.',
            'founded_year.max' => 'The founded year cannot be in the future.',
            'company_logo.image' => 'The company logo must be an image.',
            'company_logo.mimes' => 'The company logo must be a JPEG, PNG, JPG, or GIF file.',
            'company_logo.max' => 'The company logo size must not exceed 2MB.',
            'company_banner.image' => 'The company banner must be an image.',
            'company_banner.mimes' => 'The company banner must be a JPEG, PNG, JPG, or GIF file.',
            'company_banner.max' => 'The company banner size must not exceed 2MB.',
            'facebook_url.url' => 'Please enter a valid Facebook URL.',
            'twitter_url.url' => 'Please enter a valid Twitter URL.',
            'linkedin_url.url' => 'Please enter a valid LinkedIn URL.',
            'instagram_url.url' => 'Please enter a valid Instagram URL.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array
     */
    public function attributes(): array
    {
        return [
            'company_name' => 'company name',
            'email' => 'email address',
            'phone' => 'phone number',
            'address' => 'address',
            'city' => 'city',
            'state' => 'state',
            'country' => 'country',
            'zip_code' => 'zip code',
            'company_website' => 'company website',
            'company_size' => 'company size',
            'industry' => 'industry',
            'founded_year' => 'founded year',
            'company_description' => 'company description',
            'company_logo' => 'company logo',
            'company_banner' => 'company banner',
            'facebook_url' => 'Facebook URL',
            'twitter_url' => 'Twitter URL',
            'linkedin_url' => 'LinkedIn URL',
            'instagram_url' => 'Instagram URL',
            'company_culture' => 'company culture',
            'benefits' => 'benefits',
            'mission_statement' => 'mission statement',
            'vision_statement' => 'vision statement',
        ];
    }
}
