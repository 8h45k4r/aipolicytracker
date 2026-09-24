<?php

namespace Tests\Feature;

use App\Models\BillingEvent;
use App\Models\JobRun;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use StandardWebhooks\Webhook;
use Tests\TestCase;

/**
 * Regressions for the backend security audit: each test is one hole that was open.
 */
class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['aipolicytracker.admin_emails' => ['owner@example.com']]);
    }

    public function test_an_unverified_account_on_an_owner_address_is_not_an_owner(): void
    {
        $squatter = User::factory()->unverified()->create(['email' => 'owner@example.com']);

        $this->assertFalse($squatter->isOwner());
        $this->assertFalse($squatter->isAdmin());
        Auth::login($squatter);
        $response = $this->get('/backend/security/enrol');
        $this->assertNotSame(200, $response->getStatusCode(), 'an unverified owner address must not reach enrolment');
        $this->assertStringNotContainsString('/backend/', (string) $response->headers->get('Location'));

        $squatter->forceFill(['email_verified_at' => now()])->save();
        $this->assertTrue($squatter->fresh()->isOwner());
    }

    public function test_changing_the_profile_email_to_an_owner_address_does_not_grant_ownership(): void
    {
        $user = User::factory()->create();
        Auth::login($user);

        $this->patch('/profile', ['name' => $user->name, 'email' => 'owner@example.com'])->assertSessionHasNoErrors();

        $this->assertNull($user->fresh()->email_verified_at);
        $this->assertFalse($user->fresh()->isAdmin());
    }

    public function test_a_password_only_admin_session_cannot_change_the_account(): void
    {
        $owner = User::factory()->create([
            'email' => 'owner@example.com',
            'two_factor_secret' => 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ',
            'two_factor_confirmed_at' => now(),
        ]);
        Auth::login($owner); // signed in with the password, no second factor yet

        $this->patch('/profile', ['name' => 'x', 'email' => 'moved@example.com'])->assertRedirect(route('admin.two-factor.challenge'));
        $this->delete('/profile', ['password' => 'password'])->assertRedirect(route('admin.two-factor.challenge'));
        $this->put('/password', ['current_password' => 'password', 'password' => 'N3w-Passw0rd!', 'password_confirmation' => 'N3w-Passw0rd!'])
            ->assertRedirect(route('admin.two-factor.challenge'));

        $this->assertSame('owner@example.com', $owner->fresh()->email);
    }

    public function test_an_ordinary_account_still_manages_its_own_profile(): void
    {
        $user = User::factory()->create();
        Auth::login($user);

        $this->patch('/profile', ['name' => 'New name', 'email' => $user->email])->assertRedirect('/profile');
        $this->assertSame('New name', $user->fresh()->name);
    }

    public function test_ending_all_sessions_removes_stored_sessions_and_the_remember_token(): void
    {
        config(['session.driver' => 'database']);
        $user = User::factory()->create(['remember_token' => 'old-token']);
        $other = User::factory()->create();
        foreach ([['a', $user->id], ['b', $user->id], ['c', $other->id]] as [$id, $uid]) {
            DB::table('sessions')->insert(['id' => $id, 'user_id' => $uid, 'payload' => '', 'last_activity' => time()]);
        }

        $user->endAllSessions('b');

        $this->assertSame(['b', 'c'], DB::table('sessions')->orderBy('id')->pluck('id')->all());
        $this->assertNotSame('old-token', $user->fresh()->remember_token);
    }

    public function test_a_second_factor_code_from_an_account_without_one_is_not_a_500(): void
    {
        $owner = User::factory()->create(['email' => 'owner@example.com']);
        Auth::login($owner);

        $this->post(route('admin.two-factor.verify'), ['code' => '123456'])->assertRedirect(route('admin.two-factor.enrol'));
    }

    public function test_a_webhook_that_failed_while_being_applied_is_applied_on_retry(): void
    {
        $secret = 'whsec_dGVzdHNlY3JldHRlc3RzZWNyZXR0ZXN0c2VjcmV0';
        config(['billing.webhook_secret' => $secret, 'billing.plans.pro_monthly.product_id' => 'pdt_month']);
        $user = User::factory()->create();
        $payload = [
            'type' => 'subscription.active',
            'timestamp' => now()->toIso8601String(),
            'data' => [
                'subscription_id' => 'sub_retry', 'product_id' => 'pdt_month', 'status' => 'active',
                'next_billing_date' => now()->addMonth()->toIso8601String(),
                'customer' => ['customer_id' => 'cus_1', 'email' => $user->email],
                'metadata' => ['app_user_id' => (string) $user->id, 'plan_key' => 'pro_monthly'],
            ],
        ];
        // The first delivery was recorded and then failed part-way.
        BillingEvent::create(['provider' => 'dodo', 'event_id' => 'msg_failed', 'event_type' => 'subscription.active', 'payload' => $payload, 'received_at' => now()])
            ->forceFill(['outcome' => 'error', 'error' => 'deadlock', 'processed_at' => now()])->save();

        $body = json_encode($payload);
        $timestamp = time();
        $this->call('POST', '/webhooks/dodo', [], [], [], [
            'HTTP_WEBHOOK_ID' => 'msg_failed', 'HTTP_WEBHOOK_TIMESTAMP' => (string) $timestamp,
            'HTTP_WEBHOOK_SIGNATURE' => (new Webhook($secret))->sign('msg_failed', $timestamp, $body), 'CONTENT_TYPE' => 'application/json',
        ], $body)->assertOk()->assertJsonMissing(['outcome' => 'duplicate']);

        $this->assertTrue(Subscription::where('provider_subscription_id', 'sub_retry')->exists());
        $this->assertNotSame('error', BillingEvent::where('event_id', 'msg_failed')->value('outcome'));
        $this->assertSame(1, BillingEvent::where('event_id', 'msg_failed')->count());
    }

    public function test_the_subscribe_form_sends_at_most_three_confirmations_per_address_a_day(): void
    {
        Mail::fake();

        foreach (range(1, 5) as $i) {
            $this->withServerVariables(['REMOTE_ADDR' => "10.0.0.{$i}"])
                ->post('/subscribe', ['email' => 'victim@example.com'])
                ->assertSessionHas('success');
        }

        Mail::assertSentCount(3);
    }

    public function test_only_the_default_site_card_exists(): void
    {
        $this->get('/og/site/anything-else.png')->assertNotFound();
    }

    public function test_array_query_parameters_are_not_a_500(): void
    {
        $this->getJson('/api/v1/policies?page[]=x')->assertOk();
        $this->get('/ai-risk/incidents/browse?q[]=x&domain[]=y')->assertOk();
        $this->get('/tools/applicability-check?jurisdictions[][]=x&domains[][]=y')->assertOk();
    }

    public function test_an_admin_cannot_rename_themselves_into_the_reviewer_roster(): void
    {
        $owner = User::factory()->create(['email' => 'owner@example.com', 'name' => 'Owner']);
        $this->actingAs($owner)->patch('/profile', ['name' => 'Bhaskar Bhatt', 'email' => $owner->email])->assertSessionHasErrors('name');
        $this->assertSame('Owner', $owner->fresh()->name);
    }

    public function test_heavy_public_routes_are_throttled(): void
    {
        foreach (['risk.incidents.export', 'risk.risks.export', 'open-data.download', 'open-data.csv', 'open-data.ndjson', 'llms.full', 'social.card'] as $name) {
            $middleware = Route::getRoutes()->getByName($name)->gatherMiddleware();
            $this->assertNotEmpty(array_filter($middleware, fn ($m) => str_starts_with($m, 'throttle:')), "{$name} has no throttle");
        }
    }

    public function test_security_headers_reach_the_api_and_unmatched_routes(): void
    {
        foreach (['/api/v1/', '/definitely-not-a-page'] as $url) {
            $response = $this->get($url);
            $response->assertHeader('X-Content-Type-Options', 'nosniff');
            $this->assertTrue($response->headers->has('Content-Security-Policy'), "no CSP on {$url}");
        }
    }

    public function test_a_job_does_not_run_twice_at_once(): void
    {
        $lock = Cache::lock('job-run:digest', 60);
        $this->assertTrue($lock->get());

        $run = JobRun::run('digest', 'cron');

        $this->assertSame(1, $run->exit_code);
        $this->assertStringContainsString('already running', $run->output);
        $lock->release();
    }

    public function test_a_forwarded_host_does_not_rewrite_generated_links(): void
    {
        config(['app.trusted_proxies' => '*']);
        Route::get('/_probe/url', fn () => request()->url().'|'.request()->getScheme().'|'.request()->ip());

        $body = $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])->withHeaders([
            'X-Forwarded-Host' => 'evil.example', 'X-Forwarded-Port' => '1', 'X-Forwarded-Prefix' => '/x',
            'X-Forwarded-Proto' => 'https', 'X-Forwarded-For' => '203.0.113.9',
        ])->get('/_probe/url')->getContent();

        [$url, $scheme, $ip] = explode('|', $body);
        $this->assertStringNotContainsString('evil.example', $url);
        $this->assertStringNotContainsString(':1', $url);
        $this->assertStringNotContainsString('/x/', $url);
        // The two headers that are still trusted still work.
        $this->assertSame('https', $scheme);
        $this->assertSame('203.0.113.9', $ip);
    }
}
