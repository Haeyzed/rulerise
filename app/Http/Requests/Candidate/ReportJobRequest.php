<?php

namespace App\Http\Requests\Candidate;

use App\Http\Requests\BaseRequest;

class ReportJobRequest extends BaseRequest
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            /**
             * The ID of the job being reported.
             * @var int $job_id
             * @example 101
             */
            'job_id' => ['required', 'exists:jobs,id'],

            /**
             * The reason why the job is being reported.
             * @var string $reason
             * @example "Scam posting"
             */
            'reason' => ['required', 'string', 'max:255'],

            /**
             * Optional detailed description for the report.
             * @var string|null $description
             * @example "The recruiter is asking for money upfront"
             */
            'description' => ['nullable', 'string'],

            /**
             * Indicates if the issue has been resolved. Defaults to false.
             * @var bool|null $is_resolved
             * @example false
             */
            'is_resolved' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Custom messages for validation errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'job_id.required' => 'The job ID is required.',
            'job_id.exists' => 'The selected job does not exist.',
            'reason.required' => 'Please specify a reason for reporting this job.',
        ];
    }
}
