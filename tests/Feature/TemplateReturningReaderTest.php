<?php

namespace Tests\Feature;

use App\Mail\TemplateDownloadMail;
use App\Models\Subscriber;
use App\Models\TemplateDownloadRequest;
use App\Models\User;
use App\Services\Templates\TemplateBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** A reader downloading several templates fills the form once; terms are accepted every time. */
class TemplateReturningReaderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->artisan('policy:import');
        app(TemplateBuilder::class)->build('ai-risk-register');
        app(TemplateBuilder::class)->build('ai-system-inventory');
        config(['email.enforce' => true, 'email.check_deliverability' => false]);
        Mail::fake();
    }

    /** First template through the form; opening the emailed link proves the address. */
    private function proveReader(): TemplateDownloadRequest
    {
        $this->post('/templates/ai-risk-register/request', ['name' => 'Priya Natarajan', 'email' => 'priya@acme-bank.com', 'company' => 'Acme Bank', 'terms' => '1'])->assertRedirect();
        $first = TemplateDownloadRequest::sole();
        $this->get($first->downloadUrl('xlsx'))->assertOk()->assertCookie(TemplateDownloadRequest::KNOWN_COOKIE, (string) $first->id);

        return $first->fresh();
    }

    public function test_a_proven_reader_gets_the_next_template_in_one_step(): void
    {
        $first = $this->proveReader();
        $cookie = [TemplateDownloadRequest::KNOWN_COOKIE => (string) $first->id];

        $html = $this->withCookies($cookie)->get(route('templates.show', 'ai-system-inventory'))->assertOk()->getContent();
        $this->assertStringContainsString('data-template-known', $html);
        $this->assertStringContainsString('Priya Natarajan', $html);
        $this->assertStringNotContainsString('id="tr-company"', $html, 'no form to fill again');
        $this->assertStringContainsString('name="terms" value="1" required', $html, 'terms are accepted every time');

        $this->withCookies($cookie)->post('/templates/ai-system-inventory/request', ['known' => '1', 'terms' => '1', 'updates' => '1'])
            ->assertRedirect(route('templates.show', 'ai-system-inventory').'#download')
            ->assertSessionHas('template_ready', fn ($ready) => $ready['email'] === 'priya@acme-bank.com' && isset($ready['links']['xlsx']));

        $second = TemplateDownloadRequest::where('template_slug', 'ai-system-inventory')->sole();
        $this->assertSame(['Priya Natarajan', 'priya@acme-bank.com', 'Acme Bank'], [$second->name, $second->email, $second->company]);
        $this->assertNotNull($second->terms_accepted_at);
        $this->assertNotNull($second->marketing_consent_at);
        Mail::assertSent(TemplateDownloadMail::class, 1);
    }

    public function test_one_step_still_needs_the_terms_and_a_proven_address(): void
    {
        $first = $this->proveReader();
        $this->withCookies([TemplateDownloadRequest::KNOWN_COOKIE => (string) $first->id])
            ->post('/templates/ai-system-inventory/request', ['known' => '1'])->assertSessionHasErrors('terms');

        // A request whose link was never opened proves nothing: the full form is required.
        $unopened = TemplateDownloadRequest::create(['template_slug' => 'ai-risk-register', 'name' => 'X', 'email' => 'x@acme-bank.com', 'company' => 'Acme', 'terms_accepted_at' => now()]);
        $this->withCookies([TemplateDownloadRequest::KNOWN_COOKIE => (string) $unopened->id])
            ->post('/templates/ai-system-inventory/request', ['known' => '1', 'terms' => '1'])->assertSessionHasErrors(['name', 'email', 'company']);
    }

    public function test_change_details_opens_the_form_filled_with_the_known_details(): void
    {
        $first = $this->proveReader();
        $html = $this->withCookies([TemplateDownloadRequest::KNOWN_COOKIE => (string) $first->id])
            ->get(route('templates.show', ['slug' => 'ai-system-inventory', 'details' => 1]))->assertOk()->getContent();
        $this->assertStringContainsString('id="tr-company" name="company" value="Acme Bank"', $html);
    }

    public function test_a_signed_in_account_gets_the_form_filled_from_its_profile(): void
    {
        $user = User::factory()->create(['name' => 'Kailash Bohara', 'email' => 'kailash@acme-bank.com', 'organization_name' => 'Acme Bank']);
        $html = $this->actingAs($user)->get(route('templates.show', 'ai-system-inventory'))->assertOk()->getContent();
        $this->assertStringContainsString('name="email" type="email" value="kailash@acme-bank.com"', $html);
        $this->assertStringContainsString('name="name" value="Kailash Bohara"', $html);
    }

    public function test_subscribing_needs_the_terms_and_the_templates_extra_keeps_everything(): void
    {
        $this->post(route('subscribe.store'), ['email' => 'reader@acme-bank.com'])->assertSessionHasErrors('terms');
        $this->assertSame(0, Subscriber::count());

        $this->post(route('subscribe.store'), ['email' => 'reader@acme-bank.com', 'terms' => 1, 'topics' => ['templates']])->assertSessionHasNoErrors();
        $this->assertSame(['all', 'templates'], Subscriber::sole()->topics, 'ticking only the extra keeps the full digest');

        $html = $this->get(route('home'))->assertOk()->getContent();
        $this->assertStringContainsString('name="topics[]" value="templates"', $html, 'the compact form offers the related updates');
    }
}
