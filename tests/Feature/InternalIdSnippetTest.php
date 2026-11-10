<?php

namespace Tests\Feature;

use App\Models\ExternalIncident;
use App\Models\PolicyInstrument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The AI Incident Database's numeric incident id is shown to readers but kept out of
 * search snippets (data-nosnippet). This site's own permanent ids (AIPT-…) are meant to be
 * cited, so the citation block is not hidden.
 */
class InternalIdSnippetTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_incident_id_is_visible_but_not_snippet_text(): void
    {
        $i = ExternalIncident::create(['incident_id' => 91430, 'title' => 'Chatbot Reportedly Gave Wrong Refund Policy', 'occurred_on' => '2024-03-01', 'year' => 2024, 'description' => 'A record for the test.', 'report_count' => 2, 'snapshot_date' => '2026-09-01']);
        $html = $this->get($i->url())->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/<dd class="font-mono" data-nosnippet>91430<\/dd>/', $html);
        $this->assertMatchesRegularExpression('/<p [^>]*data-nosnippet>Source record: <a [^>]*>incident #91430 /', $html);
        // Every visible "#91430" sits inside a data-nosnippet element.
        $this->assertSame(1, substr_count($html, '#91430'));
    }

    public function test_the_incident_listings_hide_the_number_from_snippets(): void
    {
        ExternalIncident::create(['incident_id' => 91431, 'title' => 'Listing Test Incident', 'occurred_on' => '2024-03-01', 'year' => 2024, 'description' => 'x', 'report_count' => 1, 'snapshot_date' => '2026-09-01']);

        $html = $this->get(route('risk.incidents'))->assertOk()->getContent();
        $this->assertStringContainsString('<span data-nosnippet>#91431</span>', $html);
    }

    public function test_the_permanent_id_on_a_record_stays_citable(): void
    {
        $this->artisan('policy:import');
        $policy = PolicyInstrument::published()->whereNotNull('aipt_id')->firstOrFail();
        $html = $this->get($policy->url())->assertOk()->getContent();

        $this->assertStringContainsString($policy->aipt_id, $html);
        $this->assertDoesNotMatchRegularExpression('/<p[^>]*data-cite[^>]*data-nosnippet/', $html);
    }
}
