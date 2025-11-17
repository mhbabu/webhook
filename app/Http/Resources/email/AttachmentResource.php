<?php

namespace App\Http\Resources\Email;

use Illuminate\Http\Resources\Json\JsonResource;

class AttachmentResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'path' => $this->path,
            'mime' => $this->mime,
            'size' => $this->size,
            'download_url' => route('attachments.download', $this->id),
        ];
    }
}
