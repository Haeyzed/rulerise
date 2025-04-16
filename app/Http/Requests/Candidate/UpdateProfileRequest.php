<?php

namespace App\Http\Requests\Candidate;

use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

/**
 * Request for updating candidate profile.
 *
 * @package App\Http\Requests\Candidate
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
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => [
                'required',
                'email',
                Rule::unique('users')->ignore(auth()->id())->where(function ($query) {
                    return $query->where('user_type', $this->input('user_type', 'candidate'));
                }),
            ],
            'phone' => 'nullable|string|max:20',
            'phone_country_code' => 'nullable|string|max:10',
            'country' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'profile_picture' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',

            // Candidate specific fields
            'bio' => 'nullable|string|max:1000',
            'date_of_birth' => 'nullable|date',
            'gender' => 'nullable|string|in:male,female,other',
            'job_title' => 'nullable|string|max:255',
            'year_of_experience' => 'nullable|string|max:10',
            'experience_level' => 'nullable|string|in:entry,mid,senior,expert',
            'highest_qualification' => 'nullable|string|max:255',
            'prefer_job_industry' => 'nullable|string|max:255',
            'available_to_work' => 'nullable|boolean',
            'skills' => 'nullable|array',
            'skills.*' => 'nullable|string|max:100',
            'github' => 'nullable|string|url|max:255',
            'linkedin' => 'nullable|string|url|max:255',
            'twitter' => 'nullable|string|url|max:255',
            'portfolio_url' => 'nullable|string|url|max:255',
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
            'first_name.required' => 'The first name field is required.',
            'last_name.required' => 'The last name field is required.',
            'email.required' => 'The email field is required.',
            'email.email' => 'Please enter a valid email address.',
            'email.unique' => 'This email is already registered.',
            'skills.array' => 'Skills must be an array.',
            'skills.*.string' => 'Each skill must be a string.',
            'github.url' => 'Please enter a valid GitHub URL.',
            'linkedin.url' => 'Please enter a valid LinkedIn URL.',
            'twitter.url' => 'Please enter a valid Twitter URL.',
            'portfolio_url.url' => 'Please enter a valid portfolio URL.',
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
            'date_of_birth' => 'date of birth',
            'gender' => 'gender',
            'bio' => 'biography',
            'skills' => 'skills',
            'job_title' => 'job title',
            'year_of_experience' => 'years of experience',
            'highest_qualification' => 'highest qualification',
            'prefer_job_industry' => 'preferred job industry',
            'available_to_work' => 'availability to work',
            'github' => 'GitHub URL',
            'linkedin' => 'LinkedIn URL',
            'twitter' => 'Twitter URL',
            'portfolio_url' => 'portfolio URL',
        ];
    }
}
