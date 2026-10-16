<?php

namespace Tests\Feature;

use App\Enums\AdminRole;
use App\Mail\LicenceEnquiryMail;
use App\Models\LicenceEnquiry;
use App\Models\Tool;
use App\Models\User;
use App\Services\Templates\TemplateBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * /pricing as the commercial page: indexable and in the footer whether or not checkout is
 * on, four tiers with nothing claimed that the code does not deliver, FAQ markup that
 * matches the visible questions, and a licence enquiry form that stores before it
 * confirms, mails the contact address, and is guarded like the other public forms.
 */
class PricingAndLicenceEnquiryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // The suite switches domain enforcement off globally; the work-email rule is part of this form.
        config(['email.enforce' => true, 'email.check_deliverability' => false, 'aipolicytracker.contact_emails' => ['hello@aipolicytracker.test']]);
        Mail::fake();
    }

    private function form(array $overrides = []): array
    {
        return array_replace([
            'name' => 'Priya Natarajan',
            'email' => 'priya@acme-bank.com',
            'organization' => 'Acme Bank',
            'tier' => 'commercial',
            'use_case' => 'An internal compliance dashboard for 40 analysts, refreshed daily.',
            'coverage_request' => 'Kenya and Nigeria',
            'source' => 'pricing',
        ], $overrides);
    }

    public function test_pricing_is_indexable_lists_four_tiers_and_carries_faq_markup(): void
    {
        $html = $this->get('/pricing')->assertOk()->getContent();

        $this->assertStringNotContainsString('noindex', $html);
        foreach (['open', 'builder', 'team', 'commercial'] as $tier) {
            $this->assertStringContainsString('data-tier="'.$tier.'"', $html);
        }
        $this->assertStringContainsString('$149', $html);
        $this->assertStringContainsString('From $6,000', $html);
        $this->assertStringContainsString('$29', $html);
        $this->assertStringContainsString('Coming, not built yet', $html, 'unbuilt items are labelled, never listed as included');
        $this->assertStringContainsString(route('pricing', ['tier' => 'team']).'#licence', $html);
        $this->assertStringContainsString('id="licence"', $html);
        // Checkout is off by default: Builder takes no orders, the page still says so.
        $this->assertStringContainsString('Not yet available', $html);

        preg_match_all('#<script type="application/ld\+json"[^>]*>(.*?)</script>#s', $html, $blocks);
        $faq = collect($blocks[1])->map(fn ($j) => json_decode($j, true))
            ->flatMap(fn ($j) => isset($j['@graph']) ? $j['@graph'] : [$j])
            ->firstWhere('@type', 'FAQPage');
        $this->assertNotNull($faq, 'FAQPage JSON-LD is emitted');
        $questions = array_column($faq['mainEntity'], 'name');
        $this->assertContains('Can I use the data commercially?', $questions);
        $this->assertGreaterThanOrEqual(3, count($questions));
        $this->assertLessThanOrEqual(5, count($questions));
        foreach ($questions as $q) {
            $this->assertStringContainsString(e($q), $html, 'every marked-up question is visible');
        }
        $this->assertStringContainsString(config('aipolicytracker.data_license'), $faq['mainEntity'][0]['acceptedAnswer']['text']);
    }

    public function test_the_footer_links_to_pricing_whether_or_not_checkout_is_on(): void
    {
        $this->get('/')->assertOk()->assertSee('href="'.route('pricing').'"', false)->assertSee('Pricing and data licences');
        $this->get('/open-data')->assertOk()->assertSee('href="'.route('pricing').'"', false);
    }

    public function test_an_enquiry_is_stored_then_mailed_then_confirmed(): void
    {
        $this->post(route('licence-enquiry.store'), $this->form())
            ->assertRedirect(route('pricing').'#licence')
            ->assertSessionHas('licence_enquiry', fn ($m) => str_contains($m, 'has been sent') && str_contains($m, 'priya@acme-bank.com'));

        $enquiry = LicenceEnquiry::sole();
        $this->assertSame('Acme Bank', $enquiry->organization);
        $this->assertSame('commercial', $enquiry->tier);
        $this->assertSame('Kenya and Nigeria', $enquiry->coverage_request);
        $this->assertSame('pricing', $enquiry->source);
        $this->assertNotNull($enquiry->notified_at);
        Mail::assertSent(LicenceEnquiryMail::class, fn (LicenceEnquiryMail $m) => $m->hasTo('hello@aipolicytracker.test') && $m->hasReplyTo('priya@acme-bank.com') && str_contains($m->render(), 'An internal compliance dashboard'));

        // The confirmation replaces the form on the page it came from.
        $this->followingRedirects()->post(route('licence-enquiry.store'), $this->form(['source' => 'open-data', 'email' => 'ops@acme-bank.com']))
            ->assertOk()->assertSee('data-licence-enquiry-sent', false);
        $this->assertSame('open-data', LicenceEnquiry::latest('id')->first()->source);
    }

    public function test_a_failed_mail_still_keeps_the_enquiry_and_does_not_claim_it_was_sent(): void
    {
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('SMTP down'));

        $this->post(route('licence-enquiry.store'), $this->form())->assertSessionHas('licence_enquiry', fn ($m) => ! str_contains($m, 'sent') && str_contains($m, 'recorded'));
        $this->assertNull(LicenceEnquiry::sole()->notified_at);
    }

    public function test_validation_refuses_and_stores_nothing(): void
    {
        $this->post(route('licence-enquiry.store'), $this->form(['use_case' => '']))->assertRedirect(route('pricing').'#licence')->assertSessionHasErrors(['use_case'], null, 'licence');
        $this->post(route('licence-enquiry.store'), $this->form(['use_case' => 'too short']))->assertSessionHasErrors(['use_case'], null, 'licence');
        $this->post(route('licence-enquiry.store'), $this->form(['email' => 'priya@gmail.com']))->assertSessionHasErrors(['email'], null, 'licence');
        $this->post(route('licence-enquiry.store'), $this->form(['email' => 'x@mailinator.com']))->assertSessionHasErrors(['email'], null, 'licence');
        $this->post(route('licence-enquiry.store'), $this->form(['organization' => '']))->assertSessionHasErrors(['organization'], null, 'licence');
        $this->post(route('licence-enquiry.store'), $this->form(['tier' => 'platinum']))->assertSessionHasErrors(['tier'], null, 'licence');
        // An unknown source cannot steer the redirect anywhere else.
        $this->post(route('licence-enquiry.store'), $this->form(['source' => 'https://evil.example', 'name' => '']))->assertRedirect(route('pricing').'#licence');

        $this->assertSame(0, LicenceEnquiry::count());
        Mail::assertNothingSent();
        $this->get('/pricing')->assertOk();
    }

    public function test_honeypot_and_turnstile_stop_bots_without_a_success_message(): void
    {
        $this->post(route('licence-enquiry.store'), $this->form(['website' => 'http://spam.example']))
            ->assertRedirect(route('pricing').'#licence')->assertSessionMissing('licence_enquiry');

        config(['services.turnstile.site_key' => 'site', 'services.turnstile.secret_key' => 'secret']);
        Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => false])]);
        $this->post(route('licence-enquiry.store'), $this->form(['cf-turnstile-response' => 'bad']))->assertSessionHasErrors(['turnstile'], null, 'licence');

        $this->assertSame(0, LicenceEnquiry::count());
        Mail::assertNothingSent();
        $this->assertStringContainsString('data-turnstile-slot data-sitekey="site"', $this->get('/pricing')->getContent());
    }

    public function test_the_form_is_throttled(): void
    {
        foreach (range(1, 10) as $i) {
            $this->post(route('licence-enquiry.store'), $this->form(['email' => "p{$i}@acme-bank.com"]))->assertRedirect();
        }
        $this->post(route('licence-enquiry.store'), $this->form(['email' => 'p11@acme-bank.com']))->assertStatus(429);
        $this->assertSame(10, LicenceEnquiry::count());
    }

    public function test_the_admin_list_needs_audience_view_and_exports_what_it_shows(): void
    {
        config(['aipolicytracker.admin_emails' => ['owner@example.org']]);
        $owner = User::factory()->create(['email' => 'owner@example.org']);
        $analyst = User::factory()->create(['admin_role' => AdminRole::Analyst]);
        $this->actingAs($analyst)->get(route('backend.admin.enquiries.index'))->assertOk()->assertSee('No enquiries yet');
        $this->app['auth']->forgetGuards();
        LicenceEnquiry::create($this->form(['tier' => 'team', 'organization' => 'Team Co', 'email' => 't@team.test']) + ['notified_at' => now()]);
        LicenceEnquiry::create($this->form());
        $reviewer = User::factory()->create(['admin_role' => AdminRole::Reviewer]);
        $member = User::factory()->create();

        $this->get(route('backend.admin.enquiries.index'))->assertRedirect();
        $this->actingAs($member)->get(route('backend.admin.enquiries.index'))->assertRedirect('/');
        $this->actingAs($reviewer)->get(route('backend.admin.enquiries.index'))->assertForbidden();
        $this->actingAs($reviewer)->get(route('backend.admin.enquiries.export'))->assertForbidden();

        $html = $this->actingAs($analyst)->get(route('backend.admin.enquiries.index'))->assertOk()->getContent();
        $this->assertStringContainsString('Team Co', $html);
        $this->assertStringContainsString('Acme Bank', $html);
        $this->assertStringContainsString('Licence enquiries', $html, 'listed in the admin menu');
        $filtered = $this->actingAs($analyst)->get(route('backend.admin.enquiries.index', ['tier' => 'team']))->assertOk()->getContent();
        $this->assertStringContainsString('Team Co', $filtered);
        $this->assertStringNotContainsString('Acme Bank', $filtered);

        $body = $this->actingAs($owner)->get(route('backend.admin.enquiries.export', ['tier' => 'commercial']))->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8')->streamedContent();
        $rows = array_map('str_getcsv', array_values(array_filter(explode("\n", trim(substr($body, 3))))));
        $this->assertSame('received_at', $rows[0][0]);
        $this->assertCount(2, $rows, 'header plus the one commercial enquiry');
        $this->assertSame('Acme Bank', $rows[1][4]);
    }

    public function test_the_commercial_use_line_is_on_policy_template_and_tool_pages(): void
    {
        $this->seed();
        $this->artisan('policy:import');
        app(TemplateBuilder::class)->build('ai-risk-register');
        $line = 'Using this in a product? Commercial use is allowed under';
        $link = route('pricing').'#licence';

        $this->get('/policies/eu-ai-act')->assertOk()->assertSee($line)->assertSee($link, false);
        $this->get('/templates/ai-risk-register')->assertOk()->assertSee($line)->assertSee($link, false);

        // The seeded tools redirect to templates, so a tool page is checked on a copy of one.
        $tool = Tool::where('slug', 'ai-system-inventory-template')->firstOrFail()->replicate();
        $tool->slug = 'commercial-line-test-tool';
        $tool->save();
        $this->get('/guides/tools/commercial-line-test-tool')->assertOk()->assertSee($line)->assertSee(config('aipolicytracker.data_license'));
    }
}
