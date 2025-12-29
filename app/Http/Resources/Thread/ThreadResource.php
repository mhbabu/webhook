<?php

namespace App\Http\Resources\Thread;

// use App\Http\Resources\User\UserInfoResource;
// use App\Models\User;
use Illuminate\Http\Resources\Json\JsonResource;

class ThreadResource extends JsonResource
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
            'type' => $this->type,
            'message' => $this->message,
            'commented_at' => $this->commented_at?->toDateTimeString(),

            'customer' => $this->customer ? [
                'id' => $this->customer->id,
                'name' => $this->customer->name,
            ] : null,

            // optional: replies (if needed later)
            'replies' => ThreadResource::collection(
                $this->whenLoaded('replies')
            ),
        ];
    }
}
