<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled Commands
|--------------------------------------------------------------------------
|
| Attendance system scheduled tasks.
|
*/

// Detect and mark absent employees at end of business day (6 PM)
Schedule::command('attendance:detect-absent')
    ->dailyAt('18:00')
    ->timezone('Asia/Jakarta')
    ->description('Mark employees without check-in as ABSENT');
