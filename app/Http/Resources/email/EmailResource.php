<?php

namespace App\Http\Resources\Message;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class emailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'subject' => $this->subject,
            'cc_email' => $this->cc_email,
        ];
    }
}
