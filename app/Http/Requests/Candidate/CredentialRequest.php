<?php

namespace App\Http\Requests\Candidate;

use App\Http\Requests\BaseRequest;

/**
 * Request for creating or updating candidate credentials.
 *
 * @package App\Http\Requests\Candidate
 */
class CredentialRequest extends BaseRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'credential_name' => 'required|string|max:255',
            'issuing_organization' => 'required|string|max:255',
            'issue_date' => 'required|date',
            'expiration_date' => 'nullable|date|after_or_equal:issue_date',
            'credential_id' => 'nullable|string|max:255',
            'credential_url' => 'nullable|string|url|max:255',
            'description' => 'nullable|string|max:1000',
            'has_expiration' => 'boolean',
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
            'credential_name.required' => 'The credential name field is required.',
            'issuing_organization.required' => 'The issuing organization field is required.',
            'issue_date.required' => 'The issue date field is required.',
            'issue_date.date' => 'The issue date must be a valid date.',
            'expiration_date.date' => 'The expiration date must be a valid date.',
            'expiration_date.after_or_equal' => 'The expiration date must be after or equal to the issue date.',
            'credential_url.url' => 'Please enter a valid credential URL.',
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
            'id' => 'credential ID',
            'credential_name' => 'credential name',
            'issuing_organization' => 'issuing organization',
            'issue_date' => 'issue date',
            'expiration_date' => 'expiration date',
            'credential_id' => 'credential ID',
            'credential_url' => 'credential URL',
            'description' => 'description',
            'has_expiration' => 'has expiration',
        ];
    }
}
