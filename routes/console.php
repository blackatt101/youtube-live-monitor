<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| YouTube Live Monitoring Schedule
|--------------------------------------------------------------------------
*/

// Check monitored channels every minute (auto-detection)
Schedule::command('monitor:check')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/monitor-schedule.log'));

// Clean up old stream data every 12 hours
// Keeps channel subscriptions but removes ended stream records older than 12 hours
// Using twiceDaily at 00:00 and 12:00 for every 12 hours
Schedule::command('monitor:cleanup --hours=12')
    ->twiceDaily(0, 12)  // Runs at 00:00 and 12:00 (midnight and noon)
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/cleanup-schedule.log'));

// Alternative: Use cron expression for custom timing
// Schedule::command('monitor:cleanup --hours=12')
//     ->cron('0 */12 * * *')  // Every 12 hours at minute 0
//     ->withoutOverlapping()
//     ->appendOutputTo(storage_path('logs/cleanup-schedule.log'));
