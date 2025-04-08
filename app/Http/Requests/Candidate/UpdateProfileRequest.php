<?php

namespace App\Http\Requests\Candidate;

use App\Http\Requests\BaseRequest;

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
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|string|email|max:255|unique:users,email,' . auth()->id(),
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'zip_code' => 'nullable|string|max:20',
            'date_of_birth' => 'nullable|date',
            'gender' => 'nullable|string|in:male,female,other',
            'bio' => 'nullable|string|max:1000',
            'skills' => 'nullable|array',
            'skills.*' => 'string|max:100',
            'job_title' => 'nullable|string|max:255',
            'salary_expectation' => 'nullable|numeric|min:0',
            'experience_years' => 'nullable|integer|min:0',
            'education_level' => 'nullable|string|max:255',
            'availability' => 'nullable|string|in:immediate,1_week,2_weeks,1_month,more_than_1_month',
            'job_type' => 'nullable|string|in:full_time,part_time,contract,internship,temporary',
            'willing_to_relocate' => 'nullable|boolean',
            'linkedin_url' => 'nullable|string|url|max:255',
            'github_url' => 'nullable|string|url|max:255',
            'portfolio_url' => 'nullable|string|url|max:255',
            'profile_visibility' => 'nullable|boolean',
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
            'name.required' => 'The name field is required.',
            'email.required' => 'The email field is required.',
            'email.email' => 'Please enter a valid email address.',
            'email.unique' => 'This email is already registered.',
            'skills.array' => 'Skills must be an array.',
            'skills.*.string' => 'Each skill must be a string.',
            'salary_expectation.numeric' => 'Salary expectation must be a number.',
            'experience_years.integer' => 'Experience years must be an integer.',
            'linkedin_url.url' => 'Please enter a valid LinkedIn URL.',
            'github_url.url' => 'Please enter a valid GitHub URL.',
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
            'name' => 'full name',
            'email' => 'email address',
            'phone' => 'phone number',
            'address' => 'address',
            'city' => 'city',
            'state' => 'state',
            'country' => 'country',
            'zip_code' => 'zip code',
            'date_of_birth' => 'date of birth',
            'gender' => 'gender',
            'bio' => 'biography',
            'skills' => 'skills',
            'job_title' => 'job title',
            'salary_expectation' => 'salary expectation',
            'experience_years' => 'years of experience',
            'education_level' => 'education level',
            'availability' => 'availability',
            'job_type' => 'job type',
            'willing_to_relocate' => 'willingness to relocate',
            'linkedin_url' => 'LinkedIn URL',
            'github_url' => 'GitHub URL',
            'portfolio_url' => 'portfolio URL',
            'profile_visibility' => 'profile visibility',
        ];
    }
}
