<?php

namespace Tests\Feature;

use App\Models\Jurisdiction;
use App\Models\PolicyInstrument;
use App\Services\Hubs\HubCatalog;
use App\Support\PageTitle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P6: country hubs at /ai-regulation-<country> (the jurisdiction page at its
 * canonical address, with the old address redirecting), regional hubs, the
 * thin guard, and config-driven /compare/<a>-vs-<b> pages with one canonical
 * order per pair.
 */
class CountryHubsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('policy:import');
    }

    public function test_a_hub_country_has_one_address_and_the_old_one_redirects(): void
    {
        $japan = Jurisdiction::where('slug', 'japan')->firstOrFail();
        $this->assertSame(route('hubs.show', 'ai-regulation-japan'), $japan->url());
        $this->get('/jurisdictions/japan')->assertStatus(301)->assertRedirect('/ai-regulation-japan');

        $html = $this->get('/ai-regulation-japan')->assertOk()->getContent();
        $head = substr($html, 0, strpos($html, '</head>'));
        $this->assertSame(1, preg_match_all('#<h1\b#', $html));
        $this->assertStringContainsString('<title>'.e(PageTitle::jurisdiction($japan)), $head);
        $this->assertStringContainsString('<link rel="canonical" href="'.route('hubs.show', 'ai-regulation-japan').'"', $head);
        $this->assertStringContainsString('data-answer-box', $html, 'the answer box leads');
        $this->assertStringContainsString('Instruments at a glance', $html);
        $this->assertStringContainsString('id="timeline-heading"', $html);
        $this->assertStringContainsString('Upcoming deadlines', $html);
        $this->assertStringContainsString(route('updates.jurisdiction.feed', 'japan'), $html, 'the updates feed is linked');
        $this->assertStringContainsString('"@type":"FAQPage"', $html);
        $this->assertStringContainsString('"@type":"BreadcrumbList"', $html);
        $this->assertStringContainsString(HubCatalog::regionUrl('asia'), $html, 'the breadcrumb climbs to the regional hub');
        // Listing pages follow the model's url(), so nothing links the old address.
        $this->assertStringNotContainsString('href="'.url('/jurisdictions/japan').'"', $this->get('/jurisdictions')->assertOk()->getContent());
        $this->assertStringContainsString(route('hubs.show', 'ai-regulation-japan'), $this->get('/sitemap-jurisdictions.xml')->getContent());
        $this->assertStringNotContainsString(url('/jurisdictions/japan').'<', $this->get('/sitemap-jurisdictions.xml')->getContent());
        // A country without a hub keeps its address.
        $this->get('/jurisdictions/india')->assertOk();
    }

    public function test_the_thin_guard_indexes_a_hub_only_with_two_sourced_instruments_or_a_verified_strategy(): void
    {
        $ghana = Jurisdiction::where('slug', 'ghana')->firstOrFail();
        $this->assertSame(1, PolicyInstrument::published()->where('jurisdiction_id', $ghana->id)->whereNotNull('official_source_url')->count(), 'the fixture: one sourced instrument');
        $this->assertFalse($ghana->isPageIndexable());
        $this->assertStringContainsString('name="robots" content="noindex', $this->get('/ai-regulation-ghana')->assertOk()->getContent());
        $this->assertStringNotContainsString(route('hubs.show', 'ai-regulation-ghana'), $this->get('/sitemap-jurisdictions.xml')->getContent());

        // One verified strategy is enough.
        PolicyInstrument::published()->where('jurisdiction_id', $ghana->id)->first()->update(['instrument_type' => 'strategy', 'review_status' => 'verified']);
        $this->assertTrue($ghana->fresh()->isPageIndexable());
        $this->assertStringContainsString('name="robots" content="index', $this->get('/ai-regulation-ghana')->assertOk()->getContent());
    }

    public function test_a_regional_hub_is_computed_from_the_region_and_guarded_by_its_country_count(): void
    {
        $html = $this->get('/ai-regulation-asia')->assertOk()->getContent();
        $this->assertSame(1, preg_match_all('#<h1\b#', $html));
        $this->assertStringContainsString('<title>'.e(PageTitle::regionHub('Asia')), $html);
        $this->assertMatchesRegularExpression('/Asia has \d+ recorded jurisdictions with AI policy on record/', $html);
        $this->assertStringContainsString('Country by country', $html);
        $this->assertStringContainsString(route('hubs.show', 'ai-regulation-japan'), $html);
        $this->assertStringContainsString('"@type":"CollectionPage"', $html);
        $this->assertStringContainsString('name="robots" content="index', $html);
        $this->assertStringContainsString(HubCatalog::regionUrl('asia'), $this->get('/sitemap-resources.xml')->getContent());
        $this->get('/ai-regulation-atlantis')->assertNotFound();

        // Not enough indexable countries: served, not indexed, not listed.
        config(['hubs.min_region_countries' => 1000]);
        $this->assertStringContainsString('name="robots" content="noindex', $this->get('/ai-regulation-asia')->assertOk()->getContent());
        $this->assertStringNotContainsString(HubCatalog::regionUrl('asia'), $this->get('/sitemap-resources.xml')->getContent());
    }

    public function test_compare_pairs_have_one_canonical_order_an_overlap_matrix_and_a_whats_left_list(): void
    {
        $this->assertSame('eu-vs-japan', HubCatalog::pairSlug('japan', 'eu'));
        $this->get('/compare/japan-vs-eu')->assertStatus(301)->assertRedirect('/compare/eu-vs-japan');
        $this->get('/compare/eu-vs-india')->assertStatus(301)->assertRedirect(route('compare.show', 'eu-vs-india-ai-regulation'), 'a pair a curated comparison covers goes to the curated page');
        $this->get('/compare/eu-vs-eu')->assertNotFound();
        $this->get('/compare/eu-vs-atlantis')->assertNotFound();

        $html = $this->get('/compare/eu-vs-japan')->assertOk()->getContent();
        $head = substr($html, 0, strpos($html, '</head>'));
        $eu = Jurisdiction::where('slug', 'eu')->firstOrFail();
        $japan = Jurisdiction::where('slug', 'japan')->firstOrFail();
        $this->assertSame(1, preg_match_all('#<h1\b#', $html));
        $this->assertStringContainsString('<title>'.e(PageTitle::comparePair($eu, $japan)), $head);
        $this->assertStringContainsString('<link rel="canonical" href="'.route('compare.show', 'eu-vs-japan').'"', $head);
        $this->assertStringContainsString('name="robots" content="index', $head);
        $this->assertStringContainsString('Obligation overlap', $html);
        $this->assertStringContainsString('what is left for', $html);
        $this->assertStringContainsString('"@type":"FAQPage"', $html);
        $this->assertMatchesRegularExpression('/The European Union records \d+ AI policy instruments? \(\d+ binding\)/', $html, 'the intro is computed from counts');

        // A pair outside the listed set is served but not indexed or listed.
        $other = $this->get('/compare/albania-vs-zambia')->assertOk()->getContent();
        $this->assertStringContainsString('name="robots" content="noindex', $other);
        $sitemap = $this->get('/sitemap-resources.xml')->getContent();
        $this->assertStringContainsString(route('compare.show', 'eu-vs-japan'), $sitemap);
        $this->assertStringNotContainsString('albania-vs-zambia', $sitemap);
        $this->assertStringNotContainsString(route('compare.show', 'eu-vs-india').'<', $sitemap, 'a curated pair is listed once, under its curated address');

        // The curated pages gain the same sections.
        $curated = $this->get('/compare/eu-vs-india-ai-regulation')->assertOk()->getContent();
        $this->assertStringContainsString('Obligation overlap', $curated);
        $this->assertStringContainsString(route('compare.show', 'eu-vs-japan'), $this->get('/compare')->assertOk()->getContent());
    }

    public function test_native_language_names_come_from_the_record_and_are_never_invented(): void
    {
        $japan = Jurisdiction::where('slug', 'japan')->firstOrFail();
        $policy = PolicyInstrument::published()->where('jurisdiction_id', $japan->id)->firstOrFail();
        $this->assertNull($policy->title_native, 'the fixture carries no native title, so none is shown');
        $this->assertStringContainsString('a blank means the source is in English or the name is not yet recorded', $this->get('/ai-regulation-japan')->getContent());

        $policy->update(['title_native' => '人工知能関連技術の研究開発及び活用の推進に関する法律']);
        $html = $this->get('/ai-regulation-japan')->assertOk()->getContent();
        $this->assertStringContainsString('人工知能関連技術の研究開発及び活用の推進に関する法律', $html);
    }
}
