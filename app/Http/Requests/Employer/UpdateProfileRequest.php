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
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            // User fields
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'title' => 'nullable|string|max:255',
            'email' => [
                'required',
                'email',
                Rule::unique('users')->ignore(auth()->id())->where(function ($query) {
                    return $query->where('user_type', $this->input('user_type', 'employer'));
                }),
            ],
//            'email' => [
//                'required',
//                'email',
//                Rule::unique('users')->ignore(auth()->id()),
//            ],
            'phone' => 'nullable|string|max:20',
            'phone_country_code' => 'nullable|string|max:10',
            'country' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'city' => 'nullable|string|max:100',

            // Employer fields
            'company_name' => 'required|string|max:255',
            'company_email' => 'required|nullable|email|max:255',
            'company_logo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'company_description' => 'nullable|string',
            'company_industry' => 'nullable|string|max:255',
            'company_size' => 'nullable|string|max:255',
            'company_founded' => [
                'nullable',
                Rule::date()->format('Y-m-d'),
            ],
            'company_country' => 'nullable|string|max:100',
            'company_state' => 'nullable|string|max:100',
            'company_address' => 'nullable|string|max:255',
            'company_phone_number' => 'nullable|string|max:20',
            'company_website' => 'nullable|url|max:255',
            'company_benefits' => 'nullable|array',
            'company_linkedin_url' => 'nullable|url|max:255',
            'company_twitter_url' => 'nullable|url|max:255',
            'company_facebook_url' => 'nullable|url|max:255',
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
