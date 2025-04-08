<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\BaseRequest;

/**
 * Request for creating or updating a role.
 *
 * @package App\Http\Requests\Admin
 */
class RoleRequest extends BaseRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        $rules = [
            'name' => 'required|string|max:255|unique:roles,name',
            'permissions' => 'required|array',
            'permissions.*' => 'exists:permissions,id',
            'description' => 'nullable|string|max:1000',
        ];

        // If we're updating an existing role
        if ($this->isMethod('PUT') || $this->isMethod('PATCH')) {
            $rules['name'] = 'required|string|max:255|unique:roles,name,' . $this->route('id');
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
            'name.required' => 'The role name field is required.',
            'name.unique' => 'This role name already exists.',
            'permissions.required' => 'Please select at least one permission.',
            'permissions.array' => 'Permissions must be an array.',
            'permissions.*.exists' => 'One or more selected permissions are invalid.',
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
            'name' => 'role name',
            'permissions' => 'permissions',
            'description' => 'description',
        ];
    }
}
