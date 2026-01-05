<?php

namespace App\Http\Resources\Thread;

use App\Http\Resources\Customer\CustomerResource;
// use App\Models\User;
use Illuminate\Http\Resources\Json\JsonResource;

class ConversationThreadResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'post_id' => $this->comment?->post_id,
            'platform' => $this->platform,
            'trace_id' => $this->trace_id,

            'customer' => $this->customer
                ? new CustomerResource($this->customer)
                : null,

            'type' => $this->comment?->type,
            'info' => $this->comment?->message,

            'started_at' => $this->started_at?->toDateTimeString(),
            'end_at' => $this->end_at?->toDateTimeString(),
        ];
    }
}
