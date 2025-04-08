<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\BaseRequest;

/**
 * Request for user registration.
 *
 * @package App\Http\Requests\Auth
 */
class RegisterRequest extends BaseRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        // Base rules for all user types
        $rules = [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'other_name' => 'nullable|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'phone' => 'nullable|string|max:20',
            'phone_country_code' => 'nullable|string|max:10',
            'country' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'city' => 'nullable|string|max:100',
            'profile_picture' => 'nullable|string',
            'title' => 'nullable|string|max:50',
            'user_type' => 'required|string|in:candidate,employer',
        ];

        // Add candidate specific rules
        if ($this->input('user_type') === 'candidate') {
            $rules['year_of_experience'] = 'nullable|string|max:10';
            $rules['highest_qualification'] = 'nullable|string|max:255';
            $rules['prefer_job_industry'] = 'nullable|string|max:255';
            $rules['available_to_work'] = 'nullable|boolean';
            $rules['skills'] = 'nullable|array';
            $rules['skills.*'] = 'string|max:100';
        }

        // Add employer specific rules
        if ($this->input('user_type') === 'employer') {
            $rules['company_name'] = 'required|string|max:255';
            $rules['company_email'] = 'nullable|string|email|max:255';
            $rules['company_industry'] = 'nullable|string|max:255';
            $rules['number_of_employees'] = 'nullable|string|max:50';
            $rules['company_founded'] = 'nullable|date';
            $rules['company_country'] = 'nullable|string|max:100';
            $rules['company_state'] = 'nullable|string|max:100';
            $rules['company_address'] = 'nullable|string|max:255';
            $rules['company_benefit_offered'] = 'nullable|array';
            $rules['company_benefit_offered.*'] = 'string|max:255';
            $rules['company_description'] = 'nullable|string';
            $rules['company_phone_number'] = 'nullable|string|max:20';
            $rules['company_website'] = 'nullable|string|max:255';
            $rules['company_logo'] = 'nullable|array';
            $rules['company_logo.image_in_base64'] = 'nullable|string';
            $rules['company_logo.image_extension'] = 'nullable|string|in:jpg,jpeg,png,gif';
        }

        return $rules;
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array
     */
    public function messages(): array
    {
        return [
            'first_name.required' => 'The first name field is required.',
            'last_name.required' => 'The last name field is required.',
            'email.required' => 'The email field is required.',
            'email.email' => 'Please enter a valid email address.',
            'email.unique' => 'This email is already registered.',
            'password.required' => 'The password field is required.',
            'password.min' => 'The password must be at least 8 characters.',
            'user_type.required' => 'The user type field is required.',
            'user_type.in' => 'The user type must be either candidate or employer.',
            'company_name.required' => 'The company name is required for employer registration.',
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
            'other_name' => 'other name',
            'email' => 'email address',
            'password' => 'password',
            'phone' => 'phone number',
            'phone_country_code' => 'phone country code',
            'country' => 'country',
            'state' => 'state',
            'city' => 'city',
            'profile_picture' => 'profile picture',
            'user_type' => 'user type',
            'company_name' => 'company name',
            'company_email' => 'company email',
        ];
    }
}
