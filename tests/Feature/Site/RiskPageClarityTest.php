<?php

namespace Tests\Feature\Site;

use App\Http\Controllers\Site\RiskController;
use App\Models\ExternalIncident;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * /ai-risk opened on an editorial H1 and a "Harm is rising" heading above a tile
 * that read −24% against the previous 12 months. The heading is now stated from
 * the same figure as the tile, and the H1 says what the page is.
 */
class RiskPageClarityTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_trend_follows_the_sign_of_the_growth_figure(): void
    {
        $this->assertSame('rising', RiskController::trend(24));
        $this->assertSame('falling', RiskController::trend(-24));
        $this->assertSame('flat', RiskController::trend(RiskController::TREND_FLAT_WITHIN - 1));
        $this->assertSame('flat', RiskController::trend(-(RiskController::TREND_FLAT_WITHIN - 1)));
        $this->assertSame('unknown', RiskController::trend(null));

        $this->assertSame('Recorded incidents are falling: down 24% on the previous 12 months', RiskController::trendHeading(-24));
        $this->assertSame('Recorded incidents are rising: up 24% on the previous 12 months', RiskController::trendHeading(24));
        $this->assertSame('Recorded incidents over time', RiskController::trendHeading(null));
    }

    public function test_the_page_has_a_factual_h1_one_eyebrow_and_a_heading_that_agrees_with_the_tile(): void
    {
        // Fewer incidents in the last 12 months (2) than in the 12 before (4): falling, −50%.
        $rows = [];
        foreach ([2, 3] as $i => $months) {
            $rows[] = $this->incident(100 + $i, now()->subMonths($months));
        }
        foreach ([14, 15, 16, 17] as $i => $months) {
            $rows[] = $this->incident(200 + $i, now()->subMonths($months));
        }
        DB::table('external_incidents')->insert($rows);
        $this->assertSame(6, ExternalIncident::count());

        $html = $this->get('/ai-risk')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('#<h1[^>]*>AI risk: recorded incidents, the risk taxonomy and the policies that respond</h1>#', $html);
        $this->assertStringNotContainsString('great responsibility', $html);
        $this->assertSame(1, substr_count($html, '<p class="eyebrow mt-3">'), 'one eyebrow above the H1');
        $this->assertStringNotContainsString('AI risk, evidenced', $html);

        $this->assertStringContainsString('-50% vs previous 12 months', $html, 'the tile');
        $this->assertStringContainsString('data-trend="falling"', $html);
        $this->assertStringContainsString('Recorded incidents are falling: down 50% on the previous 12 months', $html);
        $this->assertStringNotContainsString('Harm is rising', $html);
    }

    public function test_chart_data_tables_sit_in_a_visually_hidden_div_so_they_cannot_widen_the_page(): void
    {
        $bar = Blade::render('<x-site.bar-chart :series="[\'A\' => 1, \'B\' => 2]" title="Bars" />');
        $timeline = Blade::render('<x-site.timeline-chart :series="[2024 => 3, 2025 => 5]" title="Per year" />');
        $stacked = Blade::render('<x-site.stacked-chart :series="[\'A\' => [\'x\' => 1, \'y\' => 2]]" :keys="[\'x\', \'y\']" title="Stacked" />');

        foreach (['bar' => $bar, 'timeline' => $timeline, 'stacked' => $stacked] as $name => $html) {
            // A table ignores width; a div clipped by sr-only does not.
            $this->assertStringNotContainsString('<table class="sr-only"', $html, $name);
            $this->assertMatchesRegularExpression('#<div class="sr-only"><table>#', $html, $name);
        }
        // The timeline's bars are links, so the SVG is a group, not an image with focusable content.
        $this->assertStringNotContainsString('role="img"', $timeline);
        $this->assertMatchesRegularExpression('#<svg[^>]*role="group"#', $timeline);
        $this->assertMatchesRegularExpression('#<a href="[^"]+" aria-label="2025: 5 incidents\. Browse them">#', $timeline);
    }

    public function test_attribution_landmarks_carry_distinct_names(): void
    {
        $html = $this->get('/ai-risk')->assertOk()->getContent();
        preg_match_all('#<aside[^>]*aria-label="(Data attribution[^"]*)"#', $html, $m);
        $this->assertGreaterThanOrEqual(2, count($m[1]));
        $this->assertSame(count($m[1]), count(array_unique($m[1])), 'each attribution landmark has its own name');
    }

    /** @return array<string, mixed> */
    private function incident(int $id, CarbonInterface $on): array
    {
        return ['incident_id' => $id, 'occurred_on' => $on->toDateString(), 'year' => $on->year, 'title' => 'Incident '.$id, 'report_count' => 1, 'created_at' => now(), 'updated_at' => now()];
    }
}
