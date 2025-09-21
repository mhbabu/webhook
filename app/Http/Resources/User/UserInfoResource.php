<?php

namespace App\Http\Resources\User;

use Illuminate\Http\Resources\Json\JsonResource;

class UserInfoResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'                  => $this->id,
            'name'                => $this->name,
            'profile_picture'     => $this->getFirstMediaUrl('profile_pictures') ?: null,
        ];
    }
}
