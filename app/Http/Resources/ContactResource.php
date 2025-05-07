<?php

namespace App\Http\Resources;

use App\Models\Contact;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Class ContactResource
 *
 * @package App\Http\Resources
 *
 * @property Contact $resource
 */
class ContactResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            /**
             * The unique identifier of the contact.
             *
             * @var int $id
             * @example 1
             */
            'id' => $this->id,

            /**
             * The title of the contact.
             *
             * @var string $title
             * @example "Email"
             */
            'title' => $this->title,

            /**
             * The value of the contact.
             *
             * @var string $value
             * @example "contact@example.com"
             */
            'value' => $this->value,

            /**
             * The type of the contact.
             *
             * @var string $type
             * @example "email"
             */
            'type' => $this->type,

            /**
             * The formatted value of the contact.
             *
             * @var string $formatted_value
             * @example "mailto:contact@example.com"
             */
            'formatted_value' => $this->formatted_value,

            /**
             * The display order of the contact.
             *
             * @var int $order
             * @example 1
             */
            'order' => $this->order,

            /**
             * Whether the contact is active.
             *
             * @var bool $is_active
             * @example true
             */
            'is_active' => $this->is_active,

            /**
             * The creation timestamp of the contact.
             *
             * @var string|null $created_at
             * @example "2024-05-07 12:30:00"
             */
            'created_at' => $this->created_at,

            /**
             * The last update timestamp of the contact.
             *
             * @var string|null $updated_at
             * @example "2024-05-07 15:45:00"
             */
            'updated_at' => $this->updated_at,

            /**
             * The deletion timestamp of the contact (if soft deleted).
             *
             * @var string|null $deleted_at
             * @example null
             */
            'deleted_at' => $this->deleted_at,
        ];
    }
}
