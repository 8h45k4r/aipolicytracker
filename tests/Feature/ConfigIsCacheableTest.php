<?php

namespace Tests\Feature;

use Closure;
use Tests\TestCase;

/**
 * `php artisan config:cache` is not optional on this project: azure/startup.sh
 * runs it, and it ran under `set -e`, so a config file that cannot be cached
 * aborted the container's startup before `policy:import`, `external:import` and
 * the seeders. The site went down and the deploy workflow still reported green,
 * because the workflow only ships the zip; the startup script runs in the
 * container.
 *
 * Nothing in config/ may therefore hold a closure. Behaviour that needs one
 * belongs in app/ — see VerificationRuleset and CompletenessChecks, which were
 * moved out of config/ for exactly this reason.
 *
 * The real command also runs in CI. This test is the fast, hermetic half: it
 * names the offending key instead of printing a serialization error.
 */
class ConfigIsCacheableTest extends TestCase
{
    public function test_no_configuration_value_is_a_closure(): void
    {
        $offenders = [];
        $walk = function (array $values, string $path) use (&$walk, &$offenders) {
            foreach ($values as $key => $value) {
                $current = $path === '' ? (string) $key : $path.'.'.$key;
                if ($value instanceof Closure) {
                    $offenders[] = $current;
                } elseif (is_array($value)) {
                    $walk($value, $current);
                }
            }
        };
        $walk(config()->all(), '');

        $this->assertSame(
            [],
            $offenders,
            'config:cache cannot serialize a closure, and azure/startup.sh runs it on every deploy. '
            .'Move the behaviour into app/ and leave only serializable values in config/. Offending keys: '
            .implode(', ', $offenders)
        );
    }

    public function test_the_policies_that_moved_out_of_config_still_drive_the_reports(): void
    {
        // Guards the move itself: the rules and checks must still reach the services,
        // not silently become empty arrays that make every gate trivially pass.
        $this->assertNotEmpty(
            app(\App\Services\Verification\VerificationPolicy::class)->rules(),
            'the verification rules did not survive the move out of config/'
        );
        $this->assertNotEmpty(
            app(\App\Services\Completeness\CompletenessReport::class)->checks(),
            'the completeness checks did not survive the move out of config/'
        );

        // The serializable knobs stay in config/ and are still read from there.
        $this->assertIsInt(config('verification.critical_budget'));
        $this->assertIsInt(config('completeness.required_budget'));
        $this->assertNotEmpty(config('verification.tracks'));
        $this->assertNotEmpty(config('completeness.kinds'));
    }
}
