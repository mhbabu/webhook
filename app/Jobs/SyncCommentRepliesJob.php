<?php

namespace App\Jobs;

use App\Models\Comment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;

class SyncCommentRepliesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $postId;

    public string $parentCommentId;

    public function __construct(int $postId, string $parentCommentId)
    {
        $this->postId = $postId;
        $this->parentCommentId = $parentCommentId;
    }

    public function handle()
    {
        $token = config('services.facebook.token');

        $url = "https://graph.facebook.com/v21.0/{$this->parentCommentId}/comments"
             .'?fields=id,message,from,created_time,parent'
             ."&filter=stream&access_token={$token}";

        $replies = Http::get($url)->json()['data'] ?? [];

        foreach ($replies as $reply) {

            // Store reply
            $db = Comment::updateOrCreate(
                [
                    'platform_comment_id' => $reply['id'],
                ],
                [
                    'post_id' => $this->postId,
                    'platform_parent_id' => $reply['parent']['id'],
                    'message' => $reply['message'] ?? null,
                    'created_time' => $reply['created_time'],
                ]
            );

            // ⬇️ recursively fetch more nested replies
            dispatch(new SyncCommentRepliesJob(
                $this->postId,
                $db->platform_comment_id
            ));
        }
    }
}
