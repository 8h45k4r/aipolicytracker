<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * The container's health, beyond "the web server answers".
 *
 * /up proves PHP boots and serves. It says nothing about the two processes
 * docker/entrypoint.sh starts in the background: if the scheduler dies, every
 * digest, alert and import stops while /up stays green. This checks the
 * database answers and, where this container is meant to schedule, that the
 * scheduler reported in the last few minutes (routes/console.php writes
 * `scheduler.last_tick` every minute).
 *
 * Exit 0 when healthy, 1 otherwise, so the Docker HEALTHCHECK can run it.
 */
class HealthCheckCommand extends Command
{
    protected $signature = 'aip:health';

    protected $description = 'Exit non-zero if the database is unreachable or the scheduler has stopped ticking';

    public function handle(): int
    {
        try {
            DB::select('select 1');
        } catch (Throwable $e) {
            $this->error('database: '.$e->getMessage());

            return self::FAILURE;
        }
        $this->line('database: ok');

        if (config('aipolicytracker.health.scheduler') === 'off') {
            $this->line('scheduler: not checked (SCHEDULER=off)');

            return self::SUCCESS;
        }

        $marker = (string) config('aipolicytracker.health.boot_marker');
        $grace = (int) config('aipolicytracker.health.grace_minutes', 5);
        clearstatcache(true, $marker);
        if ($marker !== '' && is_file($marker) && filemtime($marker) > now()->subMinutes($grace)->getTimestamp()) {
            $this->line("scheduler: not checked (container started under {$grace} minutes ago)");

            return self::SUCCESS;
        }

        try {
            $tick = Cache::get('scheduler.last_tick');
        } catch (Throwable $e) {
            $this->error('scheduler: could not read the last tick: '.$e->getMessage());

            return self::FAILURE;
        }

        if (! $tick) {
            $this->error('scheduler: no tick recorded');

            return self::FAILURE;
        }

        try {
            $at = Carbon::parse($tick);
        } catch (Throwable) {
            $this->error('scheduler: unreadable tick '.$tick);

            return self::FAILURE;
        }

        $maxAge = (int) config('aipolicytracker.health.tick_max_age_minutes', 5);
        if ($at->lt(now()->subMinutes($maxAge))) {
            $this->error("scheduler: last tick {$at->toIso8601String()}, more than {$maxAge} minutes ago");

            return self::FAILURE;
        }

        $this->line("scheduler: ok (last tick {$at->toIso8601String()})");

        return self::SUCCESS;
    }
}
