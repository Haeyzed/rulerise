<?php

namespace App\Http\Requests\Candidate;

use App\Http\Requests\BaseRequest;

/**
 * Request for uploading a resume.
 *
 * @package App\Http\Requests\Candidate
 */
class ResumeRequest extends BaseRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'resume_file' => 'required|file|mimes:pdf,doc,docx|max:5120', // 5MB max
            'title' => 'required|string|max:255',
            'is_default' => 'boolean',
            'description' => 'nullable|string|max:1000',
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
            'resume_file.required' => 'Please upload a resume file.',
            'resume_file.file' => 'The resume must be a file.',
            'resume_file.mimes' => 'The resume must be a PDF, DOC, or DOCX file.',
            'resume_file.max' => 'The resume file size must not exceed 5MB.',
            'title.required' => 'The resume title field is required.',
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
            'resume_file' => 'resume file',
            'title' => 'resume title',
            'is_default' => 'default resume',
            'description' => 'description',
        ];
    }
}
