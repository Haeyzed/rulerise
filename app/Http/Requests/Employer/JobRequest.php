<?php

namespace App\Http\Requests\Employer;

use App\Http\Requests\BaseRequest;

/**
 * Request for creating or updating a job.
 *
 * @package App\Http\Requests\Employer
 */
class JobRequest extends BaseRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'job_category_id' => 'required|exists:job_categories,id',
            'title' => 'required|string|max:255',
            'short_description' => 'nullable|string',
            'description' => 'required|string',
            'job_type' => 'required|string|in:on_site,remote,hybrid',
            'employment_type' => 'nullable|string|in:full_time,part_time,contract,internship,remote',
            'job_industry' => 'nullable|string|max:255',
            'location' => 'required|string|max:255',
            'job_level' => 'nullable|string|max:100',
            'language' => 'nullable|string|max:100',
            'experience_level' => 'required|string|in:entry,mid,senior,executive',
            'salary' => 'nullable|numeric|min:0',
            'salary_payment_mode' => 'nullable|string|max:100',
            'email_to_apply' => 'nullable|email|max:255',
            'easy_apply' => 'boolean',
            'email_apply' => 'boolean',
            'vacancies' => 'nullable|integer|min:1',
            'deadline' => 'nullable|date|after:today',
            'skills_required' => 'nullable|array',
            'skills_required.*' => 'string|max:100',
            'is_active' => 'boolean',
            'is_draft' => 'boolean',
            'is_featured' => 'boolean',
            'is_approved' => 'boolean',
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
            'title.required' => 'The job title field is required.',
            'description.required' => 'The job description field is required.',
            'job_type.required' => 'The job type field is required.',
            'job_type.in' => 'The selected job type is invalid.',
            'experience_level.required' => 'The experience level field is required.',
            'experience_level.in' => 'The selected experience level is invalid.',
            'location.required' => 'The job location field is required.',
            'salary.min' => 'Salary must be a positive number.',
            'email_to_apply.email' => 'The application email must be a valid email address.',
            'deadline.after' => 'The application deadline must be a future date.',
            'skills_required.array' => 'Skills required must be an array.',
            'skills_required.*.string' => 'Each skill must be a string.',
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
            'title' => 'job title',
            'short_description' => 'short description',
            'description' => 'job description',
            'job_type' => 'job type',
            'employment_type' => 'employment type',
            'job_industry' => 'industry',
            'location' => 'job location',
            'job_level' => 'job level',
            'experience_level' => 'experience level',
            'salary' => 'salary',
            'salary_payment_mode' => 'salary payment mode',
            'email_to_apply' => 'application email',
            'easy_apply' => 'easy apply option',
            'email_apply' => 'email apply option',
            'vacancies' => 'number of vacancies',
            'deadline' => 'application deadline',
            'is_active' => 'active status',
            'is_featured' => 'featured status',
            'is_approved' => 'approval status',
            'skills_required' => 'required skills',
        ];
    }
}
