<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreatePaymentRequest extends FormRequest
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'plan_id' => 'required|exists:plans,id',
            'payment_provider' => 'required|in:stripe,paypal',
        ];
    }

    public function messages(): array
    {
        return [
            'plan_id.required' => 'Please select a plan',
            'plan_id.exists' => 'Selected plan does not exist',
            'payment_provider.required' => 'Please select a payment provider',
            'payment_provider.in' => 'Invalid payment provider selected',
        ];
    }
}
