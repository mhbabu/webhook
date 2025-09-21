<?php

namespace App\Http\Resources\Message;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\UserResource;
use App\Http\Resources\CustomerResource;
use App\Http\Resources\User\UserResource as UserUserResource;
use App\Models\WrapUpConversation;

class ConversationResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'              => $this->id,
            'platform'        => $this->platform,
            'trace_id'        => $this->trace_id,
            'customer'        => $this->whenLoaded('customer', function () {
                return new CustomerResource($this->customer);
            }),
            'last_message'    => $this->lastMessage?->content ?? null,
            'last_message_at' => $this->lastMessage?->created_at ? $this->lastMessage->created_at->toDateTimeString() . ' UTC' : null,
            'started_at'      => $this->started_at,
            'end_at'          => $this->end_at,
            'wrap_up_info'    => $this->whenLoaded('wrapUp', function () {
                return $this->wrapUp ? new WrapUpConversation($this->wrapUp) : null;
            }),
            'is_ended'        => $this->end_at ? true : false,
        ];
    }
}
