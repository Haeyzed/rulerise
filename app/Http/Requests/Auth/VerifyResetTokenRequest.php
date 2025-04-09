<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\BaseRequest;

/**
 * Request for verifying reset token.
 *
 * @package App\Http\Requests\Auth
 */
class VerifyResetTokenRequest extends BaseRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'email' => 'required|string|email',
            'token' => 'required|string',
            'user_type' => 'nullable|string|in:candidate,employer,admin',
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
            'email.required' => 'The email field is required.',
            'email.email' => 'Please enter a valid email address.',
            'token.required' => 'The token field is required.',
            'user_type.in' => 'The user type must be candidate, employer, or admin.',
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
            'email' => 'email address',
            'token' => 'token',
            'user_type' => 'user type',
        ];
    }
}
