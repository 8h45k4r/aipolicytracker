<?php

namespace Tests\Feature;

use App\Mail\SubscriptionConfirmMail;
use App\Mail\WeeklyDigestMail;
use App\Models\AppSetting;
use App\Models\Subscriber;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SubscriberAndSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->artisan('policy:import');
    }

    public function test_subscription_is_double_opt_in_with_unsubscribe(): void
    {
        Mail::fake();
        $this->post('/subscribe', ['email' => 'Reader@Example.org', 'topics' => ['nepal'], 'source' => 'changes'])->assertRedirect();
        $sub = Subscriber::where('email', 'reader@example.org')->firstOrFail();
        $this->assertNull($sub->confirmed_at);
        $this->assertSame(['nepal'], $sub->topics);
        Mail::assertSent(SubscriptionConfirmMail::class, fn ($m) => $m->hasTo('reader@example.org'));

        $this->get('/subscribe/confirm/'.$sub->token)->assertOk()->assertSee('You are subscribed');
        $this->assertNotNull($sub->fresh()->confirmed_at);

        $this->get('/subscribe/unsubscribe/'.$sub->token)->assertOk()->assertSee('Unsubscribed');
        $this->assertNotNull($sub->fresh()->unsubscribed_at);
        $this->assertSame(0, Subscriber::active()->count());
    }

    public function test_honeypot_and_invalid_email_do_not_create_subscribers(): void
    {
        Mail::fake();
        $this->post('/subscribe', ['email' => 'bot@example.org', 'website' => 'http://spam'])->assertRedirect();
        $this->post('/subscribe', ['email' => 'not-an-email'])->assertSessionHasErrors('email');
        $this->assertSame(0, Subscriber::count());
        Mail::assertNothingSent();
    }

    public function test_digest_sends_only_to_confirmed_matching_subscribers_and_cron_requires_token(): void
    {
        Mail::fake();
        Subscriber::create(['email' => 'a@example.org', 'token' => Subscriber::newToken(), 'topics' => ['all'], 'confirmed_at' => now()]);
        Subscriber::create(['email' => 'b@example.org', 'token' => Subscriber::newToken(), 'topics' => ['all']]); // unconfirmed
        DB::table('change_events')->limit(1)->update(['occurred_on' => now()->toDateString()]);

        $this->artisan('digest:send')->assertExitCode(0);
        Mail::assertSent(WeeklyDigestMail::class, 1);
        Mail::assertSent(WeeklyDigestMail::class, fn ($m) => $m->hasTo('a@example.org'));

        // Policy-level topics: a subscriber following one instrument only gets its changes.
        $change = \App\Models\ChangeEvent::with('policyInstrument')->whereNotNull('policy_instrument_id')->first();
        $follower = Subscriber::create(['email' => 'c@example.org', 'token' => Subscriber::newToken(), 'topics' => [$change->policyInstrument->slug], 'confirmed_at' => now()]);
        $this->assertTrue($follower->wants($change));
        $other = \App\Models\ChangeEvent::where('id', '!=', $change->id)->where(fn ($q) => $q->whereNull('policy_instrument_id')->orWhere('policy_instrument_id', '!=', $change->policy_instrument_id))->where('jurisdiction_id', '!=', $change->jurisdiction_id)->first();
        $this->assertFalse($follower->wants($other));

        $this->postJson('/cron/digest')->assertStatus(401);
        AppSetting::put('cron_token', str_repeat('t', 32));
        $this->postJson('/cron/digest', [], ['Authorization' => 'Bearer '.str_repeat('t', 32)])->assertOk();
    }

    public function test_settings_are_encrypted_at_rest_masked_in_ui_and_applied_to_config(): void
    {
        config(['aipolicytracker.admin_emails' => ['editor@example.test']]);
        $admin = User::factory()->create(['email' => 'editor@example.test']);
        $member = User::factory()->create();

        $this->actingAs($member)->get('/backend/admin/settings')->assertRedirect();
        $this->actingAs($admin)->get('/backend/admin/settings')->assertOk()->assertSee('Settings and API keys');

        $this->actingAs($admin)->post('/backend/admin/settings', ['resend_key' => 're_TESTKEY_1234567890', 'mail_mailer' => 'array', 'mail_from_address' => 'no-reply@example.org'])->assertRedirect();
        $raw = DB::table('app_settings')->where('key', 'resend_key')->value('value');
        $this->assertNotNull($raw);
        $this->assertStringNotContainsString('re_TESTKEY', $raw);
        $this->assertSame('re_TESTKEY_1234567890', AppSetting::get('resend_key'));
        $this->actingAs($admin)->get('/backend/admin/settings')->assertOk()->assertDontSee('re_TESTKEY_1234567890')->assertSee('re_T');

        // Provider applies stored values on boot.
        (new \App\Providers\AppSettingsServiceProvider($this->app))->boot();
        $this->assertSame('array', config('mail.default'));
        $this->assertSame('re_TESTKEY_1234567890', config('services.resend.key'));
        $this->assertSame('no-reply@example.org', config('mail.from.address'));
    }

    public function test_test_mail_uses_the_branded_template_and_footer_lists_social_profiles(): void
    {
        Mail::fake();
        config(['aipolicytracker.admin_emails' => ['editor@example.test']]);
        $admin = User::factory()->create(['email' => 'editor@example.test']);
        $this->actingAs($admin)->post('/backend/admin/settings/test-mail', ['to' => 'ops@example.org'])->assertRedirect();
        Mail::assertSent(\App\Mail\TestMail::class, function ($m) {
            $html = $m->render();

            return $m->hasTo('ops@example.org') && str_contains($html, 'Follow us on social') && str_contains($html, 'linkedin.com/company/aipolicytracker') && str_contains($html, 'brand/social/instagram.png');
        });
        $this->get('/')->assertOk()->assertSee('https://www.linkedin.com/company/aipolicytracker/')->assertSee('https://www.instagram.com/aipolicytracker/')->assertSee('https://x.com/aipolicytracker')->assertSee('https://www.facebook.com/aipolicytracker');
    }

    public function test_admin_pages_render_for_admins_only(): void
    {
        config(['aipolicytracker.admin_emails' => ['editor@example.test']]);
        $admin = User::factory()->create(['email' => 'editor@example.test']);
        $urls = ['/backend/dashboard', '/backend/admin/submissions', '/backend/admin/subscribers', '/backend/admin/external', '/backend/admin/subscribers/export', '/backend/admin/downloads', '/backend/admin/downloads/export', '/backend/admin/tools', '/backend/admin/tools/create'];
        foreach ($urls as $url) {
            $this->get($url)->assertRedirect('/login');
        }
        $this->actingAs($admin);
        foreach ($urls as $url) {
            $this->get($url)->assertOk();
        }
    }
}
