<?php

namespace Tests\Feature;

use App\Models\Obligation;
use App\Models\PolicyInstrument;
use App\Models\TaxonomyTerm;
use App\Services\PolicyData\PolicyCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListingFiltersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('policy:import');
    }

    public function test_active_filters_are_named_chips_that_each_remove_one_filter(): void
    {
        $html = $this->get(route('policies.index', ['jurisdiction' => 'eu', 'binding' => 'yes']))->assertOk()->getContent();

        $this->assertStringContainsString('data-active-filters', $html);
        $this->assertStringContainsString('Jurisdiction:</span> European Union', $html, 'the chip names the jurisdiction, not its slug');
        $this->assertStringContainsString('Binding:</span> Legal requirements only', $html);
        $this->assertStringContainsString('href="'.e(route('policies.index', ['binding' => 'yes'])).'"', $html, 'removing the jurisdiction keeps the other filter');
        $this->assertStringContainsString('>Clear all</a>', $html);

        $total = PolicyInstrument::published()->count();
        $matching = PolicyInstrument::published()->where('is_binding', true)->whereHas('jurisdiction', fn ($j) => $j->where('slug', 'eu'))->count();
        $this->assertMatchesRegularExpression('#>'.$matching.'</span>\s+of '.number_format($total).' instruments match#', $html);

        $this->get(route('policies.index'))->assertOk()->assertDontSee('data-active-filters', false);
    }

    public function test_each_filter_option_counts_the_results_it_would_give_with_the_other_filters(): void
    {
        $html = $this->get(route('policies.index', ['binding' => 'yes']))->assertOk()->getContent();
        $eu = PolicyInstrument::published()->where('is_binding', true)->whereHas('jurisdiction', fn ($j) => $j->where('slug', 'eu'))->count();
        $this->assertMatchesRegularExpression('#<option value="eu"[^>]*>European Union \('.$eu.'\)</option>#', $html);

        // The chosen filter's own options are counted without it, so the alternatives stay visible.
        $voluntary = PolicyInstrument::published()->where('is_binding', false)->count();
        $this->assertStringContainsString('Voluntary guidance only ('.$voluntary.')', $html);

        $html = $this->get(route('obligations.index', ['jurisdiction' => 'eu']))->assertOk()->getContent();
        $category = Obligation::published()->whereHas('policyInstrument.jurisdiction', fn ($j) => $j->where('slug', 'eu'))->selectRaw('category, COUNT(*) as n')->groupBy('category')->orderByDesc('n')->first();
        $this->assertMatchesRegularExpression('#<option value="'.$category->category.'"[^>]*>[^<]+ \('.$category->n.'\)</option>#', $html);
    }

    public function test_lists_carry_a_compact_verification_mark_with_the_full_line_on_hover(): void
    {
        $verified = PolicyInstrument::published()->where('review_status', 'verified')->whereNotNull('last_verified_at')->whereNotNull('reviewed_by')->firstOrFail();
        $html = $this->get(route('policies.index', ['jurisdiction' => $verified->jurisdiction->slug]))->assertOk()->getContent();

        $this->assertStringNotContainsString('Verified against the official source '.$verified->last_verified_at->format('j M Y').' · reviewed by', strip_tags($html), 'the long line is for the record page');
        $this->assertMatchesRegularExpression('#title="Verified against the official source '.preg_quote($verified->last_verified_at->format('j M Y'), '#').' · reviewed by '.preg_quote(e($verified->reviewed_by), '#').'\.[^"]*" data-verified-mark>\s*<svg#', $html);
        $this->assertStringContainsString('Verified <time datetime="'.$verified->last_verified_at->toDateString().'">', $html);

        // The record page keeps the full line and the reviewer link.
        $this->get($verified->url())->assertOk()->assertSee('reviewed by <a href="'.route('reviewers').'"', false);
    }

    public function test_the_policy_explorer_opens_on_binding_law_ordered_by_title(): void
    {
        $html = $this->get(route('policies.index'))->assertOk()->getContent();

        $expected = PolicyInstrument::published()->orderByDesc('is_binding')->orderBy('title')->limit(5)->get();
        $this->assertTrue((bool) $expected->first()->is_binding, 'the first record is binding law');
        $offsets = $expected->map(fn ($p) => strpos($html, 'href="'.$p->url().'"'))->all();
        $this->assertNotContains(false, $offsets, 'the first five binding records are on page one');
        $sorted = $offsets;
        sort($sorted);
        $this->assertSame($sorted, $offsets, 'in title order');
        $this->assertMatchesRegularExpression('#<option value="binding"\s+selected#', $html, 'the sort control says so');

        // The API keeps "updated" as its default order.
        $orders = app(PolicyCatalog::class)->policyQuery([])->getQuery()->orders;
        $this->assertSame([['column' => 'policy_instruments.updated_at', 'direction' => 'desc']], $orders);
        $this->getJson('/api/v1/policies?sort=binding')->assertOk();
    }

    public function test_obligation_cards_name_their_category_not_its_key(): void
    {
        $html = $this->get(route('obligations.index'))->assertOk()->getContent();

        preg_match_all('#data-obligation-category="([a-z_]+)">([^<]+)</span>#', $html, $m, PREG_SET_ORDER);
        $this->assertNotEmpty($m);
        $names = TaxonomyTerm::where('taxonomy', 'obligation_category')->pluck('name', 'slug');
        foreach ($m as [, $slug, $label]) {
            $this->assertSame(e($names[$slug]), $label, "{$slug} shows its display name");
        }
    }

    public function test_listing_pagination_and_headings_pass_the_accessibility_rules(): void
    {
        foreach ([route('policies.index'), route('obligations.index'), route('updates.index')] as $url) {
            $html = $this->get($url)->assertOk()->getContent();
            // aria-label is not allowed on a generic span (axe aria-prohibited-attr).
            $this->assertDoesNotMatchRegularExpression('#<span[^>]*aria-disabled="true"[^>]*aria-label=#', $html, $url);
            // One pagination landmark, not a nav wrapped in a nav.
            $this->assertStringNotContainsString('aria-label="Pagination"><nav', $html, $url);
        }
        // Result cards are h3s under an h2, not straight after the h1.
        $html = $this->get(route('policies.index'))->getContent();
        $this->assertLessThan(strpos($html, '<h3'), strpos($html, '<h2 class="sr-only">Results</h2>'));
    }
}
