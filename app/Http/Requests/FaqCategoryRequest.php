<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FaqCategoryRequest extends BaseRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                Rule::unique('faq_categories', 'name')->ignore($this->faq_category->id),
            ],
            'description' => 'nullable|string',
            'order' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
        ];

//        // If slug is provided, validate it
//        if ($this->has('slug')) {
//            $slugRule = Rule::unique('faq_categories', 'slug');
//
//            // If updating, exclude the current category
//            if ($this->route('id')) {
//                $slugRule->ignore($this->route('id'));
//            }
//
//            $rules['slug'] = ['nullable', 'string', 'max:255', $slugRule];
//        }
//
//        return $rules;
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
            'slug.unique' => 'This slug is already in use.',
        ];
    }
}
