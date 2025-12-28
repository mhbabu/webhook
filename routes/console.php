<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Console\Commands\CustomerInactivityChecker;
use App\Console\Commands\EndChatAlertChecker;


Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Define the application's command schedule.
// Schedule::command(CustomerInactivityChecker::class)->everyMinute()->withoutOverlapping()->onOneServer();
// Schedule::command(EndChatAlertChecker::class)->everyMinute()->withoutOverlapping()->onOneServer();
