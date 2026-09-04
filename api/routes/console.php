<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Trial reminders (day 7/10/13) + day-14 lock. Runs hourly so locks apply
// promptly and reminders fire the same day they are due.
Schedule::command('sajio:process-trials')
    ->hourly()
    ->withoutOverlapping()
    ->onOneServer();
