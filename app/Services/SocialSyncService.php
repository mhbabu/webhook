<?php

namespace App\Services;

use App\Events\CommentSynced;
use App\Events\PostSynced;
use App\Events\ReactionSynced;
use App\Models\Comment;
use App\Models\Customer;
use App\Models\Platform;
use App\Models\PlatformAccount;
use App\Models\Post;
use App\Models\PostMedia;
use App\Models\Reaction;
use App\Models\SyncLog;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SocialSyncService
{
    /**
     * Persist or update a remote post into posts table.
     * $payload should be normalized by adapter: keys:
     * platform_post_id, caption, type, posted_at, media => [{type,url,thumbnail}]
     * php artisan social:sync facebook
     */
    public function upsertPost(Platform $platform, PlatformAccount $account, array $payload): Post
    {
        $post = Post::updateOrCreate(
            [
                'platform_id' => $platform->id,
                'platform_account_id' => $account->id,
                'platform_post_id' => (string) $payload['platform_post_id'],
            ],
            [
                'caption' => $payload['caption'] ?? null,
                'type' => $payload['type'] ?? null,
                'posted_at' => $payload['posted_at'] ?? null,
                'raw' => $payload['raw'] ?? null,
            ]
        );

        // sync media
        if (! empty($payload['media'])) {
            $post->media()->delete();
            foreach ($payload['media'] as $i => $m) {
                PostMedia::create([
                    'post_id' => $post->id,
                    'type' => $m['type'] ?? 'image',
                    'url' => $m['url'] ?? null,
                    'thumbnail' => $m['thumbnail'] ?? null,
                    'order' => $i,
                ]);
            }
        }
        /*
        $this->sendToDispatcher([
            'source' => 'facebook',
            'post_id' => $post->id,
            'platform' => $platform->name,
            'platform_account_id' => $account->platform_account_id,
            'api_key' => config('dispatcher.facebook_api_key'),
            'conversationType' => 'new',
            'timestamp' => now()->timestamp,
            'traceId' => uniqid('fb_post_', true),
        ]);
        */

        // event(new \App\Events\PostSynced($post));
        // PostSynced::dispatch($post);
        // event(new PostSynced($post));

        return $post;
    }

    /**
     * Persist comments (normalized)
     * $commentPayload keys:
     * platform_comment_id, platform_parent_id, author_platform_id, author_name, message, commented_at, raw
     */
    public function upsertComment(Post $post, array $commentPayload): Comment
    {
        $customerId = $this->mapCustomer($commentPayload['author_platform_id'] ?? null, $commentPayload['author_name'] ?? null);
        $comment = Comment::updateOrCreate(
            [
                'post_id' => $post->id,
                'platform_comment_id' => (string) $commentPayload['platform_comment_id'],
            ],
            [
                'platform_parent_id' => $commentPayload['platform_parent_id'] ?? null,
                'author_platform_id' => $commentPayload['author_platform_id'] ?? null,
                'customer_id' => $customerId,
                // 'customer_id' => $customerId,
                'author_name' => $commentPayload['author_name'] ?? 'Facebook User', // fallback name User
                'message' => $commentPayload['message'] ?? null,
                'commented_at' => $commentPayload['commented_at'] ?? null,
                'type' => $commentPayload['type'] ?? null,
                'raw' => $commentPayload['raw'] ?? null,
            ]
        );

        // event(new \App\Events\CommentSynced($comment));
        // CommentSynced::dispatch($comment);

        dispatch(new \App\Jobs\SyncCommentRepliesJob(
            $post->id,
            $comment->platform_comment_id
        ));

        return $comment;
    }

    /**
     * Persist reaction
     * payload keys: reaction_type, user_platform_id, reacted_at
     */
    public function upsertReaction(?Post $post, ?Comment $comment, array $payload): Reaction
    {
        $customerId = $this->mapCustomer($payload['user_platform_id'] ?? null, $payload['user_name'] ?? null);

        $reaction = Reaction::updateOrCreate(
            [
                'post_id' => $post?->id,
                'comment_id' => $comment?->id,
                'platform_reaction_id' => $payload['platform_reaction_id'] ?? null,
                'user_platform_id' => $payload['user_platform_id'] ?? null,
            ],
            [
                'customer_id' => $customerId,
                'reaction_type' => $payload['reaction_type'] ?? null,
                'reacted_at' => $payload['reacted_at'] ?? null,
                'raw' => $payload['raw'] ?? null,
            ]
        );

        // event(new \App\Events\ReactionSynced($reaction));
        ReactionSynced::dispatch($reaction);

        return $reaction;
    }

    /**
     * Map remote social user to Customer model (create if missing)
     */
    protected function mapCustomer(?string $platformUserId, ?string $name = null): ?int
    {
        if (! $platformUserId) {
            return null;
        }

        $platform = Platform::where('name', 'facebook')->first();

        if (! $platform) {
            throw new \Exception("Platform 'facebook' not found in database");
        }

        $platformId = $platform->id;

        $customer = Customer::firstOrCreate(
            ['platform_user_id' => (string) $platformUserId],
            [
                'name' => $name ?? 'Facebook User',
                // 'platform_id' => null, // optional: set to platform id if you store platform on customer
                'platform_id' => $platformId,
            ]
        );

        return $customer->id;
    }

    /**
     * Helper: log sync run
     */
    public function logSync(Platform $platform, PlatformAccount $account, string $syncType, string $status, ?string $message = null)
    {
        return SyncLog::create([
            'platform_id' => $platform->id,
            'platform_account_id' => $account->id,
            'sync_type' => $syncType,
            'status' => $status,
            'message' => $message,
        ]);
    }

    protected function sendToDispatcher(array $payload): void
    {
        Log::info('🔎 DISPATCHER SEND START', [
            'url' => config('dispatcher.url').config('dispatcher.endpoints.handler'),
            'payload' => $payload,
        ]);

        try {
            $url = config('dispatcher.url').config('dispatcher.endpoints.handler');
            $response = Http::acceptJson()->post($url, $payload);

            Log::info('📡 DISPATCHER RESPONSE', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            if ($response->ok()) {
                Log::info('[CUSTOMER MESSAGE FORWARDED]', $payload);
            } else {
                Log::error('[DISPATCHER FAILED]', [
                    'payload' => $payload,
                    'response' => $response->body(),
                    'status' => $response->status(),
                ]);
            }
        } catch (\Exception $e) {
            Log::error('[DISPATCHER ERROR]', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    public function dispatchPayload(array $payload): void
    {
        $this->sendToDispatcher($payload);
    }
}
