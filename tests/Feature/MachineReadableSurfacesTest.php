<?php

namespace Tests\Feature;

use App\Models\ChangeEvent;
use App\Models\Jurisdiction;
use App\Models\Obligation;
use App\Models\PolicyInstrument;
use App\Services\Completeness\CompletenessReport;
use App\Services\MachineReadable\BulkExport;
use App\Services\Reviewers\ReviewerRoster;
use App\Services\Verification\VerificationPolicy;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MachineReadableSurfacesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->artisan('policy:import');
    }

    public function test_every_schema_is_served_at_the_url_its_own_id_declares(): void
    {
        // The schema files have always declared $id: https://aipolicytracker.org/schema/...
        // and nothing was served there, so a validator resolving the identifier got a 404.
        $files = glob(base_path('data/schema/*.schema.json')) ?: [];
        $this->assertNotEmpty($files);

        foreach ($files as $path) {
            $name = str_replace('.schema.json', '', basename($path));
            $response = $this->get(route('schema.show', $name));
            $response->assertOk();
            $response->assertHeader('Content-Type', 'application/schema+json');

            $served = json_decode($response->getContent(), true);
            $this->assertIsArray($served, "{$name} must be valid JSON");
            $this->assertSame(
                'https://aipolicytracker.org/schema/'.$name.'.schema.json',
                $served['$id'] ?? null,
                "{$name} is served at a URL its \$id does not claim"
            );
        }

        $this->get('/schema/nosuchthing.schema.json')->assertNotFound();
    }

    public function test_a_policy_context_file_carries_the_record_and_its_provenance(): void
    {
        $policy = PolicyInstrument::published()->with('jurisdiction')->where('slug', 'eu-ai-act')->firstOrFail();

        $response = $this->get(route('policies.context', $policy->slug));
        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/markdown; charset=UTF-8');
        $body = $response->getContent();

        $this->assertStringStartsWith('# '.$policy->title, $body);
        $this->assertStringContainsString('**Jurisdiction**: '.$policy->jurisdiction->name, $body);
        $this->assertStringContainsString('## Provenance', $body);
        $this->assertStringContainsString($policy->official_source_url, $body);
        $this->assertStringContainsString('not legal advice', $body);
        // A record nobody has checked says so, rather than leaving the line out: an absent
        // line reads as "fine" to a person and to a model.
        $this->assertStringContainsString('never confirmed against the official source', $body);
    }

    public function test_a_context_file_exists_for_every_record_kind_and_only_for_published_ones(): void
    {
        $cases = [
            'policies.context' => PolicyInstrument::published()->firstOrFail(),
            'jurisdictions.context' => Jurisdiction::published()->firstOrFail(),
            'obligations.context' => Obligation::published()->firstOrFail(),
            'changes.context' => ChangeEvent::published()->firstOrFail(),
        ];
        foreach ($cases as $route => $record) {
            $this->get(route($route, $record->slug))->assertOk()->assertHeader('Content-Type', 'text/markdown; charset=UTF-8');
        }

        $hidden = PolicyInstrument::published()->firstOrFail();
        $hidden->forceFill(['published_at' => null])->save();
        $this->get(route('policies.context', $hidden->slug))->assertNotFound();
        $this->get(route('policies.context', 'not-a-record'))->assertNotFound();
    }

    public function test_a_stored_paragraph_cannot_break_the_document_structure(): void
    {
        $policy = PolicyInstrument::published()->firstOrFail();
        $policy->forceFill(['summary_plain' => "First line\n\n## Fake heading\n\n- injected bullet"])->save();

        $body = $this->get(route('policies.context', $policy->slug))->getContent();
        $this->assertStringContainsString('First line ## Fake heading - injected bullet', $body);
        // One real "Summary" heading, and the injected one did not become a second heading.
        $this->assertSame(1, substr_count($body, "\n## Summary\n"));
        $this->assertSame(0, substr_count($body, "\n## Fake heading\n"));
    }

    public function test_every_dataset_exports_as_csv_and_as_newline_delimited_json(): void
    {
        $export = app(BulkExport::class);

        foreach (array_keys(BulkExport::DATASETS) as $dataset) {
            $csv = $this->get(route('open-data.csv', $dataset));
            $csv->assertOk();
            $csv->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
            $rows = array_values(array_filter(explode("\n", trim($csv->streamedContent()))));
            $this->assertSame($export->columns($dataset), str_getcsv($rows[0]), "{$dataset} header must match the declared columns");

            $ndjson = $this->get(route('open-data.ndjson', $dataset));
            $ndjson->assertOk();
            $ndjson->assertHeader('Content-Type', 'application/x-ndjson; charset=UTF-8');
            $lines = array_values(array_filter(explode("\n", trim($ndjson->streamedContent()))));
            $this->assertNotEmpty($lines, "{$dataset} exported no rows");
            foreach (array_slice($lines, 0, 5) as $line) {
                $this->assertIsArray(json_decode($line, true), 'every line is a complete JSON object on its own');
            }
            $this->assertCount(count($rows) - 1, $lines, "{$dataset} must export the same rows in both formats");
        }

        $this->get('/open-data/nosuchset.csv')->assertNotFound();
        $this->get('/open-data/nosuchset.ndjson')->assertNotFound();
    }

    public function test_every_exported_row_carries_its_own_provenance(): void
    {
        // A row that travels on its own without these invites somebody to treat an
        // unverified summary as a fact.
        foreach (['policies', 'obligations', 'changes', 'jurisdictions'] as $dataset) {
            $columns = app(BulkExport::class)->columns($dataset);
            foreach (['official_source_url', 'review_status', 'confidence_level', 'last_verified_at'] as $required) {
                $this->assertContains($required, $columns, "{$dataset} rows must carry {$required}");
            }
        }
    }

    public function test_an_export_never_leaks_an_unpublished_record(): void
    {
        $hidden = PolicyInstrument::published()->firstOrFail();
        $slug = $hidden->slug;
        $hidden->forceFill(['published_at' => null])->save();

        $body = $this->get(route('open-data.ndjson', 'policies'))->streamedContent();
        $this->assertStringNotContainsString('"slug":"'.$slug.'"', $body);
    }

    public function test_the_health_document_reports_the_same_numbers_as_the_policies_it_summarises(): void
    {
        $verification = app(VerificationPolicy::class)->report();
        $completeness = app(CompletenessReport::class)->report();

        $response = $this->get(route('open-data.health'));
        $response->assertOk();
        $health = $response->json();

        $this->assertSame($verification['critical_overdue'], $health['freshness']['critical_overdue']);
        $this->assertSame($verification['never'], $health['freshness']['never_verified']);
        $this->assertSame($completeness['required_gaps'], $health['completeness']['required_gaps']);
        $this->assertSame($completeness['records'], $health['records_published']);
        $this->assertSame(app(ReviewerRoster::class)->standing()['verified'], $health['review']['verified_by_a_named_reviewer']);
        $this->assertStringContainsString('not legal advice', $health['notice']);
    }

    public function test_the_machine_surfaces_allow_cross_origin_reads(): void
    {
        // An assistant or a browser-based tool reads these from another origin; without
        // the header the surfaces exist but cannot be used.
        foreach ([
            route('open-data.health'),
            route('schema.show', 'policy'),
            route('policies.context', PolicyInstrument::published()->firstOrFail()->slug),
        ] as $url) {
            $this->get($url)->assertOk()->assertHeader('Access-Control-Allow-Origin', '*');
        }
    }

    public function test_security_txt_names_the_official_mailbox_and_has_not_expired(): void
    {
        $response = $this->get('/.well-known/security.txt');
        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/plain; charset=UTF-8');
        $body = $response->getContent();

        $this->assertStringContainsString('Contact: mailto:'.config('aipolicytracker.contact_email'), $body);
        $this->assertMatchesRegularExpression('/^Expires: \d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/m', $body);
        preg_match('/^Expires: (.+)$/m', $body, $m);
        $this->assertTrue(now()->lt(Carbon::parse($m[1])), 'security.txt must not be expired when served');
        $this->assertStringContainsString('Canonical: '.url('/.well-known/security.txt'), $body);

        $this->get('/security.txt')->assertRedirect('/.well-known/security.txt');
    }

    public function test_every_page_publishes_the_official_mailbox_in_its_organization_node(): void
    {
        $html = $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('"email":"'.config('aipolicytracker.contact_email').'"', $html);
        $this->assertStringContainsString('"@type":"ContactPoint"', $html);
        $this->assertStringContainsString('mailto:'.config('aipolicytracker.contact_email'), $html);
    }
}
