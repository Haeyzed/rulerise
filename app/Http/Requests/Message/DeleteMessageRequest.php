<?php

namespace App\Http\Requests\Message;

use App\Http\Requests\BaseRequest;

/**
 * Request for deleting a message.
 *
 * @package App\Http\Requests\Message
 */
class DeleteMessageRequest extends BaseRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [];
    }
}
