<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

class PermissionRequest extends BaseRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->hasRole('admin');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'name' => [
                'required',
                'string',
                'max:255',
            ],
            'description' => 'nullable|string|max:1000',
        ];

        // Add unique rule for name when creating a new permission
        if ($this->isMethod('post')) {
            $rules['name'][] = Rule::unique('permissions', 'name')->where('guard_name', 'api');
        } 
        // Add unique rule for name when updating a permission, excluding the current permission
        elseif ($this->isMethod('put') || $this->isMethod('patch')) {
            $rules['id'] = 'required|exists:permissions,id';
            $rules['name'][] = Rule::unique('permissions', 'name')
                ->where('guard_name', 'api')
                ->ignore($this->id);
        }

        return $rules;
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'permission name',
            'description' => 'permission description',
        ];
    }
}
