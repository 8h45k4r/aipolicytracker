<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The shared admin shell: messages as toasts, the confirmation dialog, the palette and self-hosted fonts. */
class AdminInterfaceTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        config(['aipolicytracker.admin_emails' => ['owner@example.org']]);

        return User::factory()->create(['email' => 'owner@example.org', 'email_verified_at' => now()]);
    }

    public function test_the_admin_shell_carries_the_dialogs_and_no_third_party_fonts(): void
    {
        $html = $this->actingAs($this->owner())->get(route('backend.admin.dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('id="adm-confirm"', $html, 'confirmation dialog');
        $this->assertStringContainsString('id="adm-palette"', $html, 'command palette');
        $this->assertStringContainsString('data-palette-open', $html);
        $this->assertStringContainsString('data-toasts', $html);
        $this->assertStringNotContainsString('fonts.bunny.net', $html);
    }

    public function test_messages_render_as_toasts_without_javascript(): void
    {
        $owner = $this->owner();
        $this->actingAs($owner)->withSession(['success' => 'Saved the settings.'])->get(route('backend.admin.dashboard'))
            ->assertOk()->assertSee('data-toast="success"', false)->assertSee('Saved the settings.');
        $this->actingAs($owner)->withSession(['error' => 'Could not reach the provider.'])->get(route('backend.admin.dashboard'))
            ->assertOk()->assertSee('data-toast="error"', false)->assertSee('Could not reach the provider.');
    }

    public function test_no_page_or_policy_loads_fonts_from_a_third_party(): void
    {
        $response = $this->get('/');
        $this->assertStringNotContainsString('fonts.bunny.net', (string) $response->headers->get('Content-Security-Policy'));
        $this->get('/login')->assertOk()->assertDontSee('fonts.bunny.net');
    }
}
