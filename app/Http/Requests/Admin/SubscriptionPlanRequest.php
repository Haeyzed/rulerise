<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\BaseRequest;

/**
 * Request for creating or updating a subscription plan.
 *
 * @package App\Http\Requests\Admin
 */
class SubscriptionPlanRequest extends BaseRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        $rules = [
            'name' => 'required|string|max:255|unique:subscription_plans,name',
            'description' => 'nullable|string|max:1000',
            'price' => 'required|numeric|min:0',
            'currency' => 'required|string|size:3',
            'duration_days' => 'required|integer|min:1',
            'features' => 'nullable|array',
            'features.*' => 'string|max:255',
            'max_jobs' => 'nullable|integer|min:0',
            'max_featured_jobs' => 'nullable|integer|min:0',
            'max_cv_views' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'display_order' => 'nullable|integer|min:0',
            'trial_days' => 'nullable|integer|min:0',
        ];

        // If we're updating an existing subscription plan
        if ($this->isMethod('PUT') || $this->isMethod('PATCH')) {
            $rules['name'] = 'required|string|max:255|unique:subscription_plans,name,' . $this->route('id');
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
            'name.required' => 'The plan name field is required.',
            'name.unique' => 'This plan name already exists.',
            'price.required' => 'The price field is required.',
            'price.numeric' => 'The price must be a number.',
            'price.min' => 'The price must be at least 0.',
            'currency.required' => 'The currency field is required.',
            'currency.size' => 'The currency must be a 3-letter code (e.g., USD).',
            'duration_days.required' => 'The duration field is required.',
            'duration_days.integer' => 'The duration must be an integer.',
            'duration_days.min' => 'The duration must be at least 1 day.',
            'features.array' => 'Features must be an array.',
            'features.*.string' => 'Each feature must be a string.',
            'max_jobs.integer' => 'The maximum jobs must be an integer.',
            'max_jobs.min' => 'The maximum jobs must be at least 0.',
            'max_featured_jobs.integer' => 'The maximum featured jobs must be an integer.',
            'max_featured_jobs.min' => 'The maximum featured jobs must be at least 0.',
            'max_cv_views.integer' => 'The maximum CV views must be an integer.',
            'max_cv_views.min' => 'The maximum CV views must be at least 0.',
            'display_order.integer' => 'The display order must be an integer.',
            'display_order.min' => 'The display order must be at least 0.',
            'trial_days.integer' => 'The trial days must be an integer.',
            'trial_days.min' => 'The trial days must be at least 0.',
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
            'name' => 'plan name',
            'description' => 'description',
            'price' => 'price',
            'currency' => 'currency',
            'duration_days' => 'duration',
            'features' => 'features',
            'max_jobs' => 'maximum jobs',
            'max_featured_jobs' => 'maximum featured jobs',
            'max_cv_views' => 'maximum CV views',
            'is_active' => 'active status',
            'is_featured' => 'featured status',
            'display_order' => 'display order',
            'trial_days' => 'trial days',
        ];
    }
}
