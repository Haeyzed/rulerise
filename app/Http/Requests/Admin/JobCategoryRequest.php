<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\BaseRequest;

/**
 * Request for creating or updating a job category.
 *
 * @package App\Http\Requests\Admin
 */
class JobCategoryRequest extends BaseRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255|unique:job_categories,name,'.$this->route('id'),
            'description' => 'nullable|string|max:1000',
            'icon' => 'nullable|string|max:50',
            'is_active' => 'boolean',
            'parent_id' => 'nullable|integer|exists:job_categories,id',
            'display_order' => 'nullable|integer|min:0',
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
            'name.required' => 'The category name field is required.',
            'name.unique' => 'This category name already exists.',
            'parent_id.exists' => 'The selected parent category is invalid.',
            'display_order.integer' => 'The display order must be an integer.',
            'display_order.min' => 'The display order must be at least 0.',
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
            'name' => 'category name',
            'description' => 'description',
            'icon' => 'icon',
            'is_active' => 'active status',
            'parent_id' => 'parent category',
            'display_order' => 'display order',
        ];
    }
}
