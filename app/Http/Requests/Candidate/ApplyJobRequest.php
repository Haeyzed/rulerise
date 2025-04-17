<?php

namespace App\Http\Requests\Candidate;

use App\Http\Requests\BaseRequest;
use Illuminate\Foundation\Http\FormRequest;

class ApplyJobRequest extends BaseRequest
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
            'job_id' => ['required', 'exists:job_listings,id'],
            'resume_id' => ['nullable', 'exists:resumes,id'],
            'cover_letter' => ['nullable', 'string'],
            'apply_via' => ['required', 'string', 'in:custom_cv,profile_cv'],
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
            'job_id' => 'job',
            'resume_id' => 'resume',
            'cover_letter' => 'cover letter',
            'apply_via' => 'application method',
        ];
    }
}
