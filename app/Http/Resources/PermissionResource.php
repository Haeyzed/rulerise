<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\Permission\Models\Permission;

/**
 * @property Permission $resource
 */
class PermissionResource extends JsonResource
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
             * The unique identifier of the permission.
             *
             * @var int $id
             * @example 1
             */
            'id' => $this->id,

            /**
             * The name of the permission.
             *
             * @var string $name
             * @example "create users"
             */
            'name' => $this->name,

            /**
             * The label of the permission.
             *
             * @var string $label
             * @example "Regional Head"
             */
            'label' => $this->label,

            /**
             * The description of the permission.
             *
             * @var string $description
             * @example "Manage permissions"
             */
            'description' => $this->description,

            /**
             * The group of the permission.
             *
             * @var string $group
             * @example "Permission Management"
             */
            'group' => $this->group,

            /**
             * The active status of the permission.
             *
             * @var string $status
             * @example active
             */
            'status' => $this->status,

            /**
             * The system status of the permission.
             *
             * @var string $is_system
             * @example true
             */
            'is_system' => (bool)$this->is_system,

            /**
             * The roles that have this permission.
             *
             * @var RoleResource $roles
             * @example [{"id": 1, "name": "admin"}, {"id": 2, "name": "editor"}]
             */
            'roles' => $this->whenLoaded('roles', function () {
                return $this->roles->map(function ($role) {
                    return [
                        'id' => $role->id,
                        'name' => $role->name,
                        'label' => $role->label,
                    ];
                });
            }),

            /**
             * The creation timestamp of the permission.
             *
             * @var string $created_at
             * @example "2023-06-01T12:00:00Z"
             */
            'created_at' => $this->created_at,

            /**
             * The last update timestamp of the permission.
             *
             * @var string $updated_at
             * @example "2023-06-01T12:00:00Z"
             */
            'updated_at' => $this->updated_at,
        ];
    }
}
