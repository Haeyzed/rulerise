<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
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
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => [
                'required',
                'email',
                Rule::unique('users', 'email')->where(function ($query) {
                    return $query->where('user_type', 'admin')->ignore($this->id);
                }),
            ],
//            'email' => [
//                'sometimes',
//                'string',
//                'email',
//                'max:255',
//                Rule::unique('users', 'email')->ignore($this->id),
//            ],
//            'password' => ['nullable', Password::defaults()],
            'role' => 'nullable|string|exists:roles,name',
//            'permissions' => 'nullable|array',
//            'permissions.*' => 'exists:permissions,name',
//            'is_active' => 'boolean',
        ];
    }
}
