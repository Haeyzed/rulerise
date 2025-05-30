<?php

namespace App\Http\Requests\Employer;

use App\Http\Requests\BaseRequest;

/**
 * Request for detaching candidates from multiple pools.
 *
 * @package App\Http\Requests\Employer
 */
class DetachCandidatesMultiPoolRequest extends BaseRequest
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
            'pool_ids' => 'required|array',
            'pool_ids.*' => 'integer|exists:candidate_pools,id',
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
            'pool_ids.required' => 'At least one pool ID is required.',
            'pool_ids.array' => 'Pool IDs must be provided as an array.',
            'pool_ids.*.exists' => 'One or more selected pools do not exist.',
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
            'pool_ids' => 'pools',
            'candidate_ids' => 'candidates',
        ];
    }
}
