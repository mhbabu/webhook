<?php

namespace App\Services\Adapters;

use App\Jobs\FetchFacebookCommentReplies;
use App\Models\Platform;
use App\Models\PlatformAccount;
use App\Services\Platforms\FacebookPageService;
use App\Services\SocialSyncService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FacebookAdapter
{
    protected string $token;

    protected SocialSyncService $syncService;

    public function __construct(SocialSyncService $syncService, FacebookPageService $pageService)
    {
        $this->token = config('services.facebook.token');
        $this->syncService = $syncService;
        $this->pageService = $pageService;
    }

    /** Main entry : Sync Posts of Page */
    public function syncPosts(Platform $platform, PlatformAccount $account)
    {
        $url = "https://graph.facebook.com/v24.0/{$account->platform_account_id}/posts";

        $res = Http::get($url, [
            'fields' => 'id,message,created_time,from,attachments{media,type},shares',
            'limit' => 2,
            'access_token' => $this->token,
        ]);

        if (! $res->successful()) {
            return $this->syncService->logSync($platform, $account, 'posts', 'failed', $res->body());
        }
        // $next = $res->json('paging.next') ?? null;

        // while ($next) {
        //     $res = Http::get($next);
        foreach ($res->json('data', []) as $p) {

            $post = $this->syncService->upsertPost($platform, $account, [
                'platform_post_id' => $p['id'],
                'caption' => $p['message'] ?? null,
                'posted_at' => $p['created_time'] ?? null,
                'type' => null,
                'raw' => $p,
                'media' => $this->extractAttachments($p['attachments']['data'] ?? []),
            ]);

            // ⬇ queue based heavy sync
            $this->fetchRootComments($post, $p['id']);
            $this->syncReactionsForPost($post, $p['id']);
        }
        //     $next = $res->json('paging.next') ?? null;
        // }

        return $this->syncService->logSync($platform, $account, 'posts', 'success');
    }

    /** Extract & download media locally */
    protected function extractAttachments(array $attachments): array
    {
        $mediaList = [];

        if (empty($attachments)) {
            return $mediaList;
        }

        foreach ($attachments as $mediaItem) {

            // =====================================
            // 1. Single Photo / Video
            // =====================================
            if (! empty($mediaItem['media']['image']['src'])) {

                $localUrl = $this->downloadAndStoreMedia(
                    $mediaItem['media']['image']['src']
                );

                if ($localUrl) {
                    $mediaList[] = [
                        'type' => $mediaItem['type'] ?? 'photo',
                        'url' => $localUrl,
                        'thumbnail' => null,
                    ];
                }
            }

            // =====================================
            // 2. Album / Carousel Items
            // =====================================
            if (! empty($mediaItem['subattachments']['data'])) {

                foreach ($mediaItem['subattachments']['data'] as $sub) {

                    if (! empty($sub['media']['image']['src'])) {

                        $localUrl = $this->downloadAndStoreMedia(
                            $sub['media']['image']['src']
                        );

                        if ($localUrl) {
                            $mediaList[] = [
                                'type' => $sub['type'] ?? 'photo',
                                'url' => $localUrl,
                                'thumbnail' => null,
                            ];
                        }
                    }
                }
            }
        }

        return $mediaList;
    }

    protected function downloadAndStoreMedia(string $remoteUrl): ?string
    {
        try {
            $fileName = 'fb_'.uniqid().'.jpg';
            $filePath = storage_path('app/public/facebook/'.$fileName);

            $file = file_get_contents($remoteUrl);
            file_put_contents($filePath, $file);

            return 'storage/facebook/'.$fileName;   // final accessible URL
        } catch (\Exception $e) {
            \Log::error("Media download failed: {$remoteUrl}", ['exception' => $e->getMessage()]);

            return null;
        }
    }

    /** Fetch level-1 comments and trigger recursion */
    public function fetchRootComments($post, string $fbPostId)
    {
        $res = Http::get("https://graph.facebook.com/v24.0/{$fbPostId}/comments", [
            'fields' => 'id,from,message,parent,created_time',
            'limit' => 100,
            'access_token' => $this->token,
        ]);

        if (! $res->successful()) {
            return;
        }

        foreach ($res->json('data', []) as $c) {
            $from = $this->pageService->normalizeFromData($c['from'] ?? null);
            $type = $this->pageService->detectCommentType([
                'post_id' => $post->platform_post_id,     // 🔥 correct one
                'comment_id' => $c['id'],
                'parent_id' => $c['parent']['id'] ?? null,
            ]);

            // Log::info("Detected comment type: $type for comment ID: {$c['id']}");

            $comment = $this->syncService->upsertComment($post, [
                'platform_comment_id' => $c['id'],
                'platform_parent_id' => $c['parent']['id'] ?? null,
                // 'author_platform_id' => $c['from']['id'] ?? null,
                // 'author_name' => $c['from']['name'] ?? null,
                'author_platform_id' => $from['id'],       // normalized
                'author_name' => $from['name'],     // normalized
                'message' => $c['message'] ?? null,
                'commented_at' => $c['created_time'] ?? null,
                'type' => $type,
                'raw' => $c,
            ]);

            dispatch(new FetchFacebookCommentReplies($post->id, $comment->platform_comment_id)); // 🔥 Recursion trigger
            $this->syncReactionsForComment($comment, $c['id']);
        }
    }

    /**
     * Recursively fetch replies for a comment (Level-2,3,...∞)
     */
    public function fetchReplies($post, string $parentCommentId, ?string $parentPlatformParentId = null)
    {
        $res = Http::get("https://graph.facebook.com/v24.0/{$parentCommentId}/comments", [
            'fields' => 'id,from,message,parent,created_time',
            'limit' => 100,
            'access_token' => $this->token,
        ]);

        if (! $res->successful()) {
            return;
        }

        foreach ($res->json('data', []) as $reply) {

            $comment = $this->syncService->upsertComment($post, [
                'platform_comment_id' => $reply['id'],
                'platform_parent_id' => $reply['parent']['id'] ?? $parentCommentId, // fallback
                'author_platform_id' => $reply['from']['id'] ?? null,
                'author_name' => $reply['from']['name'] ?? null,
                'message' => $reply['message'] ?? null,
                'commented_at' => $reply['created_time'] ?? now(),
                'raw' => $reply,
            ]);

            // Recursion trigger for deeper replies
            dispatch(new FetchFacebookCommentReplies($post->id, $comment->platform_comment_id));

            $this->syncReactionsForComment($comment, $reply['id']);
        }
    }

    /** Post reactions */
    public function syncReactionsForPost($post, string $id)
    {
        $res = Http::get("https://graph.facebook.com/v24.0/{$id}/reactions", [
            'fields' => 'id,name,type', 'limit' => 200, 'access_token' => $this->token,
        ]);

        foreach ($res->json('data', []) as $r) {
            $this->syncService->upsertReaction($post, null, [
                'platform_reaction_id' => $r['id'],
                'user_platform_id' => $r['id'],
                'reaction_type' => $r['type'] ?? 'LIKE',
                'reacted_at' => now(), 'raw' => $r,
            ]);
        }
    }

    /** Comment reactions */
    public function syncReactionsForComment($comment, string $id)
    {
        $res = Http::get("https://graph.facebook.com/v24.0/{$id}/reactions", [
            'fields' => 'id,name,type', 'limit' => 200, 'access_token' => $this->token,
        ]);

        foreach ($res->json('data', []) as $r) {
            $this->syncService->upsertReaction(null, $comment, [
                'platform_reaction_id' => $r['id'],
                'user_platform_id' => $r['id'],
                'reaction_type' => $r['type'] ?? 'LIKE',
                'reacted_at' => now(), 'raw' => $r,
            ]);
        }
    }

    private function generatePath(Comment $comment): string
    {
        if (! $comment->platform_parent_id) {
            $count = Comment::where('post_id', $comment->post_id)
                ->whereNull('platform_parent_id')->count();

            return (string) $count; // Example: "1"
        }

        $parent = Comment::where('platform_comment_id', $comment->platform_parent_id)->first();

        $count = Comment::where('platform_parent_id', $comment->platform_parent_id)->count();

        return "{$parent->path}.{$count}";  // Example: "1.3.2"
    }
}
