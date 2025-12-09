<?php

namespace App\Services\Platforms;

use App\Models\Comment;
use App\Models\Customer;
use App\Models\Platform;
use App\Models\PlatformAccount;
use App\Models\Post;
use App\Models\PostMedia;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FacebookPageService
{
    /**
     * Handle feed events (posts & comments)
     */
    public function handleFeedChange(array $value)
    {
        Log::info('Webhook received', $value);

        if (($value['item'] ?? '') === 'post') {
            return $this->storePost($value);
        }

        if (($value['item'] ?? '') === 'comment') {
            return $this->storeCommentOrReply($value);
        }
    }

    /**
     * Insert or update post from webhook
     */
 private function storePost(array $value)
{
    $pageId = $value['from']['id'] ?? null;

    $platform = Platform::where('name', 'facebook')->first();
    $account = PlatformAccount::where('platform_account_id', $pageId)->first();

    if (! $account) {
        Log::warning("Page not found in DB: $pageId");
        return;
    }

    // Ensure author
    $authorId = $this->getOrCreatePlatformCustomer($platform->id, $value['from'] ?? null);

    /**
     * STEP 1 — Save/Update Post
     */
    $post = Post::updateOrCreate(
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

    /**
     * STEP 2 — Parse Attachments → Unified Media List
     */
    $mediaList = [];

    if (! empty($value['attachments'])) {
        foreach ($value['attachments'] as $item) {

            // SINGLE IMAGE
            if (! empty($item['media']['image']['src'])) {
                $mediaList[] = [
                    'type' => $item['type'] ?? 'photo',
                    'url' => $item['media']['image']['src'],
                    'thumbnail' => null,
                ];
            }

            // SUBATTACHMENTS (multi photo)
            if (! empty($item['subattachments']['data'])) {
                foreach ($item['subattachments']['data'] as $sub) {
                    if (! empty($sub['media']['image']['src'])) {
                        $mediaList[] = [
                            'type' => $sub['type'] ?? 'photo',
                            'url' => $sub['media']['image']['src'],
                            'thumbnail' => null,
                        ];
                    }
                }
            }
        }
    }

    /**
     * STEP 3 — Sync Post Media
     */
    $post->media()->delete(); // remove old media

    foreach ($mediaList as $idx => $m) {
        PostMedia::create([
            'post_id' => $post->id,
            'type' => $m['type'],
            'url' => $m['url'],
            'thumbnail' => $m['thumbnail'],
            'order' => $idx,
        ]);
    }

    Log::info('Webhook post stored with media', [
        'post_id' => $post->id,
        'media_count' => count($mediaList),
        'platform_post_id' => $value['post_id'],
    ]);

    return $post;
}


    /**
     * Main handler for comment or reply (full fix)
     */
    private function storeCommentOrReply(array $value)
    {
        $platform = Platform::where('name', 'facebook')->first();

        $post = Post::where('platform_post_id', $value['post_id'])->first();

        if (! $post) {
            $post = $this->syncPostFromFacebook($value['post_id']);
            if (! $post) {
                return;
            }
        }

        $commentId = $value['comment_id'];
        $parentId = $value['parent_id'] ?? null;

        Log::info("Processing comment: $commentId (parent=$parentId)");

        /**
         * 🟡 FIX:
         * If webhook payload has NO "from" → fetch full comment from FB Graph.
         */
        if (! isset($value['from']['id'])) {
            Log::warning("Webhook missing 'from' → fetching full comment: $commentId");
            $value = $this->fetchFullComment($commentId, $post);
            if (! $value) {
                return;
            }
        }

        /**
         * Detect type: post_comment or comment_reply
         */
        $commentType = $this->detectCommentType($value);

        /**
         * Ensure parent exists before inserting reply
         */
        if ($commentType === 'comment_reply') {
            $parent = Comment::where('platform_comment_id', $parentId)->first();
            if (! $parent) {
                Log::info("Parent not found → syncing chain for: $parentId");
                $this->syncCommentChainFromFacebook($parentId, $post);
            }
        }

        /**
         * Create / update author
         */
        $customerId = $this->getOrCreatePlatformCustomer(
            $platform->id,
            $value['from'] ?? null
        );

        if (! $customerId) {
            Log::error("Author missing after sync → cannot insert comment: $commentId");

            return;
        }

        /**
         * Insert final saved data
         */
        return Comment::updateOrCreate(
            [
                'platform_comment_id' => $commentId,
                'post_id' => $post->id,
            ],
            [
                'platform_parent_id' => $parentId,
                'author_platform_id' => $value['from']['id'],
                'customer_id' => $customerId,
                'type' => $commentType,
                'message' => $value['message'] ?? null,
                'commented_at' => Carbon::createFromTimestamp($value['created_time']),
                'raw' => $value,
            ]
        );
    }

    /**
     * Fetch full comment from Facebook Graph API
     */
    private function fetchFullComment(string $commentId, Post $post): ?array
    {
        $page = PlatformAccount::find($post->platform_account_id);
        if (! $page) {
            return null;
        }

        $token = $page->credentials['page_token'] ?? null;

        $response = Http::get("https://graph.facebook.com/v24.0/{$commentId}", [
            'fields' => 'id,parent{id},from,message,message_tags,created_time,attachment,like_count,reactions',
            'access_token' => $token,
        ]);

        if ($response->failed()) {
            Log::error('Failed to fetch full comment', ['commentId' => $commentId, 'body' => $response->body()]);

            return null;
        }

        return $response->json();
    }

    /**
     * Fetch a post from Facebook and insert/update it locally.
     *
     * @return Post|null
     */
    private function syncPostFromFacebook(string $postId)
    {
        // Find platform
        $facebookPlatform = Platform::where('name', 'facebook')->first();
        if (! $facebookPlatform) {
            Log::error('Facebook platform not found in DB.');

            return null;
        }

        // Find page account
        $page = PlatformAccount::where('platform_id', $facebookPlatform->id)->first();
        if (! $page) {
            Log::error('Facebook Page account not found for platform id: '.$facebookPlatform->id);

            return null;
        }

        $token = $page->credentials['page_token'] ?? null;
        if (! $token) {
            Log::error('Missing Facebook Page Access Token for PlatformAccount id: '.$page->id);

            return null;
        }

        try {
            // Fetch Post Details
            $response = Http::get("https://graph.facebook.com/v24.0/{$postId}", [
                'fields' => 'id,message,created_time,permalink_url,attachments{type,media,media_type,url,subattachments},from',
                'access_token' => $token,
            ]);

            if ($response->failed()) {
                Log::error('Post fetch failed', [
                    'postId' => $postId,
                    'status' => $response->status(),
                    'response' => $response->body(),
                ]);

                return null;
            }

            $fb = $response->json();

            Log::info('Fetched post from Facebook', ['postId' => $postId, 'data' => $fb]);
            /**
             *  Convert Facebook attachments → unified media array
             */
            $mediaList = [];

            if (! empty($fb['attachments']['data'])) {
                foreach ($fb['attachments']['data'] as $mediaItem) {

                    // SINGLE PHOTO OR VIDEO
                    if (! empty($mediaItem['media']['image']['src'])) {
                        $mediaList[] = [
                            'type' => $mediaItem['type'] ?? 'photo',
                            'url' => $mediaItem['media']['image']['src'],
                            'thumbnail' => null,
                        ];
                    }

                    // SUBATTACHMENTS (Multiple photos)
                    if (! empty($mediaItem['subattachments']['data'])) {
                        foreach ($mediaItem['subattachments']['data'] as $sub) {
                            if (! empty($sub['media']['image']['src'])) {
                                $mediaList[] = [
                                    'type' => $sub['type'] ?? 'photo',
                                    'url' => $sub['media']['image']['src'],
                                    'thumbnail' => null,
                                ];
                            }
                        }
                    }
                }
            }

            /**
             *  Ensure customer exists
             */
            $authorId = $this->getOrCreatePlatformCustomer(
                $facebookPlatform->id,
                $fb['from'] ?? null
            );

            /**
             *  Save or update Post
             */
            $post = Post::updateOrCreate(
                [
                    'platform_post_id' => $fb['id'],
                    'platform_id' => $facebookPlatform->id,
                    'platform_account_id' => $page->id,
                ],
                [
                    'caption' => $fb['message'] ?? null,
                    'type' => 'post',
                    'posted_at' => ! empty($fb['created_time'])
                        ? Carbon::parse($fb['created_time'])
                        : now(),
                    'raw' => $fb,
                ]
            );

            /**
             *  Sync Media
             */
            $post->media()->delete(); // clear old media

            foreach ($mediaList as $i => $m) {
                PostMedia::create([
                    'post_id' => $post->id,
                    'type' => $m['type'],
                    'url' => $m['url'],
                    'thumbnail' => $m['thumbnail'],
                    'order' => $i,
                ]);
            }

            Log::info('Synced post with media from Facebook', [
                'platform_post_id' => $postId,
                'media_count' => count($mediaList),
                'local_id' => $post->id,
            ]);

            return $post;

        } catch (\Exception $e) {
            Log::error('Graph API Error while fetching post', [
                'postId' => $postId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Recursive parent fetch
     */
    private function syncCommentChainFromFacebook(string $commentId, Post $post)
    {
        $fb = $this->fetchFullComment($commentId, $post);
        if (! $fb) {
            return;
        }

        $comment = $this->insertOrUpdateComment($post, $fb);

        if (isset($fb['parent']['id'])) {
            $parentId = $fb['parent']['id'];

            if (! Comment::where('platform_comment_id', $parentId)->exists()) {
                $this->syncCommentChainFromFacebook($parentId, $post);
            }
        }

        return $comment;
    }

    private function insertOrUpdateComment(Post $post, array $fb)
    {
        $platform = Platform::where('name', 'facebook')->first();

        $customerId = $this->getOrCreatePlatformCustomer($platform->id, $fb['from'] ?? null);

        return Comment::updateOrCreate(
            [
                'platform_comment_id' => $fb['id'],
                'post_id' => $post->id,
            ],
            [
                'platform_parent_id' => $fb['parent']['id'] ?? null,
                'author_platform_id' => $fb['from']['id'] ?? null,
                'customer_id' => $customerId,
                'type' => isset($fb['parent']['id']) ? 'comment_reply' : 'post_comment',
                'message' => $fb['message'] ?? null,
                'commented_at' => Carbon::parse($fb['created_time']),
                'raw' => $fb,
            ]
        );
    }

    private function getOrCreatePlatformCustomer(int $platformId, ?array $from): ?int
    {
        if (! $from || ! isset($from['id'])) {
            return null;
        }

        $customer = Customer::updateOrCreate(
            [
                'platform_user_id' => $from['id'],
                'platform_id' => $platformId,
            ],
            [
                'name' => $from['name'] ?? 'Unknown',
            ]
        );

        return $customer->id;
    }

    /**
     * Detect comment vs reply
     */
    private function detectCommentType(array $value): string
    {
        $postId = explode('_', $value['post_id'])[1] ?? null;
        $parentId = $value['parent_id'] ?? null;
        $commentId = $value['comment_id'];

        if (! $parentId || $parentId === $postId || $parentId === $commentId) {
            return 'post_comment';
        }

        return 'comment_reply';
    }
}
