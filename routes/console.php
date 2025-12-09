<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Console\Commands\CustomerInactivityChecker;
use App\Console\Commands\EndChatAlertChecker;


Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command(CustomerInactivityChecker::class)->everyMinute();
Schedule::command(EndChatAlertChecker::class)->everyMinute();
