<?php

namespace App\Http\Resources\Message;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MessageAttachmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'attachment_id' => $this->attachment_id,
            'path'          => $this->path,
            'url'           => asset('storage/' . $this->path),
        ];
    }
}
