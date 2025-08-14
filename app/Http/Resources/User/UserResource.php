<?php

namespace App\Http\Resources\User;

use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'email'       => $this->email,
            'employee_id' => $this->employee_id,
            'role'        => $this->type,
            'max_limit'   => $this->max_limit,
            'type'        => $this->type,
            'mobile'      => $this->mobile
        ];
    }
}
