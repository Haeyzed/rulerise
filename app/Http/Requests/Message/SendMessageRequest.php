<?php

namespace App\Http\Requests\Message;

use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

/**
 * Request for sending a message.
 *
 * @package App\Http\Requests\Message
 */
class SendMessageRequest extends BaseRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'receiver_id' => [
                'required',
                'integer',
                'exists:users,id',
                Rule::notIn([auth()->id()]), // Cannot send message to self
            ],
            'subject' => 'required|string|max:255',
            'message' => 'required|string|max:5000',
            'job_id' => 'nullable|integer|exists:job_listings,id',
            'application_id' => 'nullable|integer|exists:job_applications,id',
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
            'receiver_id.required' => 'The receiver is required.',
            'receiver_id.exists' => 'The selected receiver does not exist.',
            'receiver_id.not_in' => 'You cannot send a message to yourself.',
            'subject.required' => 'The subject field is required.',
            'message.required' => 'The message field is required.',
            'job_id.exists' => 'The selected job does not exist.',
            'application_id.exists' => 'The selected application does not exist.',
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
            'receiver_id' => 'receiver',
            'subject' => 'subject',
            'message' => 'message',
            'job_id' => 'job',
            'application_id' => 'application',
        ];
    }
}
