<?php

namespace App\Providers;

use App\Events\CommentSynced;
use App\Events\PostSynced;
use App\Events\ReactionSynced;
use App\Listeners\DispatchSocialEntitiesBatch;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        PostSynced::class => [DispatchSocialEntitiesBatch::class],
        CommentSynced::class => [DispatchSocialEntitiesBatch::class],
        ReactionSynced::class => [DispatchSocialEntitiesBatch::class],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        //
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false; // set true if you want Laravel to auto-scan listeners
    }
}
