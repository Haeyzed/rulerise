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
            'first_name' => 'sometimes|string|max:255',
            'last_name' => 'sometimes|string|max:255',
            'title' => 'sometimes|string|max:100',
            'email' => 'sometimes|required|string|email|max:255|unique:users,email,' . auth()->id(),
            'phone' => 'sometimes|string|max:20',
            'country' => 'sometimes|string|max:100',
            'state' => 'sometimes|string|max:100',
            'city' => 'sometimes|string|max:100',

            'company_name' => 'sometimes|required|string|max:255',
            'company_email' => 'sometimes|required|string|email|max:255',
            'company_logo' => 'sometimes|image|mimes:jpeg,png,jpg,gif|max:2048',
            'company_description' => 'sometimes|string|max:5000',
            'company_industry' => 'sometimes|string|max:255',
            'company_size' => 'sometimes|string|max:100',
            'company_founded' => 'sometimes|date',
            'company_country' => 'sometimes|string|max:100',
            'company_state' => 'sometimes|string|max:100',
            'company_address' => 'sometimes|string|max:255',
            'company_phone_number' => 'sometimes|string|max:20',
            'company_website' => 'sometimes|string|url|max:255',
            'company_benefits' => 'sometimes|array',
            'company_benefits.*' => 'string|max:255',
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
            'company_logo.image' => 'The company logo must be an image.',
            'company_logo.mimes' => 'The company logo must be a JPEG, PNG, JPG, or GIF file.',
            'company_logo.max' => 'The company logo size must not exceed 2MB.',
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
            'company_website' => 'company website',
            'company_size' => 'company size',
            'company_industry' => 'industry',
            'company_founded' => 'founded year',
            'company_description' => 'company description',
            'company_logo' => 'company logo',
            'company_benefits' => 'company benefits',
        ];
    }
}
