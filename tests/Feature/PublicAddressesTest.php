<?php

namespace Tests\Feature;

use App\Providers\AppSettingsServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Every address the site prints is on the project's own domain. */
class PublicAddressesTest extends TestCase
{
    use RefreshDatabase;

    private function boot(array $config): void
    {
        config($config);
        (new AppSettingsServiceProvider($this->app))->boot();
    }

    public function test_an_address_on_another_domain_is_left_off_every_page(): void
    {
        $this->boot([
            'aipolicytracker.public_email_domain' => 'aipolicytracker.org',
            'aipolicytracker.contact_email' => 'someone@mail.example',
            'aipolicytracker.contact_emails' => ['someone@mail.example', 'info@aipolicytracker.org'],
            'legal.privacy_contact' => 'someone@mail.example',
        ]);

        $this->assertSame('info@aipolicytracker.org', config('aipolicytracker.contact_email'));
        $this->assertSame(['info@aipolicytracker.org'], config('aipolicytracker.contact_emails'));
        $this->assertNull(config('legal.privacy_contact'));

        foreach (['/about', '/privacy', '/terms', '/llms.txt', '/.well-known/security.txt', '/'] as $url) {
            $response = $this->get($url);
            $this->assertContains($response->status(), [200], $url);
            $this->assertStringNotContainsString('someone@mail.example', $response->getContent(), $url);
        }
        $this->get('/privacy')->assertSee('info@aipolicytracker.org', false);
    }

    public function test_addresses_on_the_domain_are_kept_and_the_rule_can_be_switched_off(): void
    {
        $this->boot([
            'aipolicytracker.public_email_domain' => 'aipolicytracker.org',
            'aipolicytracker.contact_email' => 'Info@AIPolicyTracker.org',
            'aipolicytracker.contact_emails' => ['press@aipolicytracker.org'],
            'legal.privacy_contact' => 'privacy@aipolicytracker.org',
        ]);
        $this->assertSame('Info@AIPolicyTracker.org', config('aipolicytracker.contact_email'));
        $this->assertSame(['press@aipolicytracker.org'], config('aipolicytracker.contact_emails'));
        $this->assertSame('privacy@aipolicytracker.org', config('legal.privacy_contact'));

        // A look-alike domain is not the project's domain.
        $this->boot(['aipolicytracker.contact_emails' => ['x@notaipolicytracker.org.example', 'y@evil-aipolicytracker.org']]);
        $this->assertSame(['Info@AIPolicyTracker.org'], config('aipolicytracker.contact_emails'));

        $this->boot(['aipolicytracker.public_email_domain' => '', 'aipolicytracker.contact_emails' => ['someone@mail.example']]);
        $this->assertSame(['someone@mail.example'], config('aipolicytracker.contact_emails'));
    }
}
