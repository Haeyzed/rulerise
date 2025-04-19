<?php

namespace App\Http\Requests\Employer;

use App\Http\Requests\BaseRequest;
use Illuminate\Foundation\Http\FormRequest;

class ChangeHiringStageRequest extends BaseRequest
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
            'application_id' => 'required|exists:job_applications,id',
            'status' => 'required|in:unsorted,rejected,offer_sent,shortlisted',
            'notes' => 'nullable|string',
        ];
    }
}
