<?php

namespace App\Services\Adapters;

use App\Models\Platform;
use App\Models\PlatformAccount;
use App\Services\SocialSyncService;
use Illuminate\Support\Facades\Http;

class FacebookAdapter
{
    protected $token;

    protected $syncService;

    public function __construct(SocialSyncService $syncService)
    {
        $this->token = config('services.facebook.token');
        $this->syncService = $syncService;
    }

    /**
     * Sync posts for a platform account (page)
     * $account->platform_account_id must be FB page id
     */
    public function syncPosts(Platform $platform, PlatformAccount $account)
    {
        // $this->syncService->logSync($platform, $account, 'posts', 'started');
        $pageId = $account->platform_account_id;
        $url = "https://graph.facebook.com/v24.0/{$pageId}/posts";

        $res = Http::get($url, [
            'fields' => 'id,message,created_time,updated_time,from,attachments{media,type},shares',
            'access_token' => $this->token,
            'limit' => 1,
        ]);

        \Log::info('Facebook posts sync response', ['response' => $res->body()]);

        if (! $res->successful()) {
            $this->syncService->logSync($platform, $account, 'posts', 'failed', $res->body());

            return;
        }

        foreach ($res->json('data', []) as $p) {
            $payload = [
                'platform_post_id' => $p['id'],
                'caption' => $p['message'] ?? null,
                'type' => null,
                'posted_at' => $p['created_time'] ?? null,
                'raw' => $p,
                'media' => $this->extractAttachments($p['attachments']['data'] ?? []),
            ];

            $post = $this->syncService->upsertPost($platform, $account, $payload);

            // sync comments for post
            $this->syncComments($platform, $account, $post, $p['id']);
            // sync reactions (optional; you can call separately)
            $this->syncReactionsForPost($platform, $account, $post, $p['id']);
        }

        $this->syncService->logSync($platform, $account, 'posts', 'success');
    }

    protected function extractAttachments(array $attachments)
    {
        $media = [];
        foreach ($attachments as $a) {
            $type = $a['media']['type'] ?? ($a['type'] ?? null);
            $url = $a['media']['image']['src'] ?? $a['media']['source'] ?? null;
            $media[] = [
                'type' => $type,
                'url' => $url,
                'thumbnail' => $a['media']['image']['src'] ?? null,
            ];
        }

        return $media;
    }

    public function syncComments(Platform $platform, PlatformAccount $account, $post, $fbPostId)
    {
        $url = "https://graph.facebook.com/v17.0/{$fbPostId}/comments";
        $res = Http::get($url, [
            'fields' => 'id,from,message,parent,created_time',
            'access_token' => $this->token,
            'limit' => 100,
        ]);

        if (! $res->successful()) {
            $this->syncService->logSync($platform, $account, 'comments', 'failed', $res->body());

            return;
        }

        foreach ($res->json('data', []) as $c) {
            $payload = [
                'platform_comment_id' => $c['id'],
                'platform_parent_id' => $c['parent']['id'] ?? null,
                'author_platform_id' => $c['from']['id'] ?? null,
                'author_name' => $c['from']['name'] ?? null,
                'message' => $c['message'] ?? null,
                'commented_at' => $c['created_time'] ?? null,
                'raw' => $c,
            ];
            $comment = $this->syncService->upsertComment($post, $payload);

            // sync reactions for comment
            $this->syncReactionsForComment($platform, $account, $comment, $c['id']);
        }

        $this->syncService->logSync($platform, $account, 'comments', 'success');
    }

    public function syncReactionsForPost(Platform $platform, PlatformAccount $account, $post, $fbPostId)
    {
        $url = "https://graph.facebook.com/v17.0/{$fbPostId}/reactions";
        $res = Http::get($url, [
            'fields' => 'id,name,type',
            'access_token' => $this->token,
            'limit' => 500,
        ]);
        if (! $res->successful()) {
            return;
        }
        foreach ($res->json('data', []) as $r) {
            $this->syncService->upsertReaction($post, null, [
                'platform_reaction_id' => $r['id'],
                'user_platform_id' => $r['id'],
                'reaction_type' => $r['type'] ?? 'LIKE',
                'reacted_at' => now(),
                'raw' => $r,
            ]);
        }
    }

    public function syncReactionsForComment(Platform $platform, PlatformAccount $account, $comment, $fbCommentId)
    {
        $url = "https://graph.facebook.com/v17.0/{$fbCommentId}/reactions";
        $res = Http::get($url, [
            'fields' => 'id,name,type',
            'access_token' => $this->token,
            'limit' => 500,
        ]);
        if (! $res->successful()) {
            return;
        }
        foreach ($res->json('data', []) as $r) {
            $this->syncService->upsertReaction(null, $comment, [
                'platform_reaction_id' => $r['id'],
                'user_platform_id' => $r['id'],
                'reaction_type' => $r['type'] ?? 'LIKE',
                'reacted_at' => now(),
                'raw' => $r,
            ]);
        }
    }
}
