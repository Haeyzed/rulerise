<?php

namespace App\Http\Requests\Candidate;

use App\Http\Requests\BaseRequest;

class SearchJobsRequest extends BaseRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'keyword' => 'nullable|string|max:255',
            'location' => 'nullable|string|max:255',
            'province' => 'nullable|string|max:255',
            'date_posted' => 'nullable|string|in:today,3days,week,month,any,custom',
            'custom_date' => 'nullable|date',
            'job_industry' => 'nullable|string|max:255',
            'experience_level' => 'nullable|string|in:0_1,1_3,3_5,5_10,10_plus',
            'per_page' => 'nullable|integer|min:1|max:100',
            'sort_by' => 'nullable|string|in:created_at,title,salary',
            'sort_order' => 'nullable|string|in:asc,desc',
        ];
    }
}
