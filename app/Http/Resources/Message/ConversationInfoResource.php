<?php

namespace App\Http\Resources\Message;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\CustomerResource;
use App\Http\Resources\User\UserInfoResource;
use App\Http\Resources\Message\MessageAttachmentResource;
use App\Http\Resources\Message\WrapUpConversationResource;

class ConversationInfoResource extends JsonResource
{
    protected $message;

    public function __construct($resource, $message = null)
    {
        parent::__construct($resource);
        $this->resource = $resource;
        $this->message = $message;
    }

    public function toArray($request): array
    {
        return [
            'id'              => $this->id,
            'platform'        => $this->platform,
            'trace_id'        => $this->trace_id,
            'customer'        => $this->customer ? new CustomerResource($this->customer) : null,
            'last_message'    => $this->message?->content ?? null,
            'last_message_at' => $this->message?->created_at ? $this->message->created_at->toDateTimeString() : null,

            'last_message_info' => $this->message ? [
                'last_message_sender' => $this->message->sender_type === 'App\Models\User' ? 'agent' : 'customer',
                'last_message_sender_info' => $this->message->sender_type === 'App\Models\User'
                    ? new UserInfoResource($this->message->sender)
                    : new CustomerResource($this->message->sender),
                'attachments' => $this->message->attachments
                    ? MessageAttachmentResource::collection($this->message->attachments)
                    : [],
            ] : null,

            'started_at'      => $this->started_at,
            'end_at'          => $this->end_at,
            'wrap_up_info'    => $this->wrapUp ? new WrapUpConversationResource($this->wrapUp) : null,
            'is_ended'        => (bool) $this->end_at,
        ];
    }
}
