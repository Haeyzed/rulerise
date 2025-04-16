<?php

namespace App\Http\Requests\Candidate;

use App\Http\Requests\BaseRequest;
use Illuminate\Foundation\Http\FormRequest;

class UploadCvRequest extends BaseRequest
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
            'name' => 'nullable|string|max:255',
            /**
             * The document for the  user.
             *
             * This field is required for new posts and optional for updates.
             *
             * @var string|null $document
             * @example "document.pdf"
             */
            'document' => ['required','file','mimes:pdf,doc,docx|max:5120'], // 5MB max

            /**
             * Whether to set as primary resume.
             *
             * @var bool|null $is_primary
             * @example false
             */
            'is_primary' => ['nullable', 'boolean'],
        ];
    }
}
