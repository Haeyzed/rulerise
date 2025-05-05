<?php

namespace App\Http\Requests\Employer;

use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

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
            // User fields
            'first_name' => 'sometimes|string|max:255',
            'last_name' => 'sometimes|string|max:255',
            'title' => 'sometimes|nullable|string|max:255',
            'email' => [
                'sometimes',
                'email',
                Rule::unique('users')->ignore(auth()->id()),
            ],
            'phone' => 'sometimes|nullable|string|max:20',
            'phone_country_code' => 'sometimes|nullable|string|max:10',
            'country' => 'sometimes|nullable|string|max:100',
            'state' => 'sometimes|nullable|string|max:100',
            'city' => 'sometimes|nullable|string|max:100',

            // Employer fields
            'company_name' => 'sometimes|string|max:255',
            'company_email' => 'sometimes|nullable|email|max:255',
            'company_logo' => 'sometimes|nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'company_description' => 'sometimes|nullable|string',
            'company_industry' => 'sometimes|nullable|string|max:255',
            'company_size' => 'sometimes|nullable|string|max:255',
            'company_founded' => 'sometimes|nullable|date_format:Y',
            'company_country' => 'sometimes|nullable|string|max:100',
            'company_state' => 'sometimes|nullable|string|max:100',
            'company_address' => 'sometimes|nullable|string|max:255',
            'company_phone_number' => 'sometimes|nullable|string|max:20',
            'company_website' => 'sometimes|nullable|url|max:255',
            'company_benefits' => 'sometimes|nullable|array',
            'company_linkedin_url' => 'sometimes|nullable|url|max:255',
            'company_twitter_url' => 'sometimes|nullable|url|max:255',
            'company_facebook_url' => 'sometimes|nullable|url|max:255',
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
            'email.email' => 'Please enter a valid email address.',
            'email.unique' => 'This email is already registered.',
            'company_email.email' => 'Please enter a valid company email address.',
            'company_website.url' => 'Please enter a valid website URL.',
            'company_linkedin_url.url' => 'Please enter a valid LinkedIn URL.',
            'company_twitter_url.url' => 'Please enter a valid Twitter URL.',
            'company_facebook_url.url' => 'Please enter a valid Facebook URL.',
            'company_logo.image' => 'The company logo must be an image.',
            'company_logo.mimes' => 'The company logo must be a file of type: jpeg, png, jpg, gif.',
            'company_logo.max' => 'The company logo may not be greater than 2MB.',
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
            'first_name' => 'first name',
            'last_name' => 'last name',
            'email' => 'email address',
            'phone' => 'phone number',
            'country' => 'country',
            'state' => 'state',
            'city' => 'city',
            'company_name' => 'company name',
            'company_email' => 'company email',
            'company_logo' => 'company logo',
            'company_description' => 'company description',
            'company_industry' => 'company industry',
            'company_size' => 'company size',
            'company_founded' => 'company founded year',
            'company_country' => 'company country',
            'company_state' => 'company state',
            'company_address' => 'company address',
            'company_phone_number' => 'company phone number',
            'company_website' => 'company website',
            'company_benefits' => 'company benefits',
            'company_linkedin_url' => 'company LinkedIn URL',
            'company_twitter_url' => 'company Twitter URL',
            'company_facebook_url' => 'company Facebook URL',
        ];
    }
}
