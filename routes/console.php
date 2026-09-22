<?php

use App\Models\JobRun;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Scheduled work
|--------------------------------------------------------------------------
|
| One place for every recurring job, so a host with `php artisan schedule:run`
| on a minute cron (or `schedule:work` in the container) runs the platform
| without GitHub Actions. Each job is catalogued in App\Models\JobRun, which
| is also what the admin "Jobs" page and /cron/* run, so every run, whoever
| started it, is recorded and the dashboard can say how the last one ended.
|
*/

$timetable = [
    'policy_import' => fn ($e) => $e->dailyAt('02:30'),
    'aiid_sync' => fn ($e) => $e->dailyAt('03:15'),
    'alerts' => fn ($e) => $e->dailyAt('06:30'),
    'digest' => fn ($e) => $e->weeklyOn(1, '07:00'),
    'external_import' => fn ($e) => $e->weeklyOn(0, '04:00'),
    'email_domains' => fn ($e) => $e->weeklyOn(3, '05:00'),
];

Schedule::call(fn () => Cache::put('scheduler.last_tick', now()->toIso8601String(), 3600))->everyMinute()->name('scheduler:tick');

foreach ($timetable as $job => $when) {
    $when(Schedule::call(fn () => JobRun::run($job, 'schedule'))->name('job:'.$job)->withoutOverlapping()->description(JobRun::JOBS[$job]['label']));
}
