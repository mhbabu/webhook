<?php

namespace App\Http\Resources\Customer;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerInfoResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $token          = $this->is_verified && $this->token ? $this->token : null;
        $tokenExpiresAt = $this->is_verified && $this->token_expires_at ? $this->token_expires_at : null;
        return [
            'id'                 => $this->id,
            'name'               => $this->name ?? null,
            'email'              => $this->email ?? null,
            'phone'              => $this->phone ?? null,
            'type'               => 'customer',
            'is_verified'        => $this->is_verified == 1 ? true : false,
            'token'              => $token,
            'token_expires_at'   => $tokenExpiresAt,
            'profile_photo'      => $this->getFirstMediaUrl('profile_photo') ?: null,
        ];
    }
}