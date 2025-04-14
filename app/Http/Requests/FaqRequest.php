<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FaqRequest extends BaseRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'question' => 'required|string|max:255',
            'answer' => 'required|string',
            'faq_category_id' => 'nullable|exists:faq_categories,id',
            'order' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
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
            'question.required' => 'The question field is required.',
            'answer.required' => 'The answer field is required.',
            'faq_category_id.exists' => 'The selected category does not exist.',
        ];
    }
}
