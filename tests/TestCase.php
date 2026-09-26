<?php

namespace Tests;

use App\Http\Middleware\EnsureAdminSecondFactor;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Feature tests render Inertia views; skip the Vite manifest lookup so
        // they run without a front-end build.
        $this->withoutVite();

        $this->statisticsGathered = false;
    }

    /**
     * Whether this test has already given the planner its statistics.
     */
    private bool $statisticsGathered = false;

    /**
     * PostgreSQL chooses a plan from the statistics ANALYZE collected, and ANALYZE
     * only counts committed rows. RefreshDatabase seeds inside a transaction that is
     * rolled back and never committed, so on a fresh database the planner is told
     * every table is empty. It then picks the plan that is right for no rows and
     * ruinous for thousands: a nested loop. A count over 117 obligations took
     * 18 seconds here, and a listing re-runs that count once per facet, so a single
     * page never finished rendering. That is why `PHP tests (PostgreSQL)` ran for
     * hours and was killed without ever reporting, while the SQLite job passed in
     * eleven minutes — SQLite's planner does not work this way.
     *
     * ANALYZE run inside the transaction samples the rows that transaction can see,
     * which is exactly what is missing. It is done once per test, on the first
     * request, because that is when a plan is first chosen, and only on PostgreSQL.
     */
    public function call($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null)
    {
        if (! $this->statisticsGathered && DB::connection()->getDriverName() === 'pgsql') {
            $this->statisticsGathered = true;
            DB::statement('ANALYZE');
        }

        return parent::call($method, $uri, $parameters, $cookies, $files, $server, $content);
    }

    /**
     * An admin must have enrolled an authenticator, proved a code this session and
     * confirmed their password before the routes the suite exercises will answer.
     * Tests about submissions, billing or tools are not about those gates, so acting
     * as an admin satisfies all three. The gates themselves are tested in
     * AdminTwoFactorTest and AdminAuditLogTest, which clear these keys deliberately.
     */
    public function actingAs(Authenticatable $user, $guard = null)
    {
        parent::actingAs($user, $guard);

        if (method_exists($user, 'isAdmin') && $user->isAdmin()) {
            if (! $user->hasTwoFactorEnabled()) {
                $user->forceFill([
                    'two_factor_secret' => 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ',
                    'two_factor_recovery_codes' => [],
                    'two_factor_confirmed_at' => now(),
                ])->save();
            }
            $this->withSession([
                EnsureAdminSecondFactor::SESSION_KEY => (int) $user->getKey(),
                'auth.password_confirmed_at' => time(),
            ]);
        }

        return $this;
    }
}
