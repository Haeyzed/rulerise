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
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'job_category_id' => 'required|integer|exists:job_categories,id',
            'job_type' => 'required|string|in:full_time,part_time,contract,internship,temporary',
            'location' => 'required|string|max:255',
            'is_remote' => 'boolean',
            'salary_min' => 'nullable|numeric|min:0',
            'salary_max' => 'nullable|numeric|min:0|gte:salary_min',
            'salary_currency' => 'nullable|string|size:3',
            'salary_period' => 'nullable|string|in:hourly,daily,weekly,monthly,yearly',
            'is_salary_visible' => 'boolean',
            'application_deadline' => 'nullable|date|after:today',
            'experience_level' => 'nullable|string|in:entry,junior,mid,senior,executive',
            'education_level' => 'nullable|string|max:255',
            'skills_required' => 'nullable|array',
            'skills_required.*' => 'string|max:100',
            'responsibilities' => 'nullable|string',
            'qualifications' => 'nullable|string',
            'benefits' => 'nullable|string',
            'application_instructions' => 'nullable|string',
            'is_published' => 'boolean',
            'is_featured' => 'boolean',
            'is_open' => 'boolean',
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
            'job_category_id.required' => 'The job category field is required.',
            'job_category_id.exists' => 'The selected job category is invalid.',
            'job_type.required' => 'The job type field is required.',
            'job_type.in' => 'The selected job type is invalid.',
            'location.required' => 'The job location field is required.',
            'salary_max.gte' => 'The maximum salary must be greater than or equal to the minimum salary.',
            'application_deadline.after' => 'The application deadline must be a future date.',
            'experience_level.in' => 'The selected experience level is invalid.',
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
            'id' => 'job ID',
            'title' => 'job title',
            'description' => 'job description',
            'job_category_id' => 'job category',
            'job_type' => 'job type',
            'location' => 'job location',
            'is_remote' => 'remote job',
            'salary_min' => 'minimum salary',
            'salary_max' => 'maximum salary',
            'salary_currency' => 'salary currency',
            'salary_period' => 'salary period',
            'is_salary_visible' => 'salary visibility',
            'application_deadline' => 'application deadline',
            'experience_level' => 'experience level',
            'education_level' => 'education level',
            'skills_required' => 'required skills',
            'responsibilities' => 'job responsibilities',
            'qualifications' => 'job qualifications',
            'benefits' => 'job benefits',
            'application_instructions' => 'application instructions',
            'is_published' => 'published status',
            'is_featured' => 'featured status',
            'is_open' => 'open status',
        ];
    }
}
