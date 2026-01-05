<?php

namespace App\Services\Platforms;

use App\Models\Comment;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Platform;
use App\Models\PlatformAccount;
use App\Models\Post;
use App\Models\PostMedia;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

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
         * STEP 2 — Unified Media List (raw remote URLs)
         */
        $mediaList = [];

        if (! empty($value['attachments'])) {
            foreach ($value['attachments'] as $item) {
                // SINGLE IMAGE
                if (! empty($item['media']['image']['src'])) {
                    $mediaList[] = [
                        'type' => $item['type'] ?? 'photo',
                        'remote_url' => $item['media']['image']['src'],
                    ];
                }

                // MULTIPLE (subattachments)
                if (! empty($item['subattachments']['data'])) {
                    foreach ($item['subattachments']['data'] as $sub) {
                        if (! empty($sub['media']['image']['src'])) {
                            $mediaList[] = [
                                'type' => $sub['type'] ?? 'photo',
                                'remote_url' => $sub['media']['image']['src'],
                            ];
                        }
                    }
                }
            }
        }

        /**
         * STEP 3 — Clear Old Media Records
         */
        $post->media()->delete();

        /**
         * STEP 4 — Download each media file locally & store DB record
         */
        foreach ($mediaList as $idx => $m) {

            try {
                $remoteUrl = $m['remote_url'];
                Log::info('Downloading media', ['url' => $remoteUrl]);
                // Generate filename based on post + index + extension
                $extension = pathinfo(parse_url($remoteUrl, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'jpg';
                $filename = "facebook_media/post_{$post->id}_{$idx}_".time().".$extension";
                // $filename = 'facebook_media/'.uniqid('fb_post_', true).'.'.$extension;
                // Download file (HTTP::timeout prevents long hangs)
                $fileData = Http::timeout(20)->get($remoteUrl);

                if ($fileData->successful()) {
                    Storage::disk('public')->put($filename, $fileData->body());
                    $localUrl = Storage::url($filename);
                    Log::info('Media downloaded successfully', ['local_url' => $localUrl]);
                } else {
                    // If failed, fallback to remote
                    Log::warning('Media download failed, using remote URL', [
                        'url' => $remoteUrl,
                        'status' => $fileData->status(),
                    ]);
                    $localUrl = $remoteUrl;
                }

            } catch (\Throwable $e) {
                Log::error('Media download failed', [
                    'url' => $m['remote_url'],
                    'error' => $e->getMessage(),
                ]);

                $localUrl = $m['remote_url'];
            }

            // Insert into DB
            PostMedia::create([
                'post_id' => $post->id,
                'type' => $m['type'],
                'url' => $localUrl,       // ALWAYS using local or remote fallback
                'thumbnail' => null,
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
     * Main handler for comment or reply
     */
    private function storeCommentOrReply(array $value)
    {
        $platform = Platform::whereRaw('LOWER(name) = ?', ['facebook'])->first();
        $platformId = $platform->id;
        $platformName = strtolower($platform->name);
        if (! $platform) {
            return;
        }

        $post = Post::where('platform_post_id', $value['post_id'])->first();
        if (! $post) {
            $post = $this->syncPostFromFacebook($value['post_id']);
            if (! $post) {
                return;
            }
        }

        $commentId = $value['comment_id'];
        $parentId = $value['parent_id'] ?? null;

        // Fetch full comment if 'from' is missing
        if (! isset($value['from']['id'])) {
            $value = $this->fetchFullComment($commentId, $post);
            if (! $value) {
                return;
            }
        }

        // Ensure all parent chain exists
        if ($parentId) {
            $this->ensureParentChain($parentId, $post);
        }

        $customerId = $this->getOrCreatePlatformCustomer(
            $platform->id,
            $value['from'] ?? null
        );
        if (! $customerId) {
            return;
        }

        // Conversation
        $conversation = Conversation::firstOrCreate(
            ['customer_id' => $customerId, 'platform' => $platformName],
            ['trace_id' => 'fb-'.now()->format('YmdHis').'-'.uniqid()]
        );
        // Insert or update comment
        $comment = Comment::updateOrCreate(
            [
                'conversation_id' => $conversation->id,
                'platform_comment_id' => $commentId,
                'post_id' => $post->id,
            ],
            [
                'platform_parent_id' => $parentId,
                'author_platform_id' => $value['from']['id'],
                'customer_id' => $customerId,
                'type' => $this->detectCommentType($value),
                'message' => $value['message'] ?? null,
                'commented_at' => Carbon::parse($value['created_time']),
                'raw' => $value,
            ]
        );

        $payload = [
            'source' => 'facebook',
            'traceId' => $conversation->trace_id,
            'conversationId' => $conversation->id,
            'conversationType' => 'new',
            'sender' => $comment->author_platform_id,
            // 'sender' => $customerId,
            'api_key' => config('dispatcher.facebook_api_key'),
            'timestamp' => now()->timestamp,
            'message' => $comment->message,
            'attachmentId' => null,
            'attachments' => [],
            // 'subject' => 'Facebook-'.$post->id,
            'messageId' => $comment->id,
            'parentMessageId' => optional($comment->parent)->id ?? $post->id,
        ];

        Log::info('Prepared payload for dispatcher', $payload);
        // Generate correct path now that parent chain exists
        $comment->update([
            'path' => $this->generateCommentPath($post, $comment),
        ]);

        // Log::info("Comment stored with path: {$comment->path}");

        // Dispatch recursion job
        dispatch(new \App\Jobs\SyncCommentRepliesJob($post->id, $comment->platform_comment_id));

        // $service->dispatchPayload($payload);
        $this->sendToDispatcher($payload);

        return $comment;
    }

    private function sendToDispatcher(array $payload): void
    {
        try {
            $response = Http::acceptJson()->post(config('dispatcher.url').config('dispatcher.endpoints.handler'), $payload);

            if ($response->ok()) {
                Log::info('[CUSTOMER MESSAGE FORWARDED]', $payload);
            } else {
                Log::error('[CUSTOMER MESSAGE FORWARDED] FAILED', ['payload' => $payload, 'response' => $response->body()]);
            }
        } catch (\Exception $e) {
            Log::error('[CUSTOMER MESSAGE FORWARDED] ERROR', ['exception' => $e->getMessage()]);
        }
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
            'fields' => 'id,parent{id},from,message,message_tags,created_time,like_count,reactions{type,name,id}',
            // 'fields' => 'fields=id,message,from,created_time,attachment,like_count,reactions{type,name,id},parent{id}',
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
                // 'fields' => 'id,message,created_time,permalink_url,attachments{type,media,media_type,url,subattachments},from',
                'fields' => 'id,message,created_time,permalink_url,from',
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

                    // ================================
                    // SINGLE PHOTO OR VIDEO
                    // ================================
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

                    // ================================
                    // MULTIPLE SUBATTACHMENTS
                    // ================================
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
        $fb['from'] = $this->normalizeFromData($fb['from'] ?? null);
        $customerId = $this->getOrCreatePlatformCustomer($platform->id, $fb['from'] ?? null);

        $comment = Comment::updateOrCreate(
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

        // Generate path
        $comment->update([
            'path' => $this->generateCommentPath($post, $comment),
        ]);

        return $comment;
    }

    private function getOrCreatePlatformCustomer(int $platformId, ?array $from): ?int
    {
        /* This condition will uncomment in live environment/live facebook page webhook testing
        if (! $from || ! isset($from['id'])) {
            return null;
        }
      */
        $platformUserId = $from['id'] ?? $this->generateRandomPlatformUserId();  // fallback to random ID test purpose only
        Log::info("Mapping platform user ID: $platformUserId");
        $customer = Customer::updateOrCreate(
            [
                'platform_user_id' => $platformUserId,
                'platform_id' => $platformId,
            ],
            [
                'name' => $from['name'] ?? 'Facebook User',
            ]
        );

        return $customer->id;
    }

    private function downloadAndStoreMedia(string $url): ?string
    {
        try {
            $response = Http::get($url);

            if (! $response->successful()) {
                return null;
            }

            // Generate unique filename based on hash & timestamp
            $extension = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'jpg';
            $filename = 'facebook_media/'.uniqid('fb_post_', true).'.'.$extension;

            Storage::disk('public')->put($filename, $response->body());

            // Return local accessible path
            return Storage::url($filename);

        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Detect comment vs reply
     */
    public function detectCommentType(array $value): string
    {
        $postId = explode('_', $value['post_id'])[1] ?? null;
        $parentId = $value['parent_id'] ?? null;
        $commentId = $value['comment_id'];

        if (! $parentId || $parentId === $postId || $parentId === $commentId) {
            return 'post_comment';
        }

        return 'comment_reply';
    }

    // Test purpose normalization of "from" data
    public function normalizeFromData(?array $from): array
    {
        return [
            'id' => $from['id'] ?? $this->generateRandomPlatformUserId(),
            'name' => $from['name'] ?? 'Facebook User',
        ];
    }

    // Test purpose only - generate random platform user ID
    private function generateRandomPlatformUserId($length = 17)
    {
        $min = 10 ** ($length - 1);  // e.g. 10000000000000000
        $max = (10 ** $length) - 1;  // e.g. 99999999999999999

        return (string) random_int($min, $max);
    }

    private function ensureParentChain(string $parentId, Post $post)
    {
        $parent = Comment::where('platform_comment_id', $parentId)->first();

        if ($parent) {
            return $parent;
        }

        // Fetch full parent from Facebook
        $fb = $this->fetchFullComment($parentId, $post);
        if (! $fb) {
            return null;
        }

        // Ensure grandparent exists
        $grandParentId = $fb['parent']['id'] ?? null;
        if ($grandParentId) {
            $this->ensureParentChain($grandParentId, $post);
        }

        $platform = Platform::where('name', 'facebook')->first();
        $fb['from'] = $this->normalizeFromData($fb['from'] ?? null);
        $customerId = $this->getOrCreatePlatformCustomer($platform->id, $fb['from']);

        $comment = Comment::updateOrCreate(
            [
                'platform_comment_id' => $fb['id'],
                'post_id' => $post->id,
            ],
            [
                'platform_parent_id' => $grandParentId,
                'author_platform_id' => $fb['from']['id'],
                'customer_id' => $customerId,
                'type' => isset($grandParentId) ? 'comment_reply' : 'post_comment',
                'message' => $fb['message'] ?? null,
                'commented_at' => Carbon::parse($fb['created_time']),
                'raw' => $fb,
            ]
        );

        // Generate path after grandparent is created
        $comment->update(['path' => $this->generateCommentPath($post, $comment)]);

        return $comment;
    }

    /**
     * Generate tree path for comment
     */
    private function generateCommentPath(Post $post, Comment $comment): string
    {
        // Root comment → no parent
        if (! $comment->platform_parent_id) {
            $count = Comment::where('post_id', $post->id)
                ->whereNull('platform_parent_id')
                ->count();

            return (string) $count; // "0", "1", "2", ...
        }

        // Reply comment → ensure parent exists
        $parent = Comment::where('platform_comment_id', $comment->platform_parent_id)->first();
        if (! $parent || ! $parent->path) {
            return '0';
        }

        // Count existing siblings
        $replyOrder = Comment::where('post_id', $post->id)
            ->where('platform_parent_id', $comment->platform_parent_id)
            ->count();

        return "{$parent->path}.{$replyOrder}";
    }
}
