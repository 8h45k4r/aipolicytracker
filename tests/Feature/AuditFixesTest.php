<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureAdminSecondFactor;
use App\Mail\SecondFactorLockedMail;
use App\Mail\WeeklyDigestMail;
use App\Models\AlertChannel;
use App\Models\ChangeEvent;
use App\Models\ChannelDelivery;
use App\Models\Follow;
use App\Models\Jurisdiction;
use App\Models\PolicyInstrument;
use App\Models\RecordVerification;
use App\Models\Subscriber;
use App\Models\User;
use App\Services\Alerts\AlertBuilder;
use App\Services\Alerts\WebhookDispatcher;
use App\Services\Security\Totp;
use App\Support\OutboundUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** Regression tests for the six fixes from the October 2026 code audit. */
class AuditFixesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('policy:import');
        Mail::fake();
    }

    private function change(string $slug, string $occurredOn): ChangeEvent
    {
        $eu = Jurisdiction::where('slug', 'eu')->firstOrFail();

        return ChangeEvent::create(['slug' => $slug, 'jurisdiction_id' => $eu->id, 'occurred_on' => $occurredOn, 'title' => 'Change '.$slug, 'what_changed' => 'Something changed.', 'impact_level' => 'high', 'official_source_url' => 'https://example.org/'.$slug, 'published_at' => now()]);
    }

    private function owner(): User
    {
        config(['aipolicytracker.admin_emails' => ['owner@example.org']]);

        return User::factory()->create(['email' => 'owner@example.org']);
    }

    // 1. The weekly digest

    public function test_the_digest_goes_out_every_week_and_carries_late_changes_once(): void
    {
        $this->travelTo(Carbon::parse('2026-10-05 07:00:00'));
        Subscriber::create(['email' => 'reader@example.org', 'token' => Subscriber::newToken(), 'confirmed_at' => now(), 'topics' => ['all']]);
        $this->change('week-one', '2026-10-04');
        $this->artisan('digest:send')->assertExitCode(0);
        Mail::assertSent(WeeklyDigestMail::class, 1);

        // A re-run the same week sends nothing.
        $this->travelTo(Carbon::parse('2026-10-07 07:00:00'));
        $this->artisan('digest:send')->assertExitCode(0);
        Mail::assertSent(WeeklyDigestMail::class, 1);

        // The next Monday it goes out again, with this week's change and one dated last
        // week but imported after last week's issue; last week's change is not repeated.
        $this->travelTo(Carbon::parse('2026-10-12 07:00:00'));
        $this->change('week-two', '2026-10-10');
        $late = $this->change('late-import', '2026-10-03');
        $this->artisan('digest:send')->assertExitCode(0);
        Mail::assertSent(WeeklyDigestMail::class, 2);
        Mail::assertSent(WeeklyDigestMail::class, function ($mail) use ($late) {
            $slugs = $mail->changes->pluck('slug');

            return $slugs->contains('week-two') && $slugs->contains($late->slug) && ! $slugs->contains('week-one');
        });
    }

    // 2. Daily alerts

    public function test_an_alert_includes_a_change_imported_after_the_previous_alert(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        Follow::create(['user_id' => $user->id, 'subject_type' => 'jurisdiction', 'subject_slug' => 'eu']);
        $builder = app(AlertBuilder::class);

        // Dates after the import in setUp, which stamps records published as of today.
        // The previous alert went out at 06:30 on 13 November; the change dated 13 November was
        // imported at 02:30 on 14 November.
        $this->travelTo(Carbon::parse('2026-11-14 02:30:00'));
        $change = $this->change('dated-the-13th', '2026-11-13');
        $this->travelTo(Carbon::parse('2026-11-14 06:30:00'));
        $digest = $builder->build($user, Carbon::parse('2026-11-13 06:30:00'), now());
        $this->assertTrue($digest['changes']->contains('id', $change->id));

        // And not again the day after.
        $this->travelTo(Carbon::parse('2026-11-15 06:30:00'));
        $this->assertFalse($builder->build($user, Carbon::parse('2026-11-14 06:30:00'), now())['changes']->contains('id', $change->id));
    }

    // 3. Exported verifications

    public function test_an_exported_verification_no_longer_overrides_the_file(): void
    {
        $policy = PolicyInstrument::where('review_status', 'pending_review')->firstOrFail();
        RecordVerification::create(['record_type' => 'policy', 'record_slug' => $policy->slug, 'review_status' => 'verified', 'confidence_level' => 'high', 'last_verified_at' => '2026-10-01', 'reviewed_by' => 'Some Reviewer', 'exported' => true]);
        $this->artisan('policy:import');
        $this->assertSame('pending_review', $policy->fresh()->review_status, 'the YAML is the source of truth once exported');

        RecordVerification::where('record_slug', $policy->slug)->update(['exported' => false]);
        $this->artisan('policy:import');
        $this->assertSame('verified', $policy->fresh()->review_status, 'an unexported decision still holds');
    }

    // 4. Admin takedowns

    public function test_a_takedown_survives_the_import_and_republishing_restores_the_first_date(): void
    {
        $policy = PolicyInstrument::published()->firstOrFail();
        $first = $policy->published_at;
        $owner = $this->owner();

        $this->actingAs($owner)->post(route('backend.review.publish', ['policy', $policy->slug]), ['publish' => 0])->assertRedirect();
        $this->artisan('policy:import');
        $this->assertNull($policy->fresh()->published_at, 'the nightly import does not undo a takedown');

        $this->travel(3)->days();
        $this->actingAs($owner)->post(route('backend.review.publish', ['policy', $policy->slug]), ['publish' => 1])->assertRedirect();
        $this->assertEquals($first->toDateTimeString(), $policy->fresh()->published_at->toDateTimeString(), 'republished with its first date');
        $this->artisan('policy:import');
        $this->assertNotNull($policy->fresh()->published_at);
    }

    // 5. Security

    public function test_alert_webhooks_cannot_point_at_private_addresses(): void
    {
        foreach (['https://127.0.0.1/x', 'https://169.254.169.254/latest', 'https://10.0.0.1/admin', 'https://[::1]/x', 'https://[::ffff:127.0.0.1]/x', 'https://100.64.0.1/x', 'https://localhost:8080/x', 'https://db.internal/x'] as $url) {
            $this->assertNotNull(OutboundUrl::refusal($url), $url);
        }
        $this->assertNull(OutboundUrl::refusal('https://8.8.8.8/hook'));
        $this->assertSame(false, OutboundUrl::pinnedOptions('https://8.8.8.8/hook')['allow_redirects']);

        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user)->post(route('alerts.channels.store'), ['kind' => 'webhook', 'endpoint' => 'https://127.0.0.1:5432/x'])->assertSessionHasErrors('endpoint');
        $this->assertSame(0, AlertChannel::where('user_id', $user->id)->count());

        // One saved before the check, or re-pointed since, is refused at send time.
        Http::fake();
        $channel = AlertChannel::create(['user_id' => $user->id, 'kind' => 'webhook', 'endpoint' => 'https://169.254.169.254/latest', 'secret' => AlertChannel::newSecret(), 'enabled' => true, 'confirmed_at' => now()]);
        $dispatcher = app(WebhookDispatcher::class);
        $delivery = $dispatcher->queue($channel, ['event' => 'alert.test']);
        $this->assertFalse($dispatcher->attempt($delivery));
        $this->assertStringStartsWith('Refused', ChannelDelivery::find($delivery->id)->last_error);
        Http::assertNothingSent();
    }

    public function test_admin_authenticator_codes_lock_after_a_daily_cap(): void
    {
        $secret = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';
        config(['aipolicytracker.admin_emails' => ['admin@example.com']]);
        $admin = User::factory()->create(['email' => 'admin@example.com', 'two_factor_secret' => $secret, 'two_factor_recovery_codes' => [Hash::make('abcde-fghij')], 'two_factor_confirmed_at' => now()]);
        Auth::login($admin);

        for ($i = 1; $i <= 10; $i++) {
            $this->post(route('admin.two-factor.verify'), ['code' => '000000'])->assertSessionHasErrors('code');
            if ($i % 5 === 0) {
                $this->travel(61)->seconds(); // past the per-minute limit
            }
        }
        Mail::assertSent(SecondFactorLockedMail::class, fn ($m) => $m->hasTo('admin@example.com'));

        // Locked: even the right authenticator code is refused…
        $this->post(route('admin.two-factor.verify'), ['code' => Totp::code($secret)])->assertSessionHasErrors('code');
        $this->assertNull(session(EnsureAdminSecondFactor::SESSION_KEY));
        // …but a recovery code still works.
        $this->post(route('admin.two-factor.verify'), ['code' => 'abcde-fghij'])->assertRedirect();
        $this->assertSame($admin->id, session(EnsureAdminSecondFactor::SESSION_KEY));
    }

    public function test_api_tokens_end_with_the_sessions_and_never_serve_a_suspended_account(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $plain = $user->createToken('integration')->plainTextToken;
        $this->withToken($plain)->getJson('/api/v1/watches')->assertOk();

        $user->endAllSessions();
        $this->assertSame(0, $user->tokens()->count());

        // A token that somehow survives still stops working once the account is suspended.
        $other = User::factory()->create(['email_verified_at' => now()]);
        $token = $other->createToken('integration')->plainTextToken;
        $other->forceFill(['suspended_at' => now()])->save();
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/watches')->assertUnauthorized();
    }

    // 6. Public sign-in

    public function test_the_sign_in_page_is_for_readers_and_returns_them_to_the_record(): void
    {
        $html = $this->get(route('login', ['return' => '/policies/eu-ai-act']))->assertOk()->getContent();
        $this->assertStringContainsString('<h1', $html);
        $this->assertStringContainsString('Sign in to AIPolicyTracker', $html);
        $this->assertStringContainsString(route('register'), $html);
        $this->assertStringNotContainsString('Sign in to the admin', $html);
        $this->assertSame(url('/policies/eu-ai-act'), session('url.intended'));

        $reader = User::factory()->create(['email' => 'reader@example.org', 'password' => Hash::make('Secret-pass1!'), 'email_verified_at' => now()]);
        $this->post(route('login'), ['email' => $reader->email, 'password' => 'Secret-pass1!'])->assertRedirect(url('/policies/eu-ai-act'));
    }

    public function test_the_return_path_never_leaves_the_site(): void
    {
        foreach (['//evil.example/x', 'https://evil.example/', '/\\evil.example', "/x\ty"] as $bad) {
            $this->flushSession();
            $this->get(route('login', ['return' => $bad]))->assertOk();
            $this->assertNull(session('url.intended'), $bad);
        }
        $this->get('/policies/eu-ai-act')->assertOk()->assertSee(route('login', ['return' => '/policies/eu-ai-act']), false);
    }
}
