<?php

namespace Tests\Feature;

use App\Models\ExternalIncident;
use App\Services\ExternalData\IncidentEnrichment;
use App\Services\ExternalData\IncidentSensitivity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\OpenApiContract;
use Tests\TestCase;

/**
 * P4: what this site adds to an incident record (harm domain, related laws,
 * policy angle, sensitivity), that a reviewer's override wins and survives a
 * re-import, and that a sensitive record is kept out of search and out of the
 * modules that surface incidents, while still linking to the law.
 */
class IncidentBrandSafetyTest extends TestCase
{
    use OpenApiContract;
    use RefreshDatabase;

    private string $overrides;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('policy:import');
        $this->overrides = tempnam(sys_get_temp_dir(), 'ovr').'.yaml';
        file_put_contents($this->overrides, "overrides: []\n");
        IncidentEnrichment::useOverrides($this->overrides);
    }

    protected function tearDown(): void
    {
        IncidentEnrichment::useOverrides(null);
        IncidentEnrichment::reset();
        @unlink($this->overrides);
        parent::tearDown();
    }

    public function test_a_record_is_enriched_from_its_own_coding_and_this_sites_data(): void
    {
        $i = $this->incident(['incident_id' => 90001, 'title' => 'Hiring Tool Allegedly Ranked Women Lower', 'mit_domain' => 'Discrimination and Toxicity', 'mit_subdomain' => 'Unfair discrimination and misrepresentation', 'countries' => ['US']]);
        $this->assertSame(1, IncidentEnrichment::apply());
        $i->refresh();

        $this->assertSame('discrimination-toxicity', $i->harm_domain);
        $this->assertSame(IncidentEnrichment::STANDARD, $i->sensitivity);
        $this->assertNotEmpty($i->related_policy_slugs, 'a hiring incident in the US finds laws on hiring');
        $this->assertStringStartsWith('Classified under Discrimination and Toxicity (Unfair discrimination and misrepresentation)', $i->policy_angle);
        $this->assertStringContainsString('instrument', $i->policy_angle);
        $this->assertStringNotContainsString('Women', $i->policy_angle, 'the angle never characterises the incident beyond its coding');

        // apply() fills only what is empty; a second run writes nothing.
        $this->assertSame(0, IncidentEnrichment::apply());
    }

    public function test_sensitivity_is_classified_from_the_headline_and_a_reviewer_override_wins_and_survives_reimport(): void
    {
        $i = $this->incident(['incident_id' => 90002, 'title' => 'Deepfake Nudes of a Presenter Circulated Online', 'developers' => ['Stability AI']]);
        $moderation = $this->incident(['incident_id' => 90003, 'title' => 'Moderation Tool Misidentified Rockets as Pornography']);
        IncidentEnrichment::apply();
        $this->assertSame(IncidentEnrichment::SENSITIVE, $i->fresh()->sensitivity);
        $this->assertSame(IncidentEnrichment::STANDARD, $moderation->fresh()->sensitivity);
        $this->assertTrue(IncidentSensitivity::isSensitive($i->fresh()));
        $this->assertFalse(IncidentSensitivity::isSensitive($moderation->fresh()));

        // A reviewer decides the moderation record is sensitive after all, and
        // writes a policy angle of their own.
        file_put_contents($this->overrides, "overrides:\n  - incident_id: 90003\n    sensitivity: sensitive\n    policy_angle: Reviewed; treated as sensitive because the reports reproduce the images.\n    note: test\n");
        IncidentEnrichment::reset();
        IncidentEnrichment::apply();
        $moderation->refresh();
        $this->assertSame(IncidentEnrichment::SENSITIVE, $moderation->sensitivity);
        $this->assertSame('Reviewed; treated as sensitive because the reports reproduce the images.', $moderation->policy_angle);

        // A re-import upserts the database's columns and never ours.
        ExternalIncident::upsert([['incident_id' => 90003, 'title' => 'Moderation Tool Misidentified Rockets as Pornography (updated)', 'description' => 'Updated by the weekly import.', 'occurred_on' => '2024-03-01', 'year' => 2024, 'report_count' => 2, 'snapshot_date' => '2026-09-08']], ['incident_id'], ['title', 'description', 'report_count', 'snapshot_date']);
        IncidentEnrichment::apply();
        $moderation->refresh();
        $this->assertSame('Moderation Tool Misidentified Rockets as Pornography (updated)', $moderation->title);
        $this->assertSame(IncidentEnrichment::SENSITIVE, $moderation->sensitivity, 'the override survives');
        $this->assertSame('Reviewed; treated as sensitive because the reports reproduce the images.', $moderation->policy_angle);
    }

    public function test_a_sensitive_page_is_neutral_noindexed_out_of_sitemaps_and_modules_and_still_links_the_law(): void
    {
        $sensitive = $this->incident(['incident_id' => 90004, 'title' => 'Deepfake Nudes of a Singer Circulated on Social Media', 'developers' => ['Stability AI'], 'mit_domain' => 'Malicious actors', 'mit_subdomain' => 'Disinformation, surveillance, and influence at scale', 'countries' => ['US'], 'occurred_on' => '2026-09-20', 'year' => 2026]);
        $standard = $this->incident(['incident_id' => 90005, 'title' => 'Recruitment Model Rejected Applicants by Postcode', 'mit_domain' => 'Discrimination and Toxicity', 'mit_subdomain' => 'Unfair discrimination and misrepresentation', 'countries' => ['US'], 'occurred_on' => '2026-09-21', 'year' => 2026]);
        IncidentEnrichment::apply();

        $html = $this->get($sensitive->fresh()->url())->assertOk()->getContent();
        $head = substr($html, 0, strpos($html, '</head>'));
        $this->assertStringContainsString('<title>AI incident: sexual content (Stability AI, Sep 2026)', $head);
        $this->assertStringContainsString('noindex', $head);
        $this->assertStringNotContainsStringIgnoringCase('nude', $head);
        $this->assertStringNotContainsStringIgnoringCase('singer', $html, 'the page body does not repeat the headline either');
        $this->assertStringContainsString('Laws that address this harm', $html);
        $this->assertStringContainsString('"@type":"Article"', $head);

        $standardHtml = $this->get($standard->fresh()->url())->assertOk()->getContent();
        $this->assertStringContainsString('Laws that address this harm', $standardHtml);
        $this->assertStringContainsString('Policy angle', $standardHtml);
        $this->assertStringContainsString('index,follow', $standardHtml);

        $this->get('/sitemap-incidents.xml')->assertOk()->assertDontSee($sensitive->fresh()->url())->assertSee($standard->fresh()->url());
        foreach (['/', '/ai-risk/incidents', '/ai-risk/malicious-actors', '/ai-risk'] as $module) {
            $this->get($module)->assertOk()->assertDontSee($sensitive->fresh()->url())->assertDontSee('Singer');
        }
        $this->get('/ai-risk/incidents')->assertSee($standard->fresh()->url());
        // The browse tool still lists it, under its neutral name.
        $this->get('/ai-risk/incidents/browse?year=2026')->assertOk()->assertSee($sensitive->fresh()->url())->assertDontSee('Singer')->assertSee('AI incident: sexual content');

        $this->artisan('seo:sensitive-indexed')->expectsOutputToContain('No sensitive page is in a sitemap')->assertExitCode(0);
        $csv = tempnam(sys_get_temp_dir(), 'gsc').'.csv';
        file_put_contents($csv, "Top pages,Clicks,Impressions,CTR,Position\n".$sensitive->fresh()->url().",3,\"1,200\",0.25%,4.1\n".url('/ai-risk/incidents/90004').",0,50,0%,9\n".$standard->fresh()->url().",1,100,1%,5\n");
        $this->artisan('seo:sensitive-indexed', ['csv' => $csv])->expectsOutputToContain('2 sensitive page(s) appear in the export')->assertExitCode(0);
    }

    public function test_the_api_and_the_exports_carry_the_added_fields_and_match_the_contract(): void
    {
        $this->incident(['incident_id' => 90006, 'title' => 'Chatbot Reportedly Gave Harmful Advice', 'mit_domain' => 'Human-Computer Interaction', 'mit_subdomain' => 'Overreliance and unsafe use', 'countries' => ['GB']]);
        IncidentEnrichment::apply();

        $payload = $this->getJson('/api/v1/incidents')->assertOk()->json();
        $this->assertMatchesOpenApi('/incidents', $payload);
        $row = collect($payload['data'])->firstWhere('incident_id', 90006);
        $this->assertSame('human-computer-interaction', $row['harm_domain']);
        $this->assertSame('standard', $row['sensitivity']);
        $this->assertIsArray($row['related_policies']);
        $this->assertStringStartsWith('Classified under', $row['policy_angle']);

        $csv = $this->get('/ai-risk/incidents/export.csv')->assertOk()->streamedContent();
        $this->assertStringContainsString('harm_domain,policy_angle,related_policy_slugs,sensitivity', $csv);
        $this->assertStringContainsString('human-computer-interaction', $csv);
    }

    private function incident(array $attributes): ExternalIncident
    {
        return ExternalIncident::create($attributes + [
            'occurred_on' => '2024-03-01',
            'year' => 2024,
            'description' => 'A record for the test.',
            'report_count' => 1,
            'snapshot_date' => '2026-09-01',
        ]);
    }
}
