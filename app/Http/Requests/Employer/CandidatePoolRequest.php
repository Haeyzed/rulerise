<?php

namespace App\Http\Requests\Employer;

use App\Http\Requests\BaseRequest;

/**
 * Request for creating or updating a candidate pool.
 *
 * @package App\Http\Requests\Employer
 */
class CandidatePoolRequest extends BaseRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'id' => 'sometimes|required|integer|exists:candidate_pools,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'is_active' => 'boolean',
            'job_id' => 'nullable|integer|exists:jobs,id',
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
            'name.required' => 'The pool name field is required.',
            'job_id.exists' => 'The selected job is invalid.',
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
            'id' => 'pool ID',
            'name' => 'pool name',
            'description' => 'description',
            'is_active' => 'active status',
            'job_id' => 'job',
        ];
    }
}
