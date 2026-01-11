<?php

namespace App\Http\Resources\SocialPage;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\SocialPage\PostResource;
use App\Http\Resources\SocialPage\PostCommentResource;
use App\Http\Resources\SocialPage\PostCommentReplyResource;

class ConversationPageDetailResource extends JsonResource
{
    public function toArray($request): array
    {
        // dynamically get comment and reply
        $commentResource = null;
        $replyResource = null;

        if ($this->type === 'comment' && $this->comment) {
            $commentResource = new PostCommentResource($this->comment);
        }

        if ($this->type === 'reply' && $this->reply) {
            $replyResource = new PostCommentReplyResource($this->reply);
            $commentResource = $this->reply->comment ? new PostCommentResource($this->reply->comment) : null;
        }

        return [
            'conversation_id' => $this->id,
            'type'            => $this->type,
            'platform'        => $this->platform,
            'post'            => new PostResource($this->post),
            'comment'         => $commentResource,
            'reply'           => $replyResource,
        ];
    }
}
