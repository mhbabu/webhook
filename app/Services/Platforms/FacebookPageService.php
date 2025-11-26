<?php

namespace App\Services\Platforms;

use App\Models\Comment;
use App\Models\Platform;
use App\Models\PlatformAccount;
use App\Models\Post;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FacebookPageService
{
    /**
     * Handle feed events (posts & edits)
     */
    public function handleFeedChange(array $value)
    {
        // Payload sample for post:
        // { "item": "post", "verb": "add", "post_id": "123", "message": "...", "created_time": 1712345678 }
        Log::info('Handling feed change: ', $value);
        if (($value['item'] ?? '') === 'post') {
            $this->storePost($value);
        } elseif (($value['item'] ?? '') === 'comment') {
            // $this->handleCommentChange($value);
            $this->storeCommentOrReply($value);
        }
    }

    /**
     * Handle comments (top-level and replies)
     */
    public function handleCommentChange(array $value)
    {
        // sample comment webhook:
        // { "item": "comment", "verb": "add", "parent_id": "", "comment_id": "...", "post_id": "..." }

        if (($value['item'] ?? '') === 'comment') {
            $this->storeCommentOrReply($value);
        }
    }

    /**
     * Insert or update post from webhook
     */
    private function storePost(array $value)
    {
        // facebook page id
        $pageId = $value['from']['id'] ?? null;

        $platform = Platform::where('name', 'facebook')->first();
        $account = PlatformAccount::where('platform_account_id', $pageId)->first();

        if (! $account) {
            return;
        }

        Post::updateOrCreate(
            [
                'platform_post_id' => $value['post_id'],
                'platform_id' => $platform->id,
                'platform_account_id' => $account->id,
            ],
            [
                'caption' => $value['message'] ?? null,
                'type' => 'post',
                'posted_at' => Carbon::createFromTimestamp($value['created_time']),
                'raw' => $value,
            ]
        );
    }

    /**
     * Insert comment or reply (with parent detection)
     */
    private function storeCommentOrReply(array $value)
    {
        $platform = Platform::where('name', 'facebook')->first();

        $post = Post::where('platform_post_id', $value['post_id'])->first();
        if (! $post) {
            Log::warning('Post not found locally. Fetching from Facebook: '.$value['post_id']);

            // 1) Fetch post from Facebook Graph API
            $post = $this->syncPostFromFacebook($value['post_id']);

            // If still missing → Abort
            if (! $post) {
                Log::error('Failed to fetch post from Facebook: '.$value['post_id']);

                return;
            }
        }

        $commentId = $value['comment_id'];
        $parentId = $value['parent_id'] ?? null;

        /** =======================
         *  Check if REPLY
         *  ======================= */
        $parentCommentId = null;

        if ($parentId && $parentId !== $commentId) {
            // Reply
            $parentComment = Comment::where('platform_comment_id', $parentId)->first();
            if ($parentComment) {
                $parentCommentId = $parentComment->id;
            }
        }

        /** ======================
         * Insert Comment or Reply
         * ======================= */
        Comment::updateOrCreate(
            [
                'platform_comment_id' => $commentId,
                'post_id' => $post->id,
            ],
            [
                'parent_id' => $parentCommentId,
                'author_id' => $value['from']['id'] ?? null,
                'author_name' => $value['from']['name'] ?? null,
                'message' => $value['message'] ?? null,
                'commented_at' => isset($value['created_time'])
                    ? Carbon::createFromTimestamp($value['created_time'])
                    : now(),
                'raw' => $value,
            ]
        );
    }

    private function syncPostFromFacebook(string $postId)
    {
        // Lookup Facebook Platform using name (unchanged seeder)
        $facebookPlatform = Platform::where('name', 'facebook')->first();

        if (! $facebookPlatform) {
            Log::error('Facebook platform not found in DB.');

            return null;
        }

        // Find the connected Facebook Page account
        $page = PlatformAccount::where('platform_id', $facebookPlatform->id)->first();

        if (! $page) {
            Log::error('Facebook Page account not found.');

            return null;
        }

        $token = $page->credentials['page_token'] ?? null;

        if (! $token) {
            Log::error('Missing Facebook Page Access Token!');

            return null;
        }

        try {
            // Fetch post from Graph API
            $response = Http::get("https://graph.facebook.com/v24.0/{$postId}", [
                'fields' => 'id,message,created_time,permalink_url,attachments{media_type,media,url},from',
                'access_token' => $token,
            ]);

            if ($response->failed()) {
                Log::error('Post fetch failed', [
                    'postId' => $postId,
                    'response' => $response->body(),
                ]);

                return null;
            }

            $fb = $response->json();

            // Insert or Update post
            return Post::updateOrCreate(
                [
                    'platform_post_id' => $fb['id'],
                    'platform_id' => $facebookPlatform->id,
                    'platform_account_id' => $page->id,
                ],
                [
                    'caption' => $fb['message'] ?? null,
                    'type' => 'post',
                    'posted_at' => isset($fb['created_time'])
                        ? Carbon::parse($fb['created_time'])
                        : now(),
                    'raw' => $fb,
                ]
            );

        } catch (\Exception $e) {
            Log::error('Graph API Error', [
                'postId' => $postId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
