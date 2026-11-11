<?php

namespace Tests\Feature;

use App\Models\Jurisdiction;
use App\Services\Hubs\HubCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A comparison page used to be linked only from the comparison index, so the two
 * country pages it compares never pointed to it. Each country page now links the
 * indexed comparisons it is part of, and links an on-demand comparison (which is
 * not indexed) only with nofollow.
 */
class ComparisonLinksTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->artisan('policy:import');
    }

    public function test_a_country_page_links_the_indexed_comparisons_it_is_part_of(): void
    {
        $eu = Jurisdiction::where('slug', 'eu')->firstOrFail();
        $comparisons = HubCatalog::comparisonsFor('eu');
        $this->assertNotEmpty($comparisons, 'the EU has indexed comparison pages');

        $html = $this->get($eu->url())->assertOk()->getContent();
        foreach ($comparisons as $url) {
            $this->assertStringContainsString('href="'.$url.'" class="text-brand-body hover:underline">', $html, $url);
        }
    }

    public function test_every_followed_comparison_link_is_an_indexed_page(): void
    {
        foreach (['eu', 'us', 'uk', 'japan'] as $slug) {
            $j = Jurisdiction::where('slug', $slug)->first();
            if (! $j) {
                continue;
            }
            $html = $this->get($j->url())->assertOk()->getContent();
            preg_match_all('#href="(http://localhost[^"]*/compare/[^"]+)" class="text-brand-body hover:underline">#', $html, $m);
            foreach ($m[1] as $url) {
                $page = $this->get(parse_url($url, PHP_URL_PATH))->assertOk()->getContent();
                $this->assertStringContainsString('<meta name="robots" content="index', $page, $url.' is linked without nofollow, so it must be indexable');
            }
            preg_match_all('#href="[^"]*/compare\?j=[^"]*" class="text-brand-body hover:underline"( rel="nofollow")?>#', $html, $q);
            foreach ($q[1] as $rel) {
                $this->assertSame(' rel="nofollow"', $rel, 'an on-demand comparison is linked nofollow');
            }
        }
    }
}
