<?php

namespace App\Providers;

use App\Events\SendSystemConfigureMessageEvent;
use App\Listeners\SystemConfigureMessageSendListener;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //EVETS LISTENERS
        Event::listen(SendSystemConfigureMessageEvent::class, SystemConfigureMessageSendListener::class);
        Schema::defaultStringLength(191);
    }
}
