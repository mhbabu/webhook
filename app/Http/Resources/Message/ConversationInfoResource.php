<?php

namespace App\Http\Resources\Message;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Customer\CustomerResource;
use App\Http\Resources\Message\MessageResource;

class ConversationInfoResource extends JsonResource
{
    protected $message;

    public function __construct($conversation, $message = null)
    {
        parent::__construct($conversation);
        $this->message = $message;
    }

    public function toArray($request): array
    {
        $conversation = $this->resource;
        $message      = $this->message;

        return [
            'id'                => $conversation->id,
            'platform'          => $conversation->platform,
            'trace_id'          => $conversation->trace_id,
            'customer'          => $conversation->customer ? new CustomerResource($conversation->customer) : null,
            // Use the passed message here
            'last_message'      => $message?->content ?? null,
            'last_message_at'   => $message?->created_at ? $message->created_at->toDateTimeString() : null,
            'last_message_info' => $message ? new MessageResource($message) : null,
            'started_at'        => $conversation->started_at,
            'end_at'            => $conversation->end_at,
            'wrap_up_info'      => $conversation->wrapUp ? new WrapUpConversationResource($conversation->wrapUp) : null,
            'is_ended'          => (bool) $conversation->end_at,
        ];
    }
}
