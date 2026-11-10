<?php

namespace Tests\Feature;

use App\Models\LicenceEnquiry;
use App\Models\Subscriber;
use App\Models\Subscription;
use App\Models\TemplateDownloadRequest;
use App\Models\TemplateUpdateOptout;
use App\Models\User;
use App\Support\Analytics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

/**
 * The audit's data-rights findings: the export left out billing and records kept by email,
 * deletion left them (and a running subscription) behind, signed pages could reach the
 * analytics provider, and consent could not be withdrawn on the site.
 */
class PrivacyRightsTest extends TestCase
{
    use RefreshDatabase;

    private function reader(array $attrs = []): User
    {
        return User::factory()->create($attrs + ['email' => 'ana@example.org', 'email_verified_at' => now()]);
    }

    private function emailRecords(): void
    {
        Subscriber::create(['email' => 'Ana@example.org', 'token' => Subscriber::newToken(), 'topics' => ['all'], 'confirmed_at' => now()]);
        TemplateDownloadRequest::create(['template_slug' => 'ai-risk-register', 'name' => 'Ana', 'email' => 'ana@example.org', 'company' => 'Acme', 'terms_accepted_at' => now(), 'marketing_consent_at' => now()]);
        LicenceEnquiry::create(['tier' => array_key_first(LicenceEnquiry::TIERS), 'name' => 'Ana', 'email' => 'ana@example.org', 'organization' => 'Acme', 'use_case' => 'Research']);
        TemplateUpdateOptout::create(['email' => 'ana@example.org', 'unsubscribed_at' => now(), 'source' => 'email']);
    }

    public function test_the_export_includes_billing_and_the_records_kept_under_a_verified_address(): void
    {
        $user = $this->reader();
        $this->emailRecords();
        $user->createToken('watches');

        $data = $this->actingAs($user)->get(route('account.export'))->assertOk()->json();

        $this->assertArrayHasKey('billing', $data);
        $this->assertCount(1, $data['api_tokens']);
        $this->assertArrayNotHasKey('token', $data['api_tokens'][0], 'never the token itself');
        $this->assertCount(1, $data['newsletter']);
        $this->assertSame('ai-risk-register', $data['template_requests'][0]['template']);
        $this->assertCount(1, $data['licence_enquiries']);
        $this->assertNotNull($data['template_update_unsubscribed_at']);
    }

    public function test_an_unverified_account_does_not_get_records_sent_from_that_address(): void
    {
        $user = $this->reader(['email_verified_at' => null]);
        $this->emailRecords();

        // The export itself needs a verified address (route middleware); the controller's
        // own check is a second line in case that ever changes.
        $this->actingAs($user)->get(route('account.export'))->assertRedirect(route('verification.notice'));
    }

    public function test_deleting_the_account_removes_what_is_kept_under_its_address_and_its_tokens(): void
    {
        $user = $this->reader();
        $this->emailRecords();
        $user->createToken('watches');

        $this->actingAs($user)->delete('/profile', ['password' => 'password'])->assertRedirect('/');

        $this->assertNull(User::find($user->id));
        $this->assertSame(0, Subscriber::count());
        $this->assertSame(0, TemplateDownloadRequest::count());
        $this->assertSame(0, PersonalAccessToken::count());
        // Kept, as the privacy notice says: the unsubscribe (so it stays unsubscribed) and the enquiry.
        $this->assertSame(1, TemplateUpdateOptout::count());
        $this->assertSame(1, LicenceEnquiry::count());
    }

    public function test_an_active_paid_plan_must_be_cancelled_before_deletion(): void
    {
        config(['billing.enabled' => true, 'billing.plans.pro_monthly.product_id' => 'pdt_month']);
        $user = $this->reader();
        Subscription::create(['user_id' => $user->id, 'provider' => 'dodo', 'provider_subscription_id' => 'sub_live', 'product_id' => 'pdt_month', 'plan_key' => 'pro_monthly', 'status' => 'active', 'current_period_end' => now()->addMonth()]);

        $this->actingAs($user)->from('/profile')->delete('/profile', ['password' => 'password'])
            ->assertRedirect('/profile')->assertSessionHasErrorsIn('userDeletion', 'password');
        $this->assertNotNull(User::find($user->id));
    }

    public function test_pages_whose_address_acts_for_the_reader_never_load_analytics(): void
    {
        foreach (['alerts.unsubscribe', 'templates.updates.unsubscribe', 'templates.download', 'tools.ready', 'tools.file'] as $route) {
            $this->assertContains($route, Analytics::TOKEN_ROUTES);
            $this->assertNotNull(app('router')->getRoutes()->getByName($route), "{$route} exists");
        }
    }

    public function test_consent_can_be_withdrawn_from_every_page_and_the_privacy_notice_names_the_processors(): void
    {
        config(['aipolicytracker.google_analytics_id' => 'G-TEST123', 'aipolicytracker.analytics_require_consent' => true]);
        $this->get('/')->assertOk()->assertSee('data-consent-reset', false)->assertSee('Analytics choices');

        $privacy = $this->get(route('privacy'))->assertOk();
        foreach (['Cloudflare', 'Resend', 'Google Analytics', 'Dodo Payments', 'billing address'] as $name) {
            $privacy->assertSee($name);
        }
    }
}
