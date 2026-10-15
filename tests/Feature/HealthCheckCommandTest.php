<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * `aip:health` is what the Docker HEALTHCHECK runs besides /up: it fails when
 * the scheduler stops reporting, which /up cannot see.
 */
class HealthCheckCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // No boot marker, so no start-up grace period applies.
        config([
            'aipolicytracker.health.scheduler' => 'work',
            'aipolicytracker.health.boot_marker' => sys_get_temp_dir().'/aip-health-test-absent-'.uniqid(),
        ]);
    }

    public function test_a_fresh_tick_is_healthy(): void
    {
        Cache::put('scheduler.last_tick', now()->subMinute()->toIso8601String(), 3600);

        $this->artisan('aip:health')->assertExitCode(0);
    }

    public function test_a_stale_tick_is_unhealthy(): void
    {
        Cache::put('scheduler.last_tick', now()->subMinutes(10)->toIso8601String(), 3600);

        $this->artisan('aip:health')->assertExitCode(1);
    }

    public function test_a_missing_tick_is_unhealthy(): void
    {
        Cache::forget('scheduler.last_tick');

        $this->artisan('aip:health')->assertExitCode(1);
    }

    public function test_the_tick_is_ignored_when_the_container_does_not_schedule(): void
    {
        config(['aipolicytracker.health.scheduler' => 'off']);
        Cache::put('scheduler.last_tick', now()->subHour()->toIso8601String(), 3600);

        $this->artisan('aip:health')->assertExitCode(0);
    }

    public function test_a_recent_container_start_allows_a_grace_period(): void
    {
        $marker = tempnam(sys_get_temp_dir(), 'aip-booted');
        touch($marker);
        config(['aipolicytracker.health.boot_marker' => $marker]);
        Cache::forget('scheduler.last_tick');

        try {
            $this->artisan('aip:health')->assertExitCode(0);

            touch($marker, now()->subMinutes(6)->getTimestamp());
            $this->artisan('aip:health')->assertExitCode(1);
        } finally {
            @unlink($marker);
        }
    }
}
