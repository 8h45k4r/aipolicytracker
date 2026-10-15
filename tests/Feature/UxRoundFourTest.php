<?php

namespace Tests\Feature;

use App\Support\Navigation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Fourth UX and accessibility round: nav semantics, current section, link labels, landmarks. */
class UxRoundFourTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('policy:import');
    }

    public function test_the_desktop_nav_does_not_claim_a_popup_menu(): void
    {
        // A <summary> already announces as a disclosure; aria-haspopup promised arrow-key menu behaviour.
        $html = $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('data-nav-group', $html);
        $this->assertStringNotContainsString('aria-haspopup', $html);
    }

    public function test_a_record_page_highlights_its_section_by_url_prefix(): void
    {
        // /policies/eu-ai-act is also linked from the Jurisdictions menu; the prefix decides.
        $html = $this->get('/policies/eu-ai-act')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('#nav-link-active">\s*Policies#', $html);
        $this->assertSame(1, substr_count($html, 'nav-link-active"'), 'Exactly one group is current.');

        $this->assertSame('jurisdictions', Navigation::currentGroup('ai-regulation-japan', url('/ai-regulation-japan')));
        $this->assertSame('jurisdictions', Navigation::currentGroup('jurisdictions/eu', url('/jurisdictions/eu')));
        $this->assertSame('risk', Navigation::currentGroup('ai-risk/incidents/browse', url('/ai-risk/incidents/browse')));
        $this->assertSame('data', Navigation::currentGroup('explore/data', url('/explore/data')));
        $this->assertNull(Navigation::currentGroup('', url('/')));
    }

    public function test_download_links_are_named_by_format(): void
    {
        $html = $this->get('/policies/eu-ai-act')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('#href="'.preg_quote(route('policies.json', 'eu-ai-act'), '#').'"[^>]*>JSON</a>#', $html);
        $this->assertMatchesRegularExpression('#href="'.preg_quote(route('policies.context', 'eu-ai-act'), '#').'"[^>]*>Markdown</a>#', $html);
        $this->assertStringNotContainsString('>Context file<', $html);
        $this->assertStringNotContainsString('>JSON record<', $html);
    }

    public function test_the_subscribe_page_names_the_digest_box_as_it_reads(): void
    {
        $this->get('/subscribe')->assertOk()
            ->assertSee('Weekly digest for …')
            ->assertSee('Get the digest')
            ->assertDontSee('has a "Follow" box', false);
    }

    public function test_the_auth_side_panel_is_a_labelled_landmark(): void
    {
        foreach (['/login', '/register', '/forgot-password'] as $url) {
            $html = $this->get($url)->assertOk()->getContent();
            $this->assertStringContainsString('<aside aria-label="About your account"', $html, $url);
            $this->assertStringNotContainsString('<section class="hidden lg:flex', $html, $url);
        }
    }
}
