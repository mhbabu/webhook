<?php

namespace App\Jobs;

use App\Models\Platform;
use App\Models\Post;
use App\Services\Adapters\FacebookAdapter;
use App\Services\Platforms\FacebookPageService;
use App\Services\SocialSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FetchFacebookCommentReplies implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $platformId;

    public int $postId;

    public string $commentFbId;

    public int $depth;

    private int $maxDepth = 10; // optional safety net

    public function __construct(int $platformId, int $postId, string $commentFbId, int $depth = 1)
    {
        $this->platformId = $platformId;
        $this->postId = $postId;
        $this->commentFbId = $commentFbId;
        $this->depth = $depth;
    }

    public function handle(SocialSyncService $syncService, FacebookPageService $pageService, FacebookAdapter $facebookAdapter)
    {
        if ($this->depth > $this->maxDepth) {
            Log::warning("⚠ Max depth {$this->maxDepth} reached at comment: {$this->commentFbId}");

            return;
        }

        $post = Post::find($this->postId);
        if (! $post) {
            return;
        }

        $platform = Platform::find($this->platformId);
        if (! $platform) {
            return;
        }

        // 🔥 Let adapter handle token + API + pagination
        $facebookAdapter->fetchReplies(
            $platform,
            $post,
            $this->commentFbId,
            $this->depth
        );

        $token = config('services.facebook.token');
        $url = "https://graph.facebook.com/v24.0/{$this->commentFbId}/comments";

        $after = null;

        do {
            $res = Http::get($url, [
                'fields' => 'id,from,message,parent,created_time',
                'limit' => 100,
                'after' => $after,
                'access_token' => $token,
            ]);

            if (! $res->successful()) {
                Log::error("❌ Facebook reply fetch failed for {$this->commentFbId}");

                return;
            }

            foreach ($res->json('data', []) as $c) {

                /** Normalize 'from' data */
                $from = $pageService->normalizeFromData($c['from'] ?? null);

                /** Detect type (post-comment / reply-comment / indirect reply etc.) */
                $type = $pageService->detectCommentType([
                    'post_id' => $post->platform_post_id,
                    'comment_id' => $c['id'],
                    'parent_id' => $c['parent']['id'] ?? null,
                ]);

                Log::info("💬 Reply synced: {$c['id']} | depth={$this->depth} | type={$type}");

                $comment = $syncService->upsertComment($post, [
                    'platform_comment_id' => $c['id'],
                    'platform_parent_id' => $c['parent']['id'] ?? null,
                    'author_platform_id' => $from['id'],
                    'author_name' => $from['name'],
                    'message' => $c['message'] ?? null,
                    'commented_at' => $c['created_time'] ?? null,
                    'type' => $type,
                    'raw' => $c,
                ]);

                /** 🔥 Recursively queue next-level replies */
                dispatch(new FetchFacebookCommentReplies(
                    $this->postId,
                    $comment->platform_comment_id,
                    $this->depth + 1
                ))->delay(1); // slow down rate limit hits
            }

            $after = $res['paging']['cursors']['after'] ?? null;

        } while ($after);
    }
}
