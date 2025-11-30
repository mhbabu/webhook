<?php

use App\Console\Commands\CustomerInactivityChecker;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;


//Scheduling
Schedule::command(CustomerInactivityChecker::class)->everyMinute();

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
