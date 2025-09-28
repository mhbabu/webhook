<?php

namespace App\Http\Resources\Message;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\CustomerResource;
use App\Http\Resources\User\UserInfoResource;
use App\Models\User;
use App\Models\Customer;
use App\Models\MessageAttachment;

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
                    return new UserInfoResource($this->sender);
                }
                if ($this->sender_type === Customer::class) {
                    return new CustomerResource($this->sender);
                }
                return null;
            }),

            'receiver' => $this->whenLoaded('receiver', function () {
                if ($this->receiver_type === User::class) {
                    return new UserInfoResource($this->receiver);
                }
                if ($this->receiver_type === Customer::class) {
                    return new CustomerResource($this->receiver);
                }
                return null;
            }),

            'attachment' => $this->attachment ? new MessageAttachment($this->attachment) : null,
            'read_at'    => $this->read_at ? $this->read_at->toDateTimeString() . ' UTC' : null,
            'created_at' => $this->created_at->toDateTimeString() . ' UTC',
            'updated_at' => $this->updated_at->toDateTimeString() . ' UTC',
        ];
    }
}
