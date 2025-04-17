<?php

namespace App\Http\Requests\Admin;

use App\Enums\BlogPostStatusEnum;
use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

class ListBlogPostRequest extends BaseRequest
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
             * Search term to filter blog posts.
             *
             * @query
             * @var string|null $search
             * @example "Laravel"
             */
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],

            /**
             * Status to filter blog posts.
             *
             * @query
             * @var string|null $status
             * @example "published"
             */
            'status' => ['sometimes', 'nullable', 'string', Rule::in(BlogPostStatusEnum::class)],

            /**
             * Number of items per page.
             *
             * @var int|null $per_page
             * @example 15
             */
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],

            /**
             * Page number.
             *
             * @query
             * @var int|null $page
             * @example 1
             */
            'page' => ['sometimes', 'integer', 'min:1'],

            /**
             * Field to order by.
             *
             * @query
             * @var string|null $order_by
             * @example "created_at"
             */
            'order_by' => ['sometimes', 'string', 'in:id,title,created_at,updated_at,status'],

            /**
             * Order direction.
             *
             * @query
             * @var string|null $order_direction
             * @example "desc"
             */
            'order_direction' => ['sometimes', 'string', 'in:asc,desc'],

            /**
             * Whether to show only trashed (soft deleted) items.
             *
             * @query
             * @var bool|null $trashed_only
             * @example false
             */
            'trashed_only' => ['sometimes', 'boolean'],

            /**
             * Start date for filtering by date range.
             *
             * @query
             * @var string|null $start_date
             * @example "2023-01-01"
             */
            'start_date' => ['sometimes', 'nullable', 'date', 'date_format:Y-m-d'],

            /**
             * End date for filtering by date range.
             *
             * @query
             * @var string|null $end_date
             * @example "2023-12-31"
             */
            'end_date' => ['sometimes', 'nullable', 'date', 'date_format:Y-m-d', 'after_or_equal:start_date'],
        ];
    }
}
