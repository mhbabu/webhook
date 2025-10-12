<?php

namespace App\Http\Resources\User;

use App\Http\Resources\Webhook\PlatformResource;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'                  => $this->id,
            'name'                => $this->name,
            'email'               => $this->email,
            'employee_id'         => $this->employee_id,
            'role_id'             => $this->role_id,
            'role'                => $this->role->name ?? null,
            'max_limit'           => $this->max_limit,
            'current_limit'       => $this->current_limit,
            'mobile'              => $this->mobile,
            'is_password_updated' => boolval($this->is_password_updated),
            'permissions'         => [], //$this->getAllPermissions(),
            'current_status'      => $this->current_status,
            'status_info'         => $this->userStatusInfo ?? null,
            'profile_picture'     => $this->getFirstMediaUrl('profile_pictures') ?: null,
            'platforms'        => $this->platforms ? PlatformResource::collection($this->platforms) : [],
        ];
    }
}