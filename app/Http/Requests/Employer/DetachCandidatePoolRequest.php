<?php

namespace App\Http\Requests\Employer;

use App\Http\Requests\BaseRequest;

/**
 * Request for detaching candidates from a pool.
 *
 * @package App\Http\Requests\Employer
 */
class DetachCandidatePoolRequest extends BaseRequest
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
     * @return array
     */
    public function rules(): array
    {
        return [
            'pool_id' => 'required|integer|exists:candidate_pools,id',
            'candidate_ids' => 'required|array',
            'candidate_ids.*' => 'integer|exists:candidates,id',
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
            'candidate_ids.required' => 'At least one candidate ID is required.',
            'candidate_ids.array' => 'Candidate IDs must be provided as an array.',
            'candidate_ids.*.exists' => 'One or more selected candidates do not exist.',
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
            'candidate_ids' => 'candidates',
        ];
    }
}
