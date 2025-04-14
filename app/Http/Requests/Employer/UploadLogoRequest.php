<?php

namespace App\Http\Requests\Employer;

use App\Http\Requests\BaseRequest;
use Illuminate\Foundation\Http\FormRequest;

class UploadLogoRequest extends BaseRequest
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

            /**
             * The banner image for the blog post.
             *
             * This field is required for new posts and optional for updates.
             *
             * @var string|null $company_logo
             * @example "banner.jpg"
             */
            'company_logo' => [
                $this->isMethod('post') ? 'required' : 'nullable',
                'image',
                'mimes:jpeg,png,jpg,gif',
                'max:2048'
            ],
        ];
    }
}
