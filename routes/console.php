<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

use Illuminate\Support\Facades\Schedule;

// GameHub: refresh every linked library every six hours (the work itself runs on the queue worker).
Schedule::command('gamehub:sync')->everySixHours()->withoutOverlapping();

// GameHub: pick up IGDB "time to beat" for newly matched games.
Schedule::command('gamehub:time-to-beat')->dailyAt('04:00')->withoutOverlapping();

// GameHub: email the last 30 days on the first of each month.
Schedule::command('gamehub:monthly-summary')->monthlyOn(1, '08:00');
