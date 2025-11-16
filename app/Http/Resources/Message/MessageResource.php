<?php

namespace App\Http\Resources\Message;

use App\Http\Resources\Customer\CustomerResource;
use App\Http\Resources\User\UserInfoResource;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Http\Resources\Json\JsonResource;

class MessageResource extends JsonResource
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
            'conversation_id' => $this->conversation_id,
            'content' => $this->content,
            'type' => $this->type,
            'direction' => $this->direction,

            'sender' => $this->whenLoaded('sender', function () {
                if ($this->sender_type === User::class) {
                    return new UserInfoResource($this->sender);
                }
                if ($this->sender_type === Customer::class) {
                    return new CustomerResource($this->sender);
                }

                return null;
            }),

            'receiver' => $this->whenLoaded('receiver', function () {
                if ($this->receiver_type === User::class) {
                    return new UserInfoResource($this->receiver);
                }
                if ($this->receiver_type === Customer::class) {
                    return new CustomerResource($this->receiver);
                }

                return null;
            }),
            'attachments' => $this->attachments ? MessageAttachmentResource::collection($this->attachments) : [],
            'read_at' => $this->read_at ? $this->read_at->toDateTimeString() : null,
            'cc_email' => $this->cc_email ?? null,
            'subject' => $this->subject ?? null,
            'remarks' => $this->remarks ?? null,
            'created_at' => $this->created_at->toDateTimeString(),
            'updated_at' => $this->updated_at->toDateTimeString(),
        ];
    }
}
