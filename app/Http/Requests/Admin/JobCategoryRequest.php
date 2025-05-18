<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class JobCategoryRequest extends FormRequest
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        $id = $this->jobCategory->id;

        return [
            'name' => [
                'required',
                'string',
                Rule::unique('job_categories', 'name')->ignore($id),
            ],
            'description' => 'nullable|string|max:500',
            'icon' => 'nullable|string|max:50',
            'is_active' => 'boolean',
        ];
//
//        // If this is an update request (PUT/PATCH), add the unique rule with the current record excluded
//        if ($this->isMethod('put') || $this->isMethod('patch') || $this->route('jobCategory')) {
//            $jobCategoryId = $this->route('jobCategory') ? $this->route('jobCategory')->id : null;
//
//            if (!$jobCategoryId && $this->route('id')) {
//                $jobCategoryId = $this->route('id');
//            }
//
//            $rules['name'][] = Rule::unique('job_categories', 'name')->ignore($jobCategoryId);
//        } else {
//            // For new records, simply check uniqueness
//            $rules['name'][] = 'unique:job_categories,name';
//        }
//
//        return $rules;
    }
}
