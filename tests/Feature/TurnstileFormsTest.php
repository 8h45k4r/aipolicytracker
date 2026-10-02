<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\ContributorSubmission;
use App\Models\Subscriber;
use App\Models\User;
use App\Providers\AppSettingsServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class TurnstileFormsTest extends TestCase
{
    use RefreshDatabase;

    private const OWNER = 'owner@example.test';

    private function configure(): void
    {
        config(['services.turnstile.site_key' => '0x4AAAAAAAsitekey', 'services.turnstile.secret_key' => '0x4AAAAAAAsecret']);
    }

    public function test_keys_saved_in_admin_settings_switch_turnstile_on_without_touching_the_environment(): void
    {
        config(['aipolicytracker.admin_emails' => [self::OWNER], 'services.turnstile.site_key' => null, 'services.turnstile.secret_key' => null]);
        $owner = User::factory()->create(['email' => self::OWNER]);

        $this->actingAs($owner)->get(route('backend.admin.settings'))->assertOk()->assertSee('Not configured: the forms rely on the honeypot');

        $this->actingAs($owner)->post(route('backend.admin.settings.save'), [
            'turnstile_site_key' => '0x4AAAAAAAsitekey',
            'turnstile_secret_key' => '0x4AAAAAAAsecret',
        ])->assertSessionHasNoErrors();

        $this->assertSame('0x4AAAAAAAsecret', AppSetting::get('turnstile_secret_key'));
        $this->assertNotSame('0x4AAAAAAAsecret', AppSetting::query()->find('turnstile_secret_key')->value, 'stored encrypted');

        // The provider applies stored settings at boot, which every request runs.
        (new AppSettingsServiceProvider($this->app))->boot();
        $this->assertSame('0x4AAAAAAAsitekey', config('services.turnstile.site_key'));
        $this->assertSame('0x4AAAAAAAsecret', config('services.turnstile.secret_key'));
        $html = $this->actingAs($owner->fresh())->get(route('backend.admin.settings'))->assertOk()->getContent();
        $this->assertStringContainsString('Active, using the keys saved here.', $html);
        $this->assertStringNotContainsString('0x4AAAAAAAsecret', $html, 'the secret is never sent back to the browser');
    }

    public function test_malformed_keys_are_refused(): void
    {
        config(['aipolicytracker.admin_emails' => [self::OWNER]]);
        $owner = User::factory()->create(['email' => self::OWNER]);

        $this->actingAs($owner)->post(route('backend.admin.settings.save'), ['turnstile_secret_key' => 'has spaces and <tags>'])
            ->assertSessionHasErrors('turnstile_secret_key');
    }

    public function test_the_key_check_tells_a_wrong_secret_from_a_right_one(): void
    {
        config(['aipolicytracker.admin_emails' => [self::OWNER]]);
        $owner = User::factory()->create(['email' => self::OWNER]);
        $this->configure();

        Http::fake(['challenges.cloudflare.com/*' => Http::sequence()
            ->push(['success' => false, 'error-codes' => ['invalid-input-secret']])
            ->push(['success' => false, 'error-codes' => ['invalid-input-response']])]);

        $this->actingAs($owner)->post(route('backend.admin.settings.turnstile'))->assertSessionHas('error', fn ($m) => str_contains($m, 'rejected the secret key'));
        $this->actingAs($owner)->post(route('backend.admin.settings.turnstile'))->assertSessionHas('success', fn ($m) => str_contains($m, 'accepted the secret key'));
    }

    public function test_public_forms_carry_the_widget_only_when_configured(): void
    {
        foreach (['/contribute', '/subscribe', '/register', '/'] as $path) {
            $this->assertStringNotContainsString('data-turnstile-slot', $this->get($path)->assertOk()->getContent(), $path);
        }
        $this->configure();
        foreach (['/contribute', '/subscribe', '/register'] as $path) {
            $this->assertStringContainsString('data-turnstile-slot data-sitekey="0x4AAAAAAAsitekey"', $this->get($path)->assertOk()->getContent(), $path);
        }
        // Not loaded with the page: public.js fetches the script on first use.
        $this->assertStringNotContainsString('challenges.cloudflare.com/turnstile/v0/api.js', $this->get('/contribute')->getContent());
    }

    public function test_contribute_subscribe_and_register_refuse_a_failed_check_and_accept_a_passed_one(): void
    {
        Mail::fake();
        $this->configure();
        Http::fake(['challenges.cloudflare.com/*' => Http::sequence()
            ->push(['success' => false])->push(['success' => true])
            ->push(['success' => false])->push(['success' => true])
            ->push(['success' => false])->push(['success' => true])]);

        $contribution = ['type' => 'correction', 'summary' => 'The date on this record is out of date.'];
        $this->post(route('contribute.store'), $contribution + ['cf-turnstile-response' => 'bad'])->assertSessionHasErrors('turnstile');
        $this->assertSame(0, ContributorSubmission::count());
        $this->post(route('contribute.store'), $contribution + ['cf-turnstile-response' => 'good'])->assertSessionHasNoErrors();
        $this->assertSame(1, ContributorSubmission::count());

        $this->post(route('subscribe.store'), ['email' => 'reader@aipolicytracker.org', 'cf-turnstile-response' => 'bad'])->assertSessionHasErrors('email');
        $this->assertSame(0, Subscriber::count());
        $this->post(route('subscribe.store'), ['email' => 'reader@aipolicytracker.org', 'cf-turnstile-response' => 'good'])->assertSessionHasNoErrors();
        $this->assertSame(1, Subscriber::count());

        $account = ['name' => 'New Reader', 'email' => 'new.reader@aipolicytracker.org', 'password' => 'A-long-Passw0rd!', 'password_confirmation' => 'A-long-Passw0rd!', 'terms_condition' => '1'];
        $this->post(route('register'), $account + ['cf-turnstile-response' => 'bad'])->assertSessionHasErrors('turnstile');
        $this->assertSame(0, User::count());
        $this->post(route('register'), $account + ['cf-turnstile-response' => 'good'])->assertSessionHasNoErrors();
        $this->assertSame(1, User::count());
    }
}
