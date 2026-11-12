<?php

namespace Tests\Feature;

use App\Models\ChangeEvent;
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
