<?php

namespace App\Http\Requests\Message;

use App\Http\Requests\BaseRequest;

/**
 * Request for getting a message.
 *
 * @package App\Http\Requests\Message
 */
class GetMessageRequest extends BaseRequest
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
