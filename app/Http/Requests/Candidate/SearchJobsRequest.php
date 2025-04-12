<?php

namespace App\Http\Requests\Candidate;

use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

class SearchJobsRequest extends BaseRequest
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'keyword' => 'nullable|string|max:255',
            'location' => 'nullable|string|max:255',
            'province' => 'nullable|string|max:255',
            'date_posted' => 'nullable|string|in:today,3days,week,month,any',
            'job_industry' => 'nullable|string|max:255',
            'experience_level' => 'nullable|string|in:entry,mid,senior,executive,any',
            'per_page' => 'nullable|integer|min:1|max:100',
            'sort_by' => 'nullable|string|in:created_at,title,salary',
            'sort_order' => 'nullable|string|in:asc,desc',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'keyword' => 'job title or keyword',
            'job_industry' => 'job industry',
            'experience_level' => 'experience level',
        ];
    }

    /**
     * Prepare the data for validation.
     *
     * @return void
     */
    protected function prepareForValidation(): void
    {
        // Convert date_posted to a standardized format if needed
        if ($this->has('date_posted')) {
            $datePosted = $this->date_posted;

            // Map frontend values to backend values if necessary
            // This is just an example, adjust according to your actual frontend values
            $dateMap = [
                'today' => 'today',
                'last_3_days' => '3days',
                'last_week' => 'week',
                'last_month' => 'month',
                'any_time' => 'any',
            ];

            if (isset($dateMap[$datePosted])) {
                $this->merge(['date_posted' => $dateMap[$datePosted]]);
            }
        }
    }
}
