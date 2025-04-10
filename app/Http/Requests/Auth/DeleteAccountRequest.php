<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\BaseRequest;

class DeleteAccountRequest extends BaseRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'password' => 'required|string',
            'permanent' => 'nullable|boolean',
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
            'password.required' => 'The password field is required.',
            'permanent.boolean' => 'The permanent field must be a boolean value.',
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
            'password' => 'password',
            'permanent' => 'permanent deletion flag',
        ];
    }
}
