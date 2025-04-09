<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\BaseRequest;

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
            'email' => 'required|email|exists:users,email',
            'token' => 'required|string',
        ];
    }
}