<?php

namespace App\Http\Resources\User;

use App\Models\Role;
use App\Models\RoleHierarchy;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserRoleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Get child roles via RoleHierarchy
        $childRoleIds = RoleHierarchy::where('parent_role_id', $this->id)->pluck('child_role_id');
        $childRoles   = Role::whereIn('id', $childRoleIds)->orderBy('id')->get(['id', 'name']);

        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'child_roles' => $childRoles
        ];
    }
}
