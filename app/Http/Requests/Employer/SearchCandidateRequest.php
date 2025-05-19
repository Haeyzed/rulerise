<?php

namespace App\Http\Requests\Employer;

use Illuminate\Foundation\Http\FormRequest;

class SearchCandidateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'keyword' => 'nullable|string|max:255',
            'location' => 'nullable|string|max:255',
            'province' => 'nullable|string|max:255',
            'education' => 'nullable|string|exists:degrees,name',
            'industry' => 'nullable|string|max:255',
            'experience' => 'nullable|string|in:0_1,1_3,3_5,5_10,10_plus',
            'sort_by' => 'nullable|string|in:created_at,updated_at,year_of_experience',
            'sort_order' => 'nullable|string|in:asc,desc',
            'per_page' => 'nullable|integer|min:1|max:100',
        ];
    }
}
