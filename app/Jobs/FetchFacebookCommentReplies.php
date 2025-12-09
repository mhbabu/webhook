<?php

namespace App\Jobs;

use App\Models\Post;
use App\Services\SocialSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;

class FetchFacebookCommentReplies implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $postId;

    public string $commentFbId;

    public int $depth;

    public function __construct(int $postId, string $commentFbId, int $depth = 1)
    {
        $this->postId = $postId;
        $this->commentFbId = $commentFbId;
        $this->depth = $depth;
    }

    public function handle(SocialSyncService $syncService)
    {
        $token = config('services.facebook.token');

        $url = "https://graph.facebook.com/v24.0/{$this->commentFbId}/comments";
        $res = Http::get($url, [
            'fields' => 'id,from,message,parent,created_time',
            'limit' => 100,
            'access_token' => $token,
        ]);

        $post = Post::find($this->postId);
        if (! $post || ! $res->successful()) {
            return;
        }

        foreach ($res->json('data', []) as $reply) {

            $comment = $syncService->upsertComment($post, [
                'platform_comment_id' => $reply['id'],
                'platform_parent_id' => $reply['parent']['id'] ?? null,
                'author_platform_id' => $reply['from']['id'] ?? null,
                'author_name' => $reply['from']['name'] ?? null,
                'message' => $reply['message'] ?? null,
                'commented_at' => $reply['created_time'] ?? null,
                'raw' => $reply,
            ]);

            // fetch grandchildren replies recursively
            dispatch(new FetchFacebookCommentReplies(
                $this->postId,
                $comment->platform_comment_id,
                $this->depth + 1
            ))->delay(1); // safe queue load
        }
    }
}
