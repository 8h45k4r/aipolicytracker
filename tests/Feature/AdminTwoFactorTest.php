<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureAdminSecondFactor;
use App\Models\AdminAuditLog;
use App\Models\User;
use App\Services\Security\Totp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The admin area requires a second factor per session. These tests sign in
 * without the harness shortcut in TestCase::actingAs, so every gate is exercised.
 */
class AdminTwoFactorTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

    protected function setUp(): void
    {
        parent::setUp();
        config(['aipolicytracker.admin_emails' => ['admin@example.com']]);
    }

    /** Signs the user in with no session shortcuts, the way a browser would arrive. */
    private function signIn(User $user): static
    {
        Auth::login($user);

        return $this;
    }

    private function admin(array $attributes = []): User
    {
        return User::factory()->create(['email' => 'admin@example.com'] + $attributes);
    }

    private function enrolled(): User
    {
        return $this->admin([
            'two_factor_secret' => self::SECRET,
            'two_factor_recovery_codes' => [Hash::make('abcde-fghij')],
            'two_factor_confirmed_at' => now(),
        ]);
    }

    public function test_an_admin_who_has_not_enrolled_is_sent_to_enrolment_from_every_backend_route(): void
    {
        $admin = $this->admin();

        foreach (['/backend/dashboard', '/backend/review', '/backend/admin/settings', '/backend/admin/downloads/export'] as $path) {
            $this->signIn($admin)->get($path)->assertRedirect(route('admin.two-factor.enrol'));
        }
    }

    public function test_enrolment_shows_a_key_and_confirms_only_a_code_derived_from_it(): void
    {
        $admin = $this->admin();

        $page = $this->signIn($admin)->get(route('admin.two-factor.enrol'))->assertOk();
        $page->assertSee('Setup key')->assertSee('otpauth://totp/', false);
        $pending = session('auth.two_factor_pending_secret');
        $this->assertMatchesRegularExpression('/^[A-Z2-7]{32}$/', $pending);

        // The page must not draw the key through any third-party image service.
        $this->assertStringNotContainsString('chart.googleapis', $page->getContent());
        $this->assertStringNotContainsString('qrserver', $page->getContent());

        // A wrong code leaves the account unchanged.
        $this->post(route('admin.two-factor.confirm'), ['code' => '000000'])->assertSessionHasErrors('code');
        $this->assertNull($admin->fresh()->two_factor_confirmed_at);

        // The right code enrols, hands out eight recovery codes once, and passes the session.
        $response = $this->post(route('admin.two-factor.confirm'), ['code' => Totp::code($pending)]);
        $response->assertRedirect(route('admin.two-factor.recovery'));
        $fresh = $admin->fresh();
        $this->assertSame($pending, $fresh->two_factor_secret);
        $this->assertNotNull($fresh->two_factor_confirmed_at);
        $this->assertCount(8, $fresh->two_factor_recovery_codes);
        $this->assertSame($admin->id, session(EnsureAdminSecondFactor::SESSION_KEY));
        $this->assertNull(session('auth.two_factor_pending_secret'));

        $codes = session('recovery_codes');
        $this->assertCount(8, $codes);
        $this->get(route('admin.two-factor.recovery'))->assertOk()->assertSee($codes[0]);
        $this->get('/backend/dashboard')->assertOk();
    }

    public function test_the_secret_is_encrypted_at_rest_and_never_serialised(): void
    {
        $admin = $this->enrolled();

        $raw = \DB::table('users')->where('id', $admin->id)->value('two_factor_secret');
        $this->assertNotSame(self::SECRET, $raw, 'the column must not hold the plain secret');
        $this->assertSame(self::SECRET, $admin->fresh()->two_factor_secret, 'the model decrypts it');
        $this->assertArrayNotHasKey('two_factor_secret', $admin->fresh()->toArray());
        $this->assertArrayNotHasKey('two_factor_recovery_codes', $admin->fresh()->toArray());
    }

    public function test_an_enrolled_admin_must_prove_a_code_every_session(): void
    {
        $admin = $this->enrolled();

        $this->signIn($admin)->get('/backend/dashboard')->assertRedirect(route('admin.two-factor.challenge'));
        $this->assertSame(url('/backend/dashboard'), session('url.intended'));

        $this->post(route('admin.two-factor.verify'), ['code' => '123456'])->assertSessionHasErrors('code');
        $this->get('/backend/dashboard')->assertRedirect(route('admin.two-factor.challenge'));

        $this->post(route('admin.two-factor.verify'), ['code' => Totp::code(self::SECRET)])->assertRedirect(url('/backend/dashboard'));
        $this->get('/backend/dashboard')->assertOk();
    }

    public function test_a_recovery_code_works_exactly_once(): void
    {
        $admin = $this->enrolled();

        $this->signIn($admin)->post(route('admin.two-factor.verify'), ['code' => 'ABCDE-FGHIJ'])->assertRedirect();
        $this->assertSame($admin->id, session(EnsureAdminSecondFactor::SESSION_KEY));
        $this->assertCount(0, $admin->fresh()->two_factor_recovery_codes, 'the used code is removed');

        // A new session, same code: refused.
        $this->flushSession();
        $this->signIn($admin)->post(route('admin.two-factor.verify'), ['code' => 'abcde-fghij'])->assertSessionHasErrors('code');
        $this->assertNull(session(EnsureAdminSecondFactor::SESSION_KEY));
    }

    public function test_the_challenge_is_rate_limited(): void
    {
        $admin = $this->enrolled();
        $this->signIn($admin);

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('admin.two-factor.verify'), ['code' => '000000'])->assertSessionHasErrors('code');
        }
        $this->post(route('admin.two-factor.verify'), ['code' => Totp::code(self::SECRET)])->assertStatus(429);
        $this->assertNull(session(EnsureAdminSecondFactor::SESSION_KEY), 'a correct code after the limit still does not pass');
    }

    public function test_a_passed_session_is_bound_to_the_user_who_passed_it(): void
    {
        $first = $this->enrolled();
        $second = User::factory()->create(['email' => 'other@example.com', 'two_factor_secret' => self::SECRET, 'two_factor_confirmed_at' => now(), 'two_factor_recovery_codes' => []]);
        config(['aipolicytracker.admin_emails' => ['admin@example.com', 'other@example.com']]);

        $this->signIn($first)->withSession([EnsureAdminSecondFactor::SESSION_KEY => $first->id])->get('/backend/dashboard')->assertOk();
        // Same browser session, different admin: the earlier pass does not carry over.
        $this->signIn($second)->get('/backend/dashboard')->assertRedirect(route('admin.two-factor.challenge'));
    }

    public function test_non_admins_are_untouched_by_the_second_factor(): void
    {
        $member = User::factory()->create(['email' => 'member@example.com']);
        $this->signIn($member)->get('/backend/dashboard')->assertRedirect(route('home', absolute: false));
        $this->signIn($member)->get(route('admin.two-factor.enrol'))->assertRedirect(route('home', absolute: false));
        $this->signIn($member)->get('/following')->assertOk();
    }

    public function test_regenerating_recovery_codes_needs_a_fresh_password_and_invalidates_the_old_ones(): void
    {
        $admin = $this->enrolled();
        $this->signIn($admin)->withSession([EnsureAdminSecondFactor::SESSION_KEY => $admin->id]);

        $this->post(route('admin.two-factor.regenerate'))->assertRedirect(route('password.confirm'));
        $this->assertTrue(Hash::check('abcde-fghij', $admin->fresh()->two_factor_recovery_codes[0]), 'nothing changed without the password');

        $this->withSession(['auth.password_confirmed_at' => time()])->post(route('admin.two-factor.regenerate'))->assertRedirect(route('admin.two-factor.recovery'));
        $fresh = $admin->fresh();
        $this->assertCount(8, $fresh->two_factor_recovery_codes);
        $this->assertFalse(collect($fresh->two_factor_recovery_codes)->contains(fn ($h) => Hash::check('abcde-fghij', $h)), 'the old code is gone');
    }

    public function test_the_operator_reset_command_removes_the_authenticator_and_leaves_an_audit_row(): void
    {
        $admin = $this->enrolled();

        $this->artisan('admin:two-factor-reset', ['email' => 'nobody@example.com'])->assertExitCode(1);
        $this->artisan('admin:two-factor-reset', ['email' => 'ADMIN@example.com'])->expectsConfirmation("Remove the authenticator from {$admin->email}? They will be asked to enrol again at their next sign-in.", 'yes')->assertExitCode(0);

        $fresh = $admin->fresh();
        $this->assertNull($fresh->two_factor_secret);
        $this->assertNull($fresh->two_factor_confirmed_at);
        $this->assertSame(1, AdminAuditLog::where('route_name', 'admin:two-factor-reset')->where('user_id', $admin->id)->count());
        $this->signIn($fresh)->get('/backend/dashboard')->assertRedirect(route('admin.two-factor.enrol'));
    }
}
