<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

class RegisterRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'other_name' => 'nullable|string|max:255',
            'email' => [
                'required',
                'email',
                Rule::unique('users')->where(function ($query) {
                    return $query->where('user_type', $this->input('user_type'));
                }),
            ],
            'password' => 'required|string|min:8',
            'phone' => 'nullable|string|max:20',
            'phone_country_code' => 'nullable|string|max:10',
            'country' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'city' => 'nullable|string|max:100',
            'profile_picture' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'title' => 'nullable|string|max:50',
            'user_type' => 'required|string|in:candidate,employer,admin',

            'year_of_experience' => 'nullable|required_if:user_type,candidate|string|max:10',
            'highest_qualification' => 'nullable|required_if:user_type,candidate|string|max:255',
            'prefer_job_industry' => 'nullable|required_if:user_type,candidate|string|max:255',
            'available_to_work' => 'nullable|required_if:user_type,candidate|boolean',
            'skills' => 'nullable|array',
            'skills.*' => 'nullable|string|max:100',

            'company_name' => 'required_if:user_type,employer|string|max:255',
            'company_email' => 'nullable|required_if:user_type,employer|string|email|max:255',
            'company_industry' => 'nullable|required_if:user_type,employer|string|max:255',
            'company_size' => 'nullable|required_if:user_type,employer|string|max:100000',
            'company_founded' => 'nullable|required_if:user_type,employer|date',
            'company_country' => 'nullable|required_if:user_type,employer|string|max:100',
            'company_state' => 'nullable|required_if:user_type,employer|string|max:100',
            'company_address' => 'nullable|required_if:user_type,employer|string|max:255',
            'company_benefit_offered' => 'nullable|required_if:user_type,employer|array',
            'company_benefit_offered.*' => 'nullable|string|max:255',
            'company_linkedin_url' => 'nullable|string|max:255',
            'company_twitter_url' => 'nullable|string|max:255',
            'company_facebook_url' => 'nullable|string|max:255',
            'company_description' => 'nullable|required_if:user_type,employer|string',
            'company_phone_number' => 'nullable|required_if:user_type,employer|string|max:20',
            'company_website' => 'nullable|required_if:user_type,employer|string|max:255',
            'company_logo' => 'nullable|required_if:user_type,employer|image|mimes:jpeg,png,jpg,gif|max:2048',
        ];
    }

    public function messages(): array
    {
        return [
            'first_name.required' => 'The first name field is required.',
            'last_name.required' => 'The last name field is required.',
            'email.required' => 'The email field is required.',
            'email.email' => 'Please enter a valid email address.',
            'email.unique' => 'This email is already registered for this user type.',
            'password.required' => 'The password field is required.',
            'password.min' => 'The password must be at least 8 characters.',
            'user_type.required' => 'The user type field is required.',
            'user_type.in' => 'The user type must be candidate, employer, or admin.',

            'company_name.required_if' => 'The company name is required for employer registration.',
            'company_email.required_if' => 'The company email is required for employer registration.',
            'company_industry.required_if' => 'The company industry is required for employer registration.',
            'company_logo.image' => 'The company logo must be an image.',
            'company_logo.mimes' => 'The company logo must be a file of type: jpeg, png, jpg, gif.',
            'company_logo.max' => 'The company logo may not be greater than 2MB.',

            'year_of_experience.required_if' => 'The years of experience is required for candidate registration.',
            'highest_qualification.required_if' => 'The highest qualification is required for candidate registration.',
            'prefer_job_industry.required_if' => 'The preferred job industry is required for candidate registration.',
            'skills.required_if' => 'Skills are required for candidate registration.',
        ];
    }

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
            'company_logo' => 'company logo',
            'company_industry' => 'company industry',
            'company_size' => 'company size',
            'company_founded' => 'company founded date',
            'company_country' => 'company country',
            'company_state' => 'company state',
            'company_address' => 'company address',
            'company_benefit_offered' => 'company benefits offered',
            'company_description' => 'company description',
            'company_phone_number' => 'company phone number',
            'company_website' => 'company website',
            'year_of_experience' => 'years of experience',
            'highest_qualification' => 'highest qualification',
            'prefer_job_industry' => 'preferred job industry',
            'available_to_work' => 'availability to work',
            'skills' => 'skills',
        ];
    }
}
