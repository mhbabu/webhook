<?php

namespace App\Http\Resources\Thread;

use Illuminate\Http\Resources\Json\JsonResource;

class PostResource extends JsonResource
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
            'caption' => $this->caption,
            'posted_at' => $this->posted_at?->toDateTimeString(),

            'media' => PostMediaResource::collection(
                $this->whenLoaded('media')
            ),
        ];
    }
}
