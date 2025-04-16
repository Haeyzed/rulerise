<?php

namespace App\Http\Requests\Candidate;

use App\Http\Requests\BaseRequest;

/**
 * Request for creating or updating candidate languages.
 *
 * @package App\Http\Requests\Candidate
 */
class LanguageRequest extends BaseRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'language' => 'required|string|max:100',
            'proficiency' => 'required|string|in:beginner,intermediate,advanced,native,fluent',
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
            'language_name.required' => 'The language name field is required.',
            'proficiency_level.required' => 'The proficiency level field is required.',
            'proficiency_level.in' => 'The selected proficiency level is invalid.',
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
            'id' => 'language ID',
            'language_name' => 'language name',
            'proficiency_level' => 'proficiency level',
            'is_primary' => 'primary language',
            'details' => 'details',
        ];
    }
}
