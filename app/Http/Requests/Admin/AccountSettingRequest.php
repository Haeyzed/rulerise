<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class AccountSettingRequest extends FormRequest
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
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'delete_candidate_account' => 'boolean',
            'delete_employer_account' => 'boolean',
            'email_notification' => 'boolean',
            'email_verification' => 'boolean',
            'default_currency' => 'string|max:10',
            'session_lifetime' => 'integer|min:1',
            'max_login_attempts' => 'integer|min:1',
            'password_expiry_days' => 'integer|min:0',
            'require_strong_password' => 'boolean',
        ];
    }
}
