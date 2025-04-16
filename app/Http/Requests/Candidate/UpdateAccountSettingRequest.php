<?php

namespace App\Http\Requests\Candidate;

use App\Http\Requests\BaseRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAccountSettingRequest extends BaseRequest
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'other_name' => 'nullable|string|max:255',
            'email' => [
                'required',
                'email',
                Rule::unique('users')->where(function ($query) {
                    return $query->where('user_type', $this->input('user_type'));
                }),
            ],
            'phone' => 'nullable|string|max:20',
            'phone_country_code' => 'nullable|string|max:10',
        ];
    }
}
