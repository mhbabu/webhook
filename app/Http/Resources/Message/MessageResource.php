<?php

namespace App\Http\Resources\Message;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\UserResource;
use App\Http\Resources\CustomerResource;
use App\Http\Resources\User\UserResource as UserUserResource;
use App\Models\User;
use App\Models\Customer;

class MessageResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'id'              => $this->id,
            'conversation_id' => $this->conversation_id,
            'content'         => $this->content,
            'type'            => $this->type,
            'direction'       => $this->direction,

            'sender' => $this->whenLoaded('sender', function () {
                if ($this->sender_type === User::class) {
                    return new UserUserResource($this->sender);
                }
                if ($this->sender_type === Customer::class) {
                    return new CustomerResource($this->sender);
                }
                return null;
            }),

            'receiver' => $this->whenLoaded('receiver', function () {
                if ($this->receiver_type === User::class) {
                    return new UserUserResource($this->receiver);
                }
                if ($this->receiver_type === Customer::class) {
                    return new CustomerResource($this->receiver);
                }
                return null;
            }),

            'read_at'    => $this->read_at ? $this->read_at->toDateTimeString() . ' UTC' : null,
            'created_at' => $this->created_at->toDateTimeString() . ' UTC',
            'updated_at' => $this->updated_at->toDateTimeString() . ' UTC',
        ];
    }
}
