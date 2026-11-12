<?php

namespace Tests\Feature;

use App\Models\ChangeEvent;
use App\Models\Jurisdiction;
use App\Models\Obligation;
use App\Models\PolicyInstrument;
use App\Services\Social\SocialCard;
use App\Services\Voices\VoiceCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Figures that replace text on the main listings, the sticky header, and the share card
 * of an AI Voices profile. Each figure is drawn from the same counts as the page and each
 * part of it is a link into the listing.
 */
class PageFiguresTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('policy:import');
        Storage::fake(config('social.cache_disk'));
    }

    public function test_the_policy_listing_shows_its_status_split_with_a_link_per_status(): void
    {
        $html = $this->get(route('policies.index'))->assertOk()->getContent();
        $figure = substr($html, strpos($html, 'data-facet-breakdown'), 12000);
        $inForce = PolicyInstrument::published()->where('status', 'in_force')->count();
        $this->assertGreaterThan(0, $inForce);
        $this->assertStringContainsString('In force: '.$inForce, $figure);
        $this->assertStringContainsString('status=in_force', $figure);
        $this->assertStringContainsString(PolicyInstrument::published()->where('is_binding', true)->count().' legally binding', $figure);

        // A later page is about the records, not the overview; the figure is on the first only.
        $this->assertStringNotContainsString('data-facet-breakdown', $this->get(route('policies.index', ['page' => 2]))->getContent());
    }

    public function test_the_obligation_listing_shows_categories_largest_first(): void
    {
        $html = $this->get(route('obligations.index'))->assertOk()->getContent();
        $figure = substr($html, strpos($html, 'data-facet-breakdown'), 12000);
        $top = Obligation::published()->selectRaw('category, COUNT(*) as n')->groupBy('category')->orderByDesc('n')->first();
        $this->assertStringContainsString('category='.$top->category, $figure);
        $this->assertSame(1, substr_count($html, 'data-facet-breakdown'));
    }

    public function test_the_change_log_charts_twelve_months_and_links_each_month_with_changes(): void
    {
        $html = $this->get(route('changes.index'))->assertOk()->getContent();
        $chart = substr($html, strpos($html, 'data-month-activity'), 20000);
        $total = ChangeEvent::published()->whereDate('occurred_on', '>=', now()->startOfMonth()->subMonths(11)->toDateString())->whereDate('occurred_on', '<=', now()->toDateString())->count();
        $this->assertStringContainsString(number_format($total).' recorded', $chart);
        $this->assertSame(12, substr_count(substr($chart, strpos($chart, '<ol'), strpos($chart, '</ol>') - strpos($chart, '<ol')), '<li'));
        $latest = ChangeEvent::published()->whereDate('occurred_on', '<=', now()->toDateString())->orderByDesc('occurred_on')->first();
        $this->assertStringContainsString(route('updates.month', substr((string) $latest->occurred_on, 0, 7)), $chart);
    }

    public function test_an_obligation_page_draws_the_chain_from_law_to_evidence_with_its_own_counts(): void
    {
        $o = Obligation::published()->has('evidenceArtifacts')->with(['policyInstrument', 'evidenceArtifacts', 'terms'])->firstOrFail();
        $html = $this->get(route('obligations.show', $o->slug))->assertOk()->getContent();
        $chain = substr($html, strpos($html, 'data-duty-chain'), 9000);
        $chain = substr($chain, 0, strpos($chain, '</section>'));

        $this->assertSame(5, substr_count($chain, '<li'), 'law, who, controls, evidence, templates');
        $this->assertStringContainsString('href="'.$o->policyInstrument->url().'"', $chain);
        $n = $o->evidenceArtifacts->count();
        $this->assertStringContainsString($n.' '.($n === 1 ? 'example' : 'examples'), $chain);
        $this->assertStringContainsString('href="#evidence-heading"', $chain);
        $this->assertStringContainsString('id="evidence-heading"', $html, 'the link has a target');
    }

    public function test_the_methodology_page_shows_the_life_of_a_record_and_the_source_ladder(): void
    {
        $html = $this->get(route('methodology'))->assertOk()->getContent();
        $flow = substr($html, strpos($html, 'data-record-lifecycle'), 9000);
        $flow = substr($flow, 0, strpos($flow, '</section>'));
        $this->assertSame(5, substr_count($flow, '<li'));
        foreach (['Official source', 'Record written', 'Source-linked', 'Verified', 'Re-checked'] as $step) {
            $this->assertStringContainsString($step, $flow);
        }
        $this->assertStringContainsString(route('verification'), $flow);
        $ladder = substr($html, strpos($html, 'data-source-ladder'), 3000);
        $this->assertSame(4, substr_count(substr($ladder, 0, strpos($ladder, '</ol>')), '<li'), 'four source tiers');
    }

    public function test_a_jurisdiction_page_draws_its_binding_split_from_the_instruments_it_lists(): void
    {
        $j = Jurisdiction::published()->withCount(['policyInstruments as n' => fn ($q) => $q->published()])->orderByDesc('n')->firstOrFail();
        $policies = $j->policyInstruments()->published()->get();
        $binding = $policies->where('is_binding', true)->count();
        $html = $this->get($j->url())->assertOk()->getContent();
        $mix = substr($html, strpos($html, 'data-jurisdiction-mix'), 6000);
        $mix = substr($mix, 0, strpos($mix, '</section>'));

        $this->assertStringContainsString($policies->count().' instruments', $mix);
        $this->assertStringContainsString($binding > 0 ? $binding.' legally binding' : 'No binding AI law on record', $mix);
        $this->assertStringContainsString('jurisdiction='.$j->slug, $mix);

        // One instrument has no split to draw.
        $single = Jurisdiction::published()->whereHas('policyInstruments', fn ($q) => $q->published(), '=', 1)->first();
        if ($single) {
            $this->assertStringNotContainsString('data-jurisdiction-mix', $this->get($single->url())->getContent());
        }
    }

    public function test_the_header_stays_on_screen_and_offers_search_at_every_width(): void
    {
        $html = $this->get(route('policies.index'))->getContent();
        $this->assertMatchesRegularExpression('#<header class="sticky top-0 [^"]*" data-site-header>#', $html);
        // The search box only where it fits beside the seven menus; an icon below that.
        $this->assertStringContainsString('role="search" class="hidden 2xl:block"', $html);
        $this->assertStringContainsString('hidden sm:inline-flex 2xl:hidden btn-secondary', $html);
    }

    public function test_a_voice_profile_shares_its_own_card(): void
    {
        $v = VoiceCatalog::all()->first();
        $html = $this->get(route('voices.show', $v['slug']))->assertOk()->getContent();
        $this->assertStringContainsString(route('social.card', ['kind' => 'voice', 'slug' => $v['slug']]), $html);
        $this->get(route('social.card', ['kind' => 'voice', 'slug' => 'no-such-person']))->assertNotFound();

        if (! app(SocialCard::class)->available()) {
            $this->markTestSkipped('no TrueType font on this host');
        }
        $png = $this->get(route('social.card', ['kind' => 'voice', 'slug' => $v['slug']]))->assertOk()->assertHeader('Content-Type', 'image/png')->getContent();
        $this->assertSame([1200, 630], array_slice(getimagesizefromstring($png), 0, 2));
    }
}
