<?php

namespace App\Http\Resources\ConversationSummary;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConversationTypeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'is_active' => (bool) ($this->is_active ?? true),
            // 'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}
