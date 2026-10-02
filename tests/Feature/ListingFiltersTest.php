<?php

namespace Tests\Feature;

use App\Models\Obligation;
use App\Models\PolicyInstrument;
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
}
