<?php

namespace App\Http\Resources\ConversationSummary;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WrapUpSubConversationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            // 'id' => $this->id,
            // 'wrap_up_conversation_id' => $this->wrap_up_conversation_id,
            'name' => $this->name,
            'is_active' => (bool) $this->is_active,
            'wrap_up_conversation' => $this->whenLoaded('wrapUpConversation', function () {
                return [
                    'id' => $this->wrapUpConversation->id,
                    'name' => $this->wrapUpConversation->name,
                ];
            }),
            // 'created_at' => $this->created_at?->toDateTimeString(),
            // 'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
