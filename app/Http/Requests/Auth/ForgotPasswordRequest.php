<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\BaseRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\ValidationException;
use App\Models\User; // Adjust this based on your actual User model location

/**
 * Request for forgot password.
 *
 * @package App\Http\Requests\Auth
 */
class ForgotPasswordRequest extends BaseRequest
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
            'user_type' => 'nullable|string|in:candidate,employer,admin',
        ];
    }

    /**
     * Configure the validator instance.
     *
     * @param  \Illuminate\Contracts\Validation\Validator  $validator
     * @return void
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            $query = User::query()->where('email', $this->email);

            if ($this->filled('user_type')) {
                $query->where('user_type', $this->user_type);
            }

            if (!$query->exists()) {
                $validator->errors()->add('email', 'No user found with this email and user type.');
            }
        });
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
            'user_type' => 'user type',
        ];
    }
}
