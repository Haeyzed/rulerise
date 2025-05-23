<?php

namespace App\Http\Requests\Employer;

use App\Http\Requests\BaseRequest;

/**
 * Request for attaching a candidate to a pool.
 *
 * @package App\Http\Requests\Employer
 */
class AttachSingleCandidatePoolRequest extends BaseRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'pool_id' => 'required|integer|exists:candidate_pools,id',
            'candidate_id' => 'required|integer|exists:candidates,id',
            'notes' => 'nullable|string|max:1000',
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
            'pool_id.required' => 'The pool ID is required.',
            'pool_id.exists' => 'The selected pool does not exist.',
            'candidate_id.required' => 'The candidate ID is required.',
            'candidate_id.exists' => 'The selected candidate does not exist.',
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
            'pool_id' => 'pool',
            'candidate_id' => 'candidate',
            'notes' => 'notes',
        ];
    }
}
