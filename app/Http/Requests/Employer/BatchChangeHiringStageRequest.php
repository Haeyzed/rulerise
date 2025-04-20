<?php

namespace App\Http\Requests\Employer;

use App\Http\Requests\BaseRequest;

class BatchChangeHiringStageRequest extends BaseRequest
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
            'application_ids' => 'required|array|min:1',
            'application_ids.*' => 'required|exists:job_applications,id',
            'status' => 'required|in:unsorted,rejected,offer_sent,shortlisted',
            'notes' => 'nullable|string',
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
            'application_ids.required' => 'At least one application ID is required.',
            'application_ids.array' => 'Application IDs must be provided as an array.',
            'application_ids.*.exists' => 'One or more selected applications do not exist.',
            'status.required' => 'The hiring stage status is required.',
            'status.in' => 'The selected status is invalid.',
        ];
    }
}
