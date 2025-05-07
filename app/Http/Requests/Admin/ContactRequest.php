<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\BaseRequest;

class ContactRequest extends BaseRequest
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
            /**
             * The title of the contact.
             *
             * @var string $title
             * @example "Email"
             */
            'title' => ['required', 'string', 'max:255'],

            /**
             * The value of the contact.
             *
             * @var string $value
             * @example "contact@example.com"
             */
            'value' => ['required', 'string', 'max:255'],

            /**
             * The type of the contact.
             *
             * @var string $type
             * @example "email"
             */
            'type' => ['required', 'string', 'in:email,phone,instagram,facebook,linkedin,whatsapp,link'],

            /**
             * The display order of the contact.
             *
             * @var int $order
             * @example 1
             */
            'order' => ['nullable', 'integer', 'min:0'],

            /**
             * Whether the contact is active.
             *
             * @var bool $is_active
             * @example true
             */
            'is_active' => ['nullable', 'boolean'],
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
            'title' => 'Title',
            'value' => 'Value',
            'type' => 'Type',
            'order' => 'Display order',
            'is_active' => 'Active status',
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
            'type.in' => 'The type must be one of: email, phone, instagram, facebook, linkedin, whatsapp, link.',
        ];
    }
}
