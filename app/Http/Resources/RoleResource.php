<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\Permission\Models\Role;

/**
 * @property Role $resource
 */
class RoleResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param Request $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            /**
             * The unique identifier of the role.
             *
             * @var int $id
             * @example 1
             */
            'id' => $this->id,

            /**
             * The name of the role.
             *
             * @var string $name
             * @example "SADMIN"
             */
            'name' => $this->name,

            /**
             * The label of the role.
             *
             * @var string $label
             * @example "Super Admin"
             */
            'label' => $this->label,

            /**
             * The description of the role.
             *
             * @var string|null $description
             * @example "Manage Roles"
             */
            'description' => $this->description,

//            /**
//             * The group of the role.
//             *
//             * @var string $group
//             * @example "System Management"
//             */
//            'group' => $this->group,
//
//            /**
//             * The active status of the role.
//             *
//             * @var string $status
//             * @example active
//             */
//            'status' => $this->status,

//            /**
//             * The system status of the role.
//             *
//             * @var string $is_system
//             * @example true
//             */
//            'is_system' => (bool)$this->is_system,

//            /**
//             * The permissions assigned to the role (loaded when the relationship is loaded).
//             *
//             * @var PermissionResource $permissions
//             * @example [{"id": 1, "name": "create-post"}, {"id": 2, "name": "edit-user"}]
//             */
            /**
             * The permissions assigned to the role (loaded when the relationship is loaded).
             *
             * @var array|null $permissions
             * @example [{"id": 1, "name": "create-post", "label": "Create Post"}, {"id": 2, "name": "edit-user", "label": "Edit User"}]
             */
            'permissions' => $this->whenLoaded('permissions', function () {
                return $this->permissions->map(function ($permission) {
                    return [
                        'id' => $permission->id,
                        'name' => $permission->name,
                        'label' => $permission->label,
                    ];
                });
            }),

            /**
             * The creation timestamp of the role.
             *
             * @var string $created_at
             * @example "2023-06-01T12:00:00Z"
             */
            'created_at' => $this->created_at,

            /**
             * The last update timestamp of the role.
             *
             * @var string $updated_at
             * @example "2023-06-01T12:00:00Z"
             */
            'updated_at' => $this->updated_at,
        ];
    }
}
