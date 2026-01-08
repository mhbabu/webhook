<?php

namespace App\Http\Resources\SocialPage;

use App\Http\Resources\Customer\CustomerResource;
use App\Models\Customer;
use App\Models\Post;
use App\Models\PostComment;
use App\Models\PostCommentReply;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConversationPageDetailResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'conversation_id' => $this->id,
            'type'            => $this->type,
            'platform'        => $this->platform,
            'post'            => new PostResource($this->post), 
        ];
    }
}
