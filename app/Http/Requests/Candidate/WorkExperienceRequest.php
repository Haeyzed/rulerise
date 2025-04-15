<?php

namespace App\Http\Requests\Candidate;

use App\Http\Requests\BaseRequest;

/**
 * Request for creating or updating work experience.
 *
 * @package App\Http\Requests\Candidate
 */
class WorkExperienceRequest extends BaseRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'company_name' => 'required|string|max:255',
            'job_title' => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'is_current' => 'boolean',
            'description' => 'nullable|string|max:1000',
            'location' => 'nullable|string|max:255',
            'achievements' => 'nullable|string|max:1000',
            'company_website' => 'nullable|string|url|max:255',
            'employment_type' => 'nullable|string|in:full_time,part_time,contract,internship,remote',
            'industry' => 'nullable|string|max:255',
            'experience_level' => 'nullable|string|in:0_1,1_3,3_5,5_10,10_plus',
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
            'job_title.required' => 'The job title field is required.',
            'start_date.required' => 'The start date field is required.',
            'start_date.date' => 'The start date must be a valid date.',
            'end_date.date' => 'The end date must be a valid date.',
            'end_date.after_or_equal' => 'The end date must be after or equal to the start date.',
            'company_website.url' => 'Please enter a valid company website URL.',
            'employment_type.in' => 'The selected employment type is invalid.',
            'experience_level.in' => 'The selected experience level is invalid.',
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
            'id' => 'work experience ID',
            'company_name' => 'company name',
            'job_title' => 'job title',
            'start_date' => 'start date',
            'end_date' => 'end date',
            'is_current' => 'current job',
            'description' => 'job description',
            'location' => 'job location',
            'achievements' => 'achievements',
            'company_website' => 'company website',
            'employment_type' => 'employment type',
            'industry' => 'industry',
            'experience_level' => 'experience level',
        ];
    }
}
