<?php

namespace Tests\Feature;

use App\Models\ExternalIncident;
use App\Models\ExternalRisk;
use App\Services\PolicyData\PolicyDataRepository;
use App\Services\PolicyData\PolicyImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicApiSurfaceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        (new PolicyImporter(PolicyDataRepository::default()))->run();
    }

    public function test_the_root_document_advertises_every_endpoint(): void
    {
        $this->getJson('/api/v1')->assertOk()->assertJsonStructure([
            'endpoints' => ['jurisdictions', 'policies', 'obligations', 'changes', 'taxonomies', 'frameworks', 'deadlines', 'incidents', 'risks'],
        ]);
    }

    public function test_the_framework_index_reports_coverage_per_framework(): void
    {
        $body = $this->getJson('/api/v1/frameworks')->assertOk()->json();
        $iso = collect($body['data'])->firstWhere('slug', 'iso-42001');

        $this->assertSame('ISO/IEC 42001:2023', $iso['name']);
        $this->assertSame(89, $iso['obligations_mapped']);
        $this->assertTrue($iso['certifiable']);
        $this->assertNotNull($iso['href']);

        // A framework with no mappings is listed for completeness but has no endpoint.
        $this->assertNull(collect($body['data'])->firstWhere('slug', 'oecd-ai-principles')['href']);
    }

    public function test_a_framework_endpoint_groups_mappings_by_family(): void
    {
        $body = $this->getJson('/api/v1/frameworks/nist-ai-rmf')->assertOk()->json('data');

        $this->assertSame('Function', $body['unit']);
        $this->assertSame(['GOVERN', 'MAP', 'MEASURE', 'MANAGE', 'Other references'], array_column($body['families'], 'family'));
        $this->assertArrayHasKey('obligation', $body['families'][0]['mappings'][0]);
    }

    public function test_a_crosswalk_endpoint_states_both_sides_of_its_denominator(): void
    {
        $body = $this->getJson('/api/v1/frameworks/iso-42001/eu')->assertOk()->json('data');

        $this->assertSame(42, $body['obligations_mapped']);
        $this->assertSame(44, $body['obligations_recorded']);
        $this->assertCount(42, $body['mappings']);
        $this->assertSame('European Union', $body['jurisdiction']['name']);
    }

    public function test_missing_frameworks_and_empty_crosswalks_are_not_found(): void
    {
        $this->getJson('/api/v1/frameworks/not-a-framework')->assertNotFound();
        $this->getJson('/api/v1/frameworks/oecd-ai-principles')->assertNotFound();
        $this->getJson('/api/v1/frameworks/iso-27001/uk')->assertNotFound();
    }

    public function test_deadlines_are_exposed_with_their_precision_and_source(): void
    {
        $body = $this->getJson('/api/v1/deadlines')->assertOk()->json();

        $this->assertGreaterThan(0, $body['meta']['total']);
        $this->assertArrayHasKey('date_precision', $body['data'][0]);
        $this->assertArrayHasKey('official_source_url', $body['data'][0]);
    }

    public function test_mirrored_datasets_carry_their_licence_and_citation(): void
    {
        $this->incident(9001, 'France');
        $this->risk('EV-TEST-1');

        foreach (['/api/v1/incidents', '/api/v1/risks'] as $endpoint) {
            $meta = $this->getJson($endpoint)->assertOk()->json('meta');

            $this->assertNotEmpty($meta['source']['license'] ?? null, "{$endpoint} must state the licence its rows are shared under.");
            $this->assertNotEmpty($meta['source']['name'] ?? null, "{$endpoint} must name the upstream source.");
        }
    }

    /** The incidents table requires a year and date alongside the identifier. */
    private function incident(int $id, string $country): ExternalIncident
    {
        return ExternalIncident::create([
            'incident_id' => $id, 'title' => 'Recorded incident '.$id, 'description' => 'A recorded incident.',
            'occurred_on' => '2026-02-01', 'year' => 2026, 'countries' => [$country], 'report_count' => 2,
        ]);
    }

    public function test_listings_are_returned_in_a_stable_declared_order(): void
    {
        // Ordering by a column that does not exist is silently accepted by SQLite, which
        // reads an unmatched double-quoted identifier as a string literal, so asserting
        // the order rather than the status code is what catches it on either engine.
        foreach (['EV-3', 'EV-1', 'EV-2'] as $id) {
            $this->risk($id);
        }
        $this->assertSame(['EV-1', 'EV-2', 'EV-3'], array_column($this->getJson('/api/v1/risks')->assertOk()->json('data'), 'ev_id'));

        // Incidents share dates, so the key has to break the tie or pages overlap.
        foreach ([9101, 9103, 9102] as $id) {
            $this->incident($id, 'France');
        }
        $this->assertSame([9103, 9102, 9101], array_column($this->getJson('/api/v1/incidents')->assertOk()->json('data'), 'incident_id'));
    }

    /** The risks table has no title; it is keyed by ev_id and described by its category pair. */
    private function risk(string $id): ExternalRisk
    {
        return ExternalRisk::create([
            'ev_id' => $id, 'risk_category' => 'Recorded category', 'risk_subcategory' => 'Recorded subcategory',
            'description' => 'A recorded risk.', 'domain' => 1, 'subdomain' => '1.1',
            'entity' => 'AI', 'intent' => 'Intentional', 'timing' => 'Pre-deployment',
            'level' => 'Risk Category', 'quick_ref' => 'Test2026', 'paper_title' => 'A recorded paper',
        ]);
    }

    public function test_incident_and_risk_filters_narrow_the_result(): void
    {
        $this->incident(9002, 'France');
        $this->incident(9003, 'Japan');
        $this->risk('EV-TEST-2');

        $this->assertSame(1, $this->getJson('/api/v1/incidents?country=France')->assertOk()->json('meta.total'));

        // The incidents table names its taxonomy columns mit_domain/mit_subdomain, so a
        // domain filter that reached for "domain" would fail only when it is exercised.
        ExternalIncident::where('incident_id', 9002)->update(['mit_domain' => '7']);
        $this->assertSame(1, $this->getJson('/api/v1/incidents?domain=7')->assertOk()->json('meta.total'));

        $this->getJson('/api/v1/risks?entity=nonsense')->assertOk()->assertJsonPath('meta.filters.entity', null);
        $this->assertSame(1, $this->getJson('/api/v1/risks?domain=1')->assertOk()->json('meta.total'));
    }
}
