<?php

namespace App\Http\Requests\Admin;

use App\Models\SubscriptionPlan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubscriptionPlanRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->hasRole('admin');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'currency' => 'required|string|size:3',
            'duration_days' => 'nullable|integer|min:1',
            'job_posts_limit' => 'required|integer|min:0',
            'featured_jobs_limit' => 'required|integer|min:0',
            'resume_views_limit' => 'required|integer|min:0',
            'job_alerts' => 'required|boolean',
            'candidate_search' => 'required|boolean',
            'resume_access' => 'required|boolean',
            'company_profile' => 'required|boolean',
            'support_level' => 'nullable|string|in:basic,standard,premium',
            'is_active' => 'required|boolean',
            'is_featured' => 'required|boolean',
            'features' => 'nullable|array',
            'payment_type' => ['required', 'string', Rule::in([SubscriptionPlan::PAYMENT_TYPE_ONE_TIME, SubscriptionPlan::PAYMENT_TYPE_RECURRING])],
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'job_posts_limit' => 'job posts limit',
            'featured_jobs_limit' => 'featured jobs limit',
            'resume_views_limit' => 'resume views limit',
            'job_alerts' => 'job alerts',
            'candidate_search' => 'candidate search',
            'resume_access' => 'resume access',
            'company_profile' => 'company profile',
            'support_level' => 'support level',
            'is_active' => 'active status',
            'is_featured' => 'featured status',
            'payment_type' => 'payment type',
        ];
    }
}
