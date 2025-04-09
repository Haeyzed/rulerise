<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\BaseRequest;
use Illuminate\Foundation\Http\FormRequest;

class VerifyEmailRequest extends FormRequest
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
     * @return array
     */
    public function rules(): array
    {
        return [
            'id' => 'required|integer|exists:users,id',
            'hash' => 'required|string',
            'signature' => 'required|string',
            'expires' => 'required|integer',
        ];
    }
}