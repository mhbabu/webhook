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
            'limit' => 1,
            'access_token' => $this->token,
        ]);

        if (! $res->successful()) {
            return $this->syncService->logSync($platform, $account, 'posts', 'failed', $res->body());
        }

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

        return $this->syncService->logSync($platform, $account, 'posts', 'success');
    }

    /** Extract media */
    protected function extractAttachments(array $attachments): array
    {
        return collect($attachments)->map(fn ($a) => [
            'type' => $a['type'] ?? 'image',
            'url' => $a['media']['image']['src'] ?? $a['media']['source'] ?? null,
            'thumbnail' => $a['media']['image']['src'] ?? null,
        ])->values()->toArray();
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

            $type = $this->pageService->detectCommentType([
                'post_id' => $post->platform_post_id,     // 🔥 correct one
                'comment_id' => $c['id'],
                'parent_id' => $c['parent']['id'] ?? null,
            ]);

            Log::info("Detected comment type: $type for comment ID: {$c['id']}");

            $comment = $this->syncService->upsertComment($post, [
                'platform_comment_id' => $c['id'],
                'platform_parent_id' => $c['parent']['id'] ?? null,
                'author_platform_id' => $c['from']['id'] ?? null,
                'author_name' => $c['from']['name'] ?? null,
                'message' => $c['message'] ?? null,
                'commented_at' => $c['created_time'] ?? null,
                'type' => $type,
                'raw' => $c,
            ]);

            dispatch(new FetchFacebookCommentReplies($post->id, $comment->platform_comment_id)); // 🔥 Recursion trigger
            $this->syncReactionsForComment($comment, $c['id']);
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
}
