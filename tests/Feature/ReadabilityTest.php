<?php

namespace Tests\Feature;

use App\Models\ChangeEvent;
use App\Models\Obligation;
use App\Models\PolicyInstrument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Feedback of November 2026: text hard to read, pages busy. */
class ReadabilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('policy:import');
    }

    public function test_a_record_page_offers_copy_link_and_share_once(): void
    {
        $pages = [
            route('policies.show', PolicyInstrument::published()->firstOrFail()->slug),
            route('jurisdictions.show', 'germany'),
            Obligation::published()->firstOrFail()->url(),
            ChangeEvent::published()->firstOrFail()->url(),
        ];
        foreach ($pages as $url) {
            $html = $this->followingRedirects()->get($url)->assertOk()->getContent();
            $this->assertSame(1, substr_count($html, 'data-copy-link'), "{$url} shows Copy link once");
            $this->assertLessThanOrEqual(1, substr_count($html, 'data-share'), "{$url} shows Share at most once");
        }
    }

    public function test_the_public_type_is_the_system_font_and_small_text_is_at_least_13px(): void
    {
        $config = file_get_contents(base_path('tailwind.config.js'));
        $this->assertStringContainsString('sans: ["system-ui"', $config);
        $this->assertStringContainsString('xs: ["0.8125rem"', $config);
        $css = file_get_contents(resource_path('css/public.css'));
        $this->assertDoesNotMatchRegularExpression('/text-\[1[01]px\]/', $css, 'no 10 or 11px text in the shared components');
    }
}
