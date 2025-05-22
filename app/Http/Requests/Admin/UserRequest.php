<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\BaseRequest;

/**
 * Request for creating or updating a user.
 *
 * @package App\Http\Requests\Admin
 */
class UserRequest extends BaseRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        $rules = [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email',
//            'password' => 'required|string|min:8|confirmed',
            'roles' => 'required|array',
            'roles.*' => 'exists:roles,id',
            'is_active' => 'boolean',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'zip_code' => 'nullable|string|max:20',
            'profile_picture' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ];

        // If we're updating an existing user
        if ($this->isMethod('PUT') || $this->isMethod('PATCH')) {
            $rules['email'] = 'required|string|email|max:255|unique:users,email,' . $this->route('id');
            $rules['password'] = 'nullable|string|min:8|confirmed';
        }

        return $rules;
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array
     */
    public function messages(): array
    {
        return [
            'name.required' => 'The name field is required.',
            'email.required' => 'The email field is required.',
            'email.email' => 'Please enter a valid email address.',
            'email.unique' => 'This email is already registered.',
            'password.required' => 'The password field is required.',
            'password.min' => 'The password must be at least 8 characters.',
            'password.confirmed' => 'The password confirmation does not match.',
            'roles.required' => 'Please select at least one role.',
            'roles.array' => 'Roles must be an array.',
            'roles.*.exists' => 'One or more selected roles are invalid.',
            'profile_picture.image' => 'The profile picture must be an image.',
            'profile_picture.mimes' => 'The profile picture must be a JPEG, PNG, JPG, or GIF file.',
            'profile_picture.max' => 'The profile picture size must not exceed 2MB.',
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
            'name' => 'name',
            'email' => 'email address',
            'password' => 'password',
            'roles' => 'roles',
            'is_active' => 'active status',
            'phone' => 'phone number',
            'address' => 'address',
            'city' => 'city',
            'state' => 'state',
            'country' => 'country',
            'zip_code' => 'zip code',
            'profile_picture' => 'profile picture',
        ];
    }
}
