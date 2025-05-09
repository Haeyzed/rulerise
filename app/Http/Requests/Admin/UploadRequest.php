<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UploadRequest extends FormRequest
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
        $rules = [];

        // Check which endpoint is being accessed
        if ($this->routeIs('uploads.upload')) {
            $rules['file'] = ['required', 'file', 'max:' . $this->getMaxFileSize()];
        } elseif ($this->routeIs('uploads.upload-multiple')) {
            $rules['files'] = ['required', 'array', 'min:1', 'max:10'];
            $rules['files.*'] = ['required', 'file', 'max:' . $this->getMaxFileSize()];
        }

        // Common rules
        $rules['path'] = ['nullable', 'string'];
        $rules['disk'] = ['nullable', 'string'];
        $rules['is_public'] = ['nullable', 'boolean'];
        $rules['collection'] = ['nullable', 'string', 'max:255'];
        $rules['metadata'] = ['nullable', 'array'];

        return $rules;
    }

    /**
     * Get the maximum file size in kilobytes.
     *
     * @return int
     */
    protected function getMaxFileSize(): int
    {
        // Default to 10MB, or use a config value
        return config('filestorage.max_file_size', 10240);
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'file' => 'file',
            'files' => 'files',
            'files.*' => 'file',
            'path' => 'path',
            'disk' => 'storage disk',
            'is_public' => 'public access',
            'collection' => 'collection',
            'metadata' => 'metadata',
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.max' => 'The file may not be greater than :max kilobytes.',
            'files.*.max' => 'Each file may not be greater than :max kilobytes.',
            'files.max' => 'You may not upload more than :max files at once.',
        ];
    }
}
