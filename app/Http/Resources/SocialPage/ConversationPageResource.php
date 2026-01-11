<?php

namespace App\Http\Resources\SocialPage;

use App\Http\Resources\Customer\CustomerResource;
use App\Models\Customer;
use App\Models\PostComment;
use App\Models\PostCommentReply;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConversationPageResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'id'             => $this->id,
            'post_id'        => $this->post_id,
            'platform'       => $this->platform,
            'trace_id'       => $this->trace_id,
            'customer'       => $this->customer ? new CustomerResource($this->customer) : null,
            'type'           => $this->type,
            'info'           => Customer::find($this->customer_id)->name . ' ' . ($this->type === 'comment' ? 'commented' : 'replied a comment'),
            'started_at'     => $this->started_at?->toDateTimeString(),
            'end_at'         => $this->end_at?->toDateTimeString(),
            'wrap_up_info'   => null,
            'is_ended'       => $this->end_at ? true : false,
        ];
    }
}
