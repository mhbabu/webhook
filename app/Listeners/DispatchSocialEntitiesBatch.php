<?php

namespace App\Listeners;

use App\Events\CommentSynced;
use App\Events\PostSynced;
use App\Events\ReactionSynced;
use App\Jobs\SendSocialEntitiesToDispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class DispatchSocialEntitiesBatch implements ShouldQueue
{
    public array $posts = [];

    public array $comments = [];

    public array $reactions = [];

    public function handle($event)
    {
        // Collect the entity
        if ($event instanceof PostSynced) {
            $event->post->api_key = config('dispatcher.facebook_api_key');
            $this->posts[] = $event->post;
            log::info('DispatchSocialEntitiesBatch collected PostSynced', $this->posts);
        } elseif ($event instanceof CommentSynced) {
            $this->comments[] = $event->comment;
        } elseif ($event instanceof ReactionSynced) {
            $this->reactions[] = $event->reaction;
        }

        // Dispatch single batch job immediately for simplicity
        SendSocialEntitiesToDispatcher::dispatch(
            $this->posts,
            $this->comments,
            $this->reactions
        );
    }
}
