<?php

namespace Tests;

use App\Http\Middleware\EnsureAdminSecondFactor;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Feature tests render Inertia views; skip the Vite manifest lookup so
        // they run without a front-end build.
        $this->withoutVite();
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
