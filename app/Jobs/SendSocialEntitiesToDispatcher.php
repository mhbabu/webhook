<?php

namespace App\Jobs;

use App\Services\SocialSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendSocialEntitiesToDispatcher implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public array $posts;

    public array $comments;

    public array $reactions;

    /**
     * Create a new job instance.
     *
     * Accept arrays of models
     */
    public function __construct(array $posts = [], array $comments = [], array $reactions = [])
    {
        $this->posts = $posts;
        $this->comments = $comments;
        $this->reactions = $reactions;
    }

    /**
     * Execute the job.
     */
    public function handle(SocialSyncService $service): void
    {
        // Posts
        foreach ($this->posts as $post) {
            Log::info('🚀 Dispatching Post to Dispatcher', ['post_id' => $post->id]);

            $payload = [
                'type' => 'post',
                'post_id' => $post->id,
                'platform' => $post->platform?->name,
                'platform_post_id' => $post->platform_post_id,
                'caption' => $post->caption,
                'media' => $post->media()->get()->toArray(),
                'posted_at' => $post->posted_at,
            ];

            $service->dispatchPayload($payload);
        }

        // Comments
        foreach ($this->comments as $comment) {
            Log::info('🚀 Dispatching Comment to Dispatcher', ['comment_id' => $comment->id]);

            $payload = [
                'type' => 'comment',
                'comment_id' => $comment->id,
                'platform_comment_id' => $comment->platform_comment_id,
                'post_id' => $comment->post_id,
                'customer_id' => $comment->customer_id,
                'author_name' => $comment->author_name,
                'message' => $comment->message,
                'commented_at' => $comment->commented_at,
            ];

            $service->dispatchPayload($payload);
        }

        // Reactions
        foreach ($this->reactions as $reaction) {
            Log::info('🚀 Dispatching Reaction to Dispatcher', ['reaction_id' => $reaction->id]);

            $payload = [
                'type' => 'reaction',
                'reaction_id' => $reaction->id,
                'post_id' => $reaction->post_id,
                'comment_id' => $reaction->comment_id,
                'reaction_type' => $reaction->reaction_type,
                'customer_id' => $reaction->customer_id,
                'reacted_at' => $reaction->reacted_at,
            ];

            $service->dispatchPayload($payload);
        }
    }

    public int $tries = 3;

    public int $backoff = 10;
}
