<?php

namespace Tests\Feature;

use App\Models\ChangeEvent;
use App\Models\ExternalRisk;
use App\Models\PolicyInstrument;
use App\Services\PolicyData\RecordBadge;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Findings from the November 2026 crawl against the claude-seo checklist. */
class ClaudeSeoAuditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('policy:import');
    }

    private function description(string $html): string
    {
        preg_match('#<meta name="description" content="([^"]*)"#', $html, $m);

        return html_entity_decode($m[1] ?? '', ENT_QUOTES);
    }

    public function test_risk_entries_from_one_passage_do_not_share_a_description(): void
    {
        $shared = 'Bad actors can also use generative AI tools to produce adaptable content designed to support a campaign.';
        $a = ExternalRisk::create(['ev_id' => '99.01.01', 'quick_ref' => 'Test2026', 'paper_title' => 'A test paper', 'level' => 'Risk Sub-Category', 'risk_category' => 'Misuse', 'risk_subcategory' => 'Scams', 'description' => $shared, 'domain' => '4', 'subdomain' => '4.3']);
        $b = ExternalRisk::create(['ev_id' => '99.01.02', 'quick_ref' => 'Test2026', 'paper_title' => 'A test paper', 'level' => 'Risk Sub-Category', 'risk_category' => 'Misuse', 'risk_subcategory' => 'Disinformation', 'description' => $shared, 'domain' => '4', 'subdomain' => '4.1']);

        $first = $this->description($this->get($a->url())->assertOk()->getContent());
        $second = $this->description($this->get($b->url())->assertOk()->getContent());
        $this->assertStringStartsWith('Scams: ', $first);
        $this->assertStringStartsWith('Disinformation: ', $second);
        $this->assertNotSame($first, $second);
    }

    public function test_the_changes_by_year_page_has_no_skipped_heading_level(): void
    {
        $year = (int) substr((string) ChangeEvent::published()->max('occurred_on'), 0, 4);
        $html = $this->get(route('changes.year', $year))->assertOk()->getContent();
        preg_match_all('#<h([1-6])\b#', $html, $m);
        $levels = array_map('intval', $m[1]);
        foreach (array_slice($levels, 1) as $i => $level) {
            $this->assertLessThanOrEqual($levels[$i] + 1, $level, 'a heading level is skipped: '.implode(',', $levels));
        }
        $this->assertStringContainsString('Every recorded change in '.$year, $html);
    }

    public function test_the_badge_image_reserves_its_width_and_height(): void
    {
        $policy = PolicyInstrument::published()->firstOrFail();
        $width = app(RecordBadge::class)->width($policy);
        $svg = $this->get(route('badges.policy', $policy->slug))->assertOk()->getContent();
        $this->assertStringStartsWith('<svg xmlns="http://www.w3.org/2000/svg" width="'.$width.'" height="20"', $svg);
        $html = $this->get(route('policies.show', $policy->slug))->assertOk()->getContent();
        $this->assertStringContainsString('src="'.route('badges.policy', $policy->slug).'" alt="', $html);
        $this->assertStringContainsString('width="'.$width.'" height="20" loading="lazy"', $html);
        $this->assertStringContainsString('width=&quot;'.$width.'&quot; height=&quot;20&quot;', $html, 'the copied snippet carries the size too');
    }
}
