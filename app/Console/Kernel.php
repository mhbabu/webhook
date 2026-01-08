<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected $commands = [
        // \App\Console\Commands\SyncSocialCommand::class,
        // Commands\IdraData::class,
        Commands\SyncSocialCommand::class,

        // php artisan social:sync facebook
        // php artisan email:fetch
    ];

    protected function schedule(Schedule $schedule)
    {
        $schedule->command('email:fetch')
            ->everyFiveMinutes()
            ->withoutOverlapping()
            ->runInBackground()
            ->onOneServer() // safe for multi-server
            ->appendOutputTo(storage_path('logs/email-cron.log'));
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
