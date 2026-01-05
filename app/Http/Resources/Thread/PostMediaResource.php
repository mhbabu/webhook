<?php

namespace App\Http\Resources\Thread;

use Illuminate\Http\Resources\Json\JsonResource;

class PostMediaResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            // 'id' => $this->id,
            'type' => $this->type,
            'url' => $this->url ?? $this->media_url ?? null,
        ];
    }
}
