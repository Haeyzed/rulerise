<?php

namespace App\Http\Requests\Employer;

use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

/**
 * Request for updating a staff user for an employer.
 *
 * @package App\Http\Requests\Employer
 */
class UpdateUserRequest extends BaseRequest
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
     * @return array
     */
    public function rules(): array
    {
        return [
            'first_name' => 'sometimes|string|max:255',
            'last_name' => 'sometimes|string|max:255',
            'email' => [
                'required',
                'email',
                Rule::unique('users')->ignore($this->route('id'))->where(function ($query) {
                    return $query->where('user_type', $this->input('user_type', 'employer_staff'));
                }),
            ],
            'phone' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'city' => 'nullable|string|max:100',
            'is_active' => 'sometimes|boolean',
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
            'id.required' => 'The user ID is required.',
            'id.exists' => 'The selected user does not exist.',
            'email.email' => 'Please enter a valid email address.',
            'email.unique' => 'This email is already registered.',
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
            'id' => 'user ID',
            'first_name' => 'first name',
            'last_name' => 'last name',
            'email' => 'email address',
            'phone' => 'phone number',
            'country' => 'country',
            'state' => 'state',
            'city' => 'city',
            'is_active' => 'active status',
        ];
    }
}
