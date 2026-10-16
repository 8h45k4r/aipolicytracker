<?php

namespace Tests\Feature;

use App\Models\ExternalRisk;
use App\Models\Jurisdiction;
use App\Models\TransitionMeasure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Risks, frameworks and transition measures are searchable, not only laws, duties and incidents. */
class SearchCoverageTest extends TestCase
{
    use RefreshDatabase;

    private function risk(string $id, string $subcategory, string $description): void
    {
        ExternalRisk::forceCreate(['ev_id' => $id, 'level' => 'Risk Sub-Category', 'domain' => 1, 'subdomain' => '1.1', 'risk_category' => 'Discrimination and toxicity', 'risk_subcategory' => $subcategory, 'description' => $description, 'quick_ref' => 'mit-'.$id, 'paper_title' => 'Paper '.$id]);
    }

    public function test_risk_entries_are_found_by_their_subcategory_and_description(): void
    {
        $this->risk('01.01.00', 'Facial recognition misidentifies people', 'Systems that match faces can misidentify people of some groups more often.');
        $this->risk('01.02.00', 'Unrelated rule', 'Nothing about the query here.');

        $html = $this->get(route('search', ['q' => 'facial recognition']))->assertOk()->getContent();

        $this->assertStringContainsString('>Risks <span', $html);
        $this->assertStringContainsString('Facial recognition misidentifies people', $html);
        $this->assertStringNotContainsString('Unrelated rule', $html);

        $this->get(route('search', ['q' => 'misidentifies']))->assertOk()->assertSee('Facial recognition misidentifies people', false);
    }

    public function test_risk_results_report_the_full_count_not_the_shown_page(): void
    {
        for ($i = 1; $i <= 8; $i++) {
            $this->risk(sprintf('02.%02d.00', $i), "Bias variant {$i}", 'A description about bias in outputs.');
        }

        $html = $this->get(route('search', ['q' => 'bias']))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/>Risks <span[^>]*>\s*8\s*<\/span>/', $html);
        $this->assertStringContainsString(route('risk.risks', ['q' => 'bias']), $html, 'the group links to the full filtered list');
    }

    public function test_frameworks_and_standards_are_found_by_name(): void
    {
        $html = $this->get(route('search', ['q' => 'ISO/IEC 42001']))->assertOk()->getContent();

        $this->assertStringContainsString('>Frameworks <span', $html);
        $this->assertStringContainsString(route('frameworks.show', 'iso-42001'), $html);
    }

    public function test_only_published_transition_measures_are_searchable(): void
    {
        $jurisdiction = Jurisdiction::forceCreate(['slug' => 'testland', 'name' => 'Testland', 'jurisdiction_type' => 'country', 'published_at' => now()]);
        $base = ['jurisdiction_id' => $jurisdiction->id, 'measure_type' => 'retraining', 'status' => 'proposed', 'summary' => 'Funds retraining for workers displaced by automation.'];
        TransitionMeasure::forceCreate($base + ['slug' => 'public-retraining', 'title' => 'Public retraining fund', 'published_at' => now()]);
        TransitionMeasure::forceCreate($base + ['slug' => 'draft-retraining', 'title' => 'Draft retraining plan', 'published_at' => null]);

        $html = $this->get(route('search', ['q' => 'retraining']))->assertOk()->getContent();

        $this->assertStringContainsString('>Transition measures <span', $html);
        $this->assertStringContainsString('Public retraining fund', $html);
        $this->assertStringNotContainsString('Draft retraining plan', $html, 'unpublished measures stay out of search');
    }
}
