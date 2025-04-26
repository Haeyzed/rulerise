<?php

namespace App\Http\Requests\Employer;

use App\Services\SubscriptionService;
use Illuminate\Foundation\Http\FormRequest;

class VerifySubscriptionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->isEmployer();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'plan_id' => 'required|exists:subscription_plans,id',
            'gateway' => 'required|in:' . SubscriptionService::GATEWAY_STRIPE . ',' . SubscriptionService::GATEWAY_PAYPAL,
            'session_id' => 'required_if:gateway,' . SubscriptionService::GATEWAY_STRIPE,
            'paypal_order_id' => 'required_if:gateway,' . SubscriptionService::GATEWAY_PAYPAL,
            'receipt' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
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
            'plan_id.required' => 'The subscription plan is required.',
            'plan_id.exists' => 'The selected subscription plan is invalid.',
            'gateway.required' => 'The payment gateway is required.',
            'gateway.in' => 'The selected payment gateway is invalid.',
            'session_id.required_if' => 'The Stripe session ID is required when using Stripe.',
            'paypal_order_id.required_if' => 'The PayPal order ID is required when using PayPal.',
            'receipt.file' => 'The receipt must be a file.',
            'receipt.mimes' => 'The receipt must be a PDF, JPG, JPEG, or PNG file.',
            'receipt.max' => 'The receipt may not be greater than 2MB.',
        ];
    }
}
