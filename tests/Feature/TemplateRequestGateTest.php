<?php

namespace Tests\Feature;

use App\Mail\TemplateDownloadMail;
use App\Models\TemplateDownloadRequest;
use App\Services\Templates\TemplateBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * A template is requested with a work address and arrives by email as signed links.
 * Consumer and throwaway mailboxes are refused, Turnstile is checked on the server
 * when configured, and a link works only with its signature.
 */
class TemplateRequestGateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->artisan('policy:import');
        app(TemplateBuilder::class)->build('ai-risk-register');
        // The suite switches domain enforcement off globally; this test is about it.
        config(['email.enforce' => true, 'email.check_deliverability' => false]);
        Mail::fake();
    }

    private function form(array $overrides = []): array
    {
        return array_replace(['name' => 'Priya Natarajan', 'email' => 'priya@acme-bank.com', 'company' => 'Acme Bank', 'job_title' => 'Head of AI risk', 'country' => 'India', 'terms' => '1', 'updates' => '1'], $overrides);
    }

    public function test_a_work_address_gets_signed_links_by_email_and_they_serve_the_file(): void
    {
        $this->post('/templates/ai-risk-register/request', $this->form())
            ->assertRedirect(route('templates.show', 'ai-risk-register').'#download')
            ->assertSessionHas('template_requested', 'priya@acme-bank.com');

        $request = TemplateDownloadRequest::sole();
        $this->assertSame('Acme Bank', $request->company);
        $this->assertNotNull($request->marketing_consent_at);
        $this->assertNotNull($request->emailed_at);

        Mail::assertSent(TemplateDownloadMail::class, function (TemplateDownloadMail $mail) {
            $html = $mail->render();
            preg_match('#href="([^"]+/templates/ai-risk-register/download\?[^"]+)"#', $html, $m);
            $url = html_entity_decode($m[1] ?? '');
            $this->assertStringContainsString('signature=', $url);
            $this->get($url)->assertOk()->assertDownload();

            return $mail->hasTo('priya@acme-bank.com');
        });
        $this->assertSame(1, TemplateDownloadRequest::sole()->downloads);
    }

    public function test_consumer_and_throwaway_mailboxes_are_refused(): void
    {
        $this->post('/templates/ai-risk-register/request', $this->form(['email' => 'priya@gmail.com']))->assertSessionHasErrors('email');
        $this->post('/templates/ai-risk-register/request', $this->form(['email' => 'x@mailinator.com']))->assertSessionHasErrors('email');
        $this->post('/templates/ai-risk-register/request', $this->form(['terms' => null]))->assertSessionHasErrors('terms');
        $this->post('/templates/ai-risk-register/request', $this->form(['company' => '']))->assertSessionHasErrors('company');

        $this->assertSame(0, TemplateDownloadRequest::count());
        Mail::assertNothingSent();
    }

    public function test_a_failed_turnstile_check_sends_nothing(): void
    {
        config(['services.turnstile.site_key' => 'site', 'services.turnstile.secret_key' => 'secret']);
        Http::fake(['challenges.cloudflare.com/*' => Http::sequence()->push(['success' => false])->push(['success' => true])]);

        $this->post('/templates/ai-risk-register/request', $this->form(['cf-turnstile-response' => 'bad']))->assertSessionHasErrors('captcha');
        $this->assertSame(0, TemplateDownloadRequest::count());

        $this->post('/templates/ai-risk-register/request', $this->form(['cf-turnstile-response' => 'good']))->assertSessionHasNoErrors();
        $this->assertSame(1, TemplateDownloadRequest::count());
        $this->assertStringContainsString('data-turnstile-slot data-sitekey="site"', $this->get('/templates/ai-risk-register')->getContent());
    }

    public function test_a_honeypot_submission_looks_accepted_and_does_nothing(): void
    {
        $this->post('/templates/ai-risk-register/request', $this->form(['website' => 'http://spam.example']))->assertRedirect();
        $this->assertSame(0, TemplateDownloadRequest::count());
        Mail::assertNothingSent();
    }

    public function test_a_link_without_a_valid_signature_or_past_its_expiry_does_not_serve_the_file(): void
    {
        $request = TemplateDownloadRequest::create(['template_slug' => 'ai-risk-register', 'name' => 'A', 'email' => 'a@acme-bank.com', 'company' => 'Acme', 'terms_accepted_at' => now()]);
        $url = $request->downloadUrl('xlsx');

        $this->get($url.'x')->assertRedirect(route('templates.show', 'ai-risk-register').'#download');
        $this->travel(TemplateDownloadRequest::LINK_DAYS + 1)->days();
        $this->get($url)->assertRedirect(route('templates.show', 'ai-risk-register').'#download');
    }
}
