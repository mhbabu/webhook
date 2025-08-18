<?php

namespace App\Http\Resources\User;

use App\Models\Role;
use App\Models\RoleHierarchy;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserRoleResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $childRoles = Role::whereIn('id', RoleHierarchy::where('parent_role_id', $this->id)->pluck('child_role_id'))->get();

        return [
            'id'            => $this->id,
            'name'          => $this->name,
            'child_roles'   => $childRoles->map(function ($role) {
                return [
                    'id'   => $role->id,
                    'name' => $role->name
                ];
            }),
        ];
    }
}
