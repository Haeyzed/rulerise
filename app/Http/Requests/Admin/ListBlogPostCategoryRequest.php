<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\BaseRequest;

class ListBlogPostCategoryRequest extends BaseRequest
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
             * The search term to filter categories by name or description.
             *
             * @var string|null $search
             * @example "tech"
             */
            'search' => ['nullable', 'string', 'max:255'],

            /**
             * Whether to include only active categories.
             *
             * @var bool|null $active_only
             * @example true
             */
            'active_only' => ['nullable', 'boolean'],

            /**
             * Whether to include soft deleted categories.
             *
             * @var bool|null $trashed_only
             * @example false
             */
            'trashed_only' => ['nullable', 'boolean'],

            /**
             * The field to order the results by.
             *
             * @var string|null $order_by
             * @example "name"
             */
            'order_by' => ['nullable', 'string', 'in:id,name,created_at,updated_at,order'],

            /**
             * The direction to order the results.
             *
             * @var string|null $order_direction
             * @example "asc"
             */
            'order_direction' => ['nullable', 'string', 'in:asc,desc'],

            /**
             * The number of items per page.
             *
             * @var int|null $per_page
             * @example 15
             */
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],

            /**
             * The start date for filtering by creation date.
             *
             * @var string|null $start_date
             * @example "2023-01-01"
             */
            'start_date' => ['nullable', 'date', 'date_format:Y-m-d'],

            /**
             * The end date for filtering by creation date.
             *
             * @var string|null $end_date
             * @example "2023-12-31"
             */
            'end_date' => ['nullable', 'date', 'date_format:Y-m-d', 'after_or_equal:start_date'],
        ];
    }
}