<?php

namespace App\Http\Resources\Customer;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Customer\CustomerResource;
use App\Http\Resources\User\UserInfoResource;
use App\Http\Resources\Message\MessageAttachmentResource;
use App\Http\Resources\Message\MessageResource;
use App\Http\Resources\Message\WrapUpConversationResource;

class CustomerConversationInfoResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'              => $this->id,
            'platform'        => $this->platform,
            'trace_id'        => $this->trace_id,
            'customer'        => $this->customer ? new CustomerResource($this->customer) : null,
            'last_message'    => $this->lastMessage?->content ?? null,
            'last_message_at' => $this->lastMessage?->created_at ? $this->lastMessage->created_at->toDateTimeString() : null,

            'last_message_info' => $this->lastMessage ? [
                'last_message_sender' => $this->lastMessage->sender_type === 'App\Models\User' ? 'agent' : 'customer',
                'last_message_sender_info' => $this->lastMessage->sender_type === 'App\Models\User' ? new UserInfoResource($this->lastMessage->sender) : new CustomerResource($this->lastMessage->sender),
                'attachments' => $this->lastMessage->attachments ? MessageAttachmentResource::collection($this->lastMessage->attachments) : [], 
            ] : null,

            'started_at'      => $this->started_at,
            'end_at'          => $this->end_at,
            'wrap_up_info'    => $this->wrapUp ? new WrapUpConversationResource($this->wrapUp) : null,
            'is_ended'        => (bool) $this->end_at,
            'messages'        => MessageResource::collection($this->messages),
        ];
    }
}
