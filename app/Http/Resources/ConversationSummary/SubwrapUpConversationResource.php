<?php

namespace App\Http\Resources\ConversationSummary;

use App\Http\Resources\Message\WrapUpConversationResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubwrapUpConversationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                   => $this->id,
            'name'                 => $this->name,
            'is_active'            => (bool) $this->is_active,
            'wrap_up_conversation' => new WrapUpConversationResource($this->whenLoaded('wrapUpConversation')),
        ];
    }
}
