<?php

namespace App\Http\Requests\Candidate;

use App\Http\Requests\BaseRequest;

/**
 * Request for creating or updating education history.
 *
 * @package App\Http\Requests\Candidate
 */
class EducationHistoryRequest extends BaseRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'institution' => 'required|string|max:255',
            'degree' => 'required|string|max:255',
            'field_of_study' => 'nullable|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'is_current' => 'boolean',
            'description' => 'nullable|string|max:1000',
            'grade' => 'nullable|numeric|max:50',
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
            'institution_name.required' => 'The institution name field is required.',
            'degree.required' => 'The degree field is required.',
            'start_date.required' => 'The start date field is required.',
            'start_date.date' => 'The start date must be a valid date.',
            'end_date.date' => 'The end date must be a valid date.',
            'end_date.after_or_equal' => 'The end date must be after or equal to the start date.',
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
            'id' => 'education history ID',
            'institution_name' => 'institution name',
            'degree' => 'degree',
            'field_of_study' => 'field of study',
            'start_date' => 'start date',
            'end_date' => 'end date',
            'is_current' => 'current education',
            'description' => 'description',
            'grade' => 'grade',
            'activities' => 'activities',
            'location' => 'location',
        ];
    }
}
