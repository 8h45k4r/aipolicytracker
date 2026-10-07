<?php

namespace Tests\Feature;

use App\Models\EnforcementEvent;
use App\Models\ImplementationMeasure;
use App\Models\Jurisdiction;
use App\Models\PolicyInstrument;
use App\Services\Implementation\Trackers;
use App\Services\PolicyData\PolicyDataRepository;
use App\Services\PolicyData\PolicyDataValidator;
use App\Services\PolicyData\SchemaValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;
use Symfony\Component\Yaml\Yaml;
use Tests\Support\OpenApiContract;
use Tests\TestCase;

/**
 * F1, F2, F7: the enforcement tracker over enforcement_events, the implementation
 * tracker and the standards tracker over data/implementation. Everything seeded is
 * pending review or an empty draft; the hubs stay noindex and out of the sitemap
 * until enough recorded entries exist; the API, exports and contract carry them.
 *
 * Fixture events and measures are written to a throwaway copy of data/ only. They
 * use example.org sources and never touch the repository's own files.
 */
class EnforcementAndImplementationTrackerTest extends TestCase
{
    use OpenApiContract;
    use RefreshDatabase;

    private ?string $dataDir = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('policy:import')->assertSuccessful();
    }

    protected function tearDown(): void
    {
        if ($this->dataDir) {
            File::deleteDirectory($this->dataDir);
        }
        parent::tearDown();
    }

    public function test_seeded_measures_import_unverified_with_nothing_invented(): void
    {
        $this->artisan('policy:validate')->assertSuccessful();
        $this->assertGreaterThanOrEqual(8, ImplementationMeasure::count());
        foreach (ImplementationMeasure::all() as $m) {
            $this->assertNotSame('verified', $m->review_status, $m->slug.' ships unverified');
            $this->assertNull($m->last_verified_at, $m->slug);
            $this->assertNull($m->reviewed_by, $m->slug);
            if ($m->isDraft()) {
                $this->assertSame('unverified', $m->status, $m->slug);
                foreach (['legal_basis', 'summary', 'due_on', 'adopted_on', 'published_on', 'official_source_url', 'reference'] as $field) {
                    $this->assertNull($m->{$field}, $m->slug.' draft has no '.$field);
                }
            } else {
                $this->assertSame('pending_review', $m->review_status, $m->slug);
                $this->assertNotNull($m->official_source_url, $m->slug.' cites its source');
            }
        }
        // Standards are metadata only, and a year-only publication date reads as the year.
        $iso = ImplementationMeasure::where('slug', 'iso-iec-42005-2025')->firstOrFail();
        $this->assertSame('2025', $iso->publishedLabel());
        $this->assertTrue($iso->isStandard());
        $this->assertSame('iso-iec-42001-2023-ai-management-system', ImplementationMeasure::where('slug', 'iso-iec-42001-2023')->firstOrFail()->relatedPolicy->slug);
        $this->assertSame('eu-ai-act', ImplementationMeasure::where('slug', 'eu-ai-act-gpai-code-of-practice')->firstOrFail()->policyInstrument->slug);
        // No enforcement action is recorded anywhere yet: the tracker starts empty rather than from press reports.
        $this->assertSame(0, EnforcementEvent::count());
    }

    public function test_the_validator_rejects_malformed_measures_and_events(): void
    {
        $dir = $this->copyData();
        File::put($dir.'/implementation/bad-measure.yaml', Yaml::dump([
            'slug' => 'bad-measure', 'title' => 'A malformed measure', 'instrument' => 'no-such-policy', 'kind' => 'press_release',
            'status' => 'overdue', 'published_on' => '2025-05-20', 'published_on_precision' => 'year', 'oj_citation_on' => '2026-01-01',
            'review_status' => 'pending_review',
        ]));
        File::put($dir.'/implementation/unnamed-guidelines.yaml', Yaml::dump(['slug' => 'unnamed-guidelines', 'title' => 'Guidelines with no instrument', 'kind' => 'guidelines', 'status' => 'published', 'review_status' => 'pending_review', 'official_source_url' => 'https://example.org/g']));
        $this->appendEvents($dir, [
            ['slug' => 'fixture-dup', 'title' => 'Fixture event one', 'kind' => 'fine', 'amount' => 1000, 'official_source_url' => 'https://example.org/1', 'source_title' => 'Fixture', 'source_publisher' => 'Example regulator'],
            ['slug' => 'fixture-dup', 'title' => 'Fixture event two', 'kind' => 'penalty', 'jurisdiction' => 'atlantis', 'official_source_url' => 'https://example.org/2', 'source_title' => 'Fixture', 'source_publisher' => 'Example regulator'],
        ]);

        $errors = $this->validate($dir);
        $measure = implode("\n", $errors['implementation/bad-measure.yaml'] ?? []);
        $this->assertStringContainsString('unknown policy slug "no-such-policy"', $measure);
        $this->assertStringContainsString('$.kind', $measure);
        $this->assertStringContainsString('$.status', $measure, 'overdue is derived, never declared');
        $this->assertStringContainsString('1 January', $measure);
        $this->assertStringContainsString('only a harmonised standard', $measure);
        $this->assertStringContainsString('must name the instrument', implode("\n", $errors['implementation/unnamed-guidelines.yaml'] ?? []));
        $policy = implode("\n", $errors['policies/eu/eu-ai-act.yaml'] ?? []);
        $this->assertStringContainsString('an amount needs its currency', $policy);
        $this->assertStringContainsString('duplicate event slug "fixture-dup"', $policy);
        $this->assertStringContainsString('unknown jurisdiction "atlantis"', $policy);
        $this->assertStringContainsString('enforcement_events[1].kind', $policy);
    }

    public function test_the_pages_render_and_are_noindex_below_the_threshold(): void
    {
        $impl = $this->get('/policies/eu-ai-act/implementation')->assertOk()->getContent();
        $this->assertSame(1, preg_match_all('#<h1\b#', $impl));
        $this->assertStringContainsString('General-Purpose AI Code of Practice', $impl);
        $this->assertStringContainsString('Draft', $impl);
        $this->assertStringContainsString('Application dates on the EU AI Act record', $impl);
        $this->assertStringContainsString('name="robots" content="noindex', $impl, 'two recorded measures are below the threshold');
        $this->assertStringNotContainsString('ISO/IEC 42005', $impl, 'standards are on the standards tracker');

        $standards = $this->get('/ai-standards')->assertOk()->getContent();
        $this->assertStringContainsString('ISO/IEC 42001:2023', $standards);
        $this->assertStringContainsString('Metadata only', $standards);
        $this->assertStringContainsString('name="robots" content="noindex', $standards);

        $enforcement = $this->get('/enforcement')->assertOk()->getContent();
        $this->assertStringContainsString('No enforcement action is recorded yet', $enforcement);
        $this->assertStringContainsString('name="robots" content="noindex', $enforcement);
        $this->assertStringContainsString('"@type":"Dataset"', $enforcement);

        $this->get('/policies/us-nist-ai-rmf/implementation')->assertNotFound();
        $this->get('/policies/no-such-policy/implementation')->assertNotFound();

        $sitemap = $this->get('/sitemap-static.xml')->assertOk()->getContent();
        foreach ([route('enforcement.index'), route('standards.index'), route('policies.implementation', 'eu-ai-act')] as $url) {
            $this->assertStringNotContainsString('<loc>'.$url.'</loc>', $sitemap, $url.' is noindex, so it is not in the sitemap');
        }

        // The include point for the policy page: a pointer when measures exist, nothing otherwise.
        $eu = PolicyInstrument::where('slug', 'eu-ai-act')->firstOrFail();
        $this->assertStringContainsString(route('policies.implementation', 'eu-ai-act'), Blade::render('<x-site.implementation-link :policy="$p" />', ['p' => $eu]));
        $nist = PolicyInstrument::where('slug', 'us-nist-ai-rmf')->firstOrFail();
        $this->assertSame('', trim(Blade::render('<x-site.implementation-link :policy="$p" />', ['p' => $nist])));
    }

    public function test_enough_recorded_entries_index_the_hubs_and_filters_overdue_and_exports_work(): void
    {
        $dir = $this->copyData();
        $events = [];
        foreach (range(1, 5) as $i) {
            $events[] = [
                'title' => 'Fixture enforcement action '.$i, 'occurred_on' => '2026-0'.$i.'-15', 'kind' => $i === 1 ? 'fine' : 'order',
                'jurisdiction' => $i === 1 ? 'italy' : null, 'regulator' => 'Example regulator', 'respondent' => 'Example Corp '.$i,
                'amount' => $i === 1 ? 1500000 : null, 'currency' => $i === 1 ? 'EUR' : null, 'legal_basis' => 'Article 99',
                'outcome' => 'Fixture outcome.', 'appeal_status' => 'appeal_pending', 'official_source_url' => 'https://example.org/e'.$i,
                'source_title' => 'Fixture decision', 'source_publisher' => 'Example regulator', 'review_status' => 'pending_review', 'confidence_level' => 'medium',
            ];
        }
        $this->appendEvents($dir, array_map(fn ($e) => array_filter($e, fn ($v) => $v !== null), $events));
        foreach (range(1, 4) as $i) {
            File::put($dir.'/implementation/fixture-measure-'.$i.'.yaml', Yaml::dump([
                'slug' => 'fixture-measure-'.$i, 'title' => 'Fixture implementing act '.$i, 'instrument' => 'eu-ai-act', 'kind' => 'implementing_act',
                'status' => 'planned', 'due_on' => $i === 1 ? '2026-02-02' : '2099-01-01', 'official_source_url' => 'https://example.org/m'.$i,
                'source_publisher' => 'Example', 'review_status' => 'pending_review', 'confidence_level' => 'medium',
            ]));
        }
        $this->assertSame([], $this->validate($dir));
        $this->artisan('policy:import', ['--path' => $dir])->assertSuccessful();

        $this->assertSame(5, EnforcementEvent::count());
        $fine = EnforcementEvent::where('kind', 'fine')->firstOrFail();
        $this->assertSame(Jurisdiction::where('slug', 'italy')->value('id'), $fine->jurisdiction_id, 'filed where the authority acted');
        $this->assertSame('EUR 1,500,000', $fine->amountLabel());
        $this->assertNotEmpty($fine->slug);
        $this->assertTrue(Trackers::enforcementIndexable());

        $hub = $this->get('/enforcement')->assertOk()->getContent();
        $this->assertStringContainsString('name="robots" content="index', $hub);
        $this->assertStringContainsString('5 enforcement actions recorded across 2 jurisdictions', $hub);
        $this->assertStringContainsString('id="'.$fine->slug.'"', $hub);
        $filtered = $this->get('/enforcement?kind=fine')->assertOk()->getContent();
        $this->assertStringContainsString('name="robots" content="noindex', $filtered, 'a filtered view is not indexed');
        $this->assertStringContainsString('Example Corp 1', $filtered);
        $this->assertStringNotContainsString('Example Corp 2', $filtered);
        $this->assertStringContainsString('Example Corp 3', $this->get('/enforcement?year=2026&jurisdiction=eu')->getContent());
        $this->assertStringNotContainsString('Example Corp 1', $this->get('/enforcement?jurisdiction=eu')->getContent());

        // Six recorded measures: the implementation page is indexable, and one is overdue.
        $impl = $this->get('/policies/eu-ai-act/implementation')->assertOk()->getContent();
        $this->assertStringContainsString('name="robots" content="index', $impl);
        $this->assertStringContainsString('1 is overdue', $impl);
        $sitemap = $this->get('/sitemap-static.xml')->getContent();
        $this->assertStringContainsString('<loc>'.route('enforcement.index').'</loc>', $sitemap);
        $this->assertStringContainsString('<loc>'.route('policies.implementation', 'eu-ai-act').'</loc>', $sitemap);
        $this->assertStringNotContainsString('<loc>'.route('standards.index').'</loc>', $sitemap);

        $api = $this->getJson('/api/v1/enforcement')->assertOk()->json();
        $this->assertMatchesOpenApi('/enforcement', $api);
        $this->assertSame(5, $api['meta']['total']);
        $byKind = collect($api['data'])->groupBy('kind');
        $this->assertSame(1500000.0, (float) $byKind['fine'][0]['amount']);
        $this->assertNull($byKind['order'][0]['amount'], 'an unpublished amount stays null');
        $this->assertSame(1, $this->getJson('/api/v1/enforcement?kind=fine&year=2026')->json('meta.total'));
        $this->assertSame(0, $this->getJson('/api/v1/enforcement?year=2025')->json('meta.total'));

        $overdue = $this->getJson('/api/v1/implementation-measures?instrument=eu-ai-act&status=overdue')->assertOk()->json();
        $this->assertMatchesOpenApi('/implementation-measures', $overdue);
        $this->assertSame(['fixture-measure-1'], array_column($overdue['data'], 'slug'));
        $this->assertSame('planned', $overdue['data'][0]['declared_status']);

        $csv = $this->get(route('open-data.csv', 'enforcement'))->assertOk()->streamedContent();
        $this->assertStringContainsString('Example Corp 1', $csv);
        $this->assertSame(6, count(array_filter(explode("\n", trim($csv)))), 'header and five rows');
        $ndjson = $this->get(route('open-data.ndjson', 'implementation'))->assertOk()->streamedContent();
        $this->assertStringContainsString('"slug":"fixture-measure-1"', $ndjson);
        $this->assertStringContainsString('"status":"overdue"', $ndjson);
    }

    public function test_api_contract_llms_schema_and_policy_json_carry_the_new_shapes(): void
    {
        $measures = $this->getJson('/api/v1/implementation-measures')->assertOk()->json();
        $this->assertMatchesOpenApi('/implementation-measures', $measures);
        $this->assertContains('draft', array_column($measures['data'], 'review_status'));
        $standards = $this->getJson('/api/v1/implementation-measures?standards=1')->assertOk()->json();
        $this->assertMatchesOpenApi('/implementation-measures', $standards);
        $this->assertSame(['iso_work_item'], array_values(array_unique(array_column($standards['data'], 'kind'))));
        $empty = $this->getJson('/api/v1/enforcement')->assertOk()->json();
        $this->assertMatchesOpenApi('/enforcement', $empty);
        $this->assertSame([], $empty['data']);

        $root = $this->getJson('/api/v1')->assertOk()->json('endpoints');
        $this->assertSame(route('api.v1.enforcement'), $root['enforcement']);
        $this->assertSame(route('api.v1.implementation'), $root['implementation_measures']);

        $llms = $this->get('/llms.txt')->assertOk()->getContent();
        $this->assertStringContainsString(route('enforcement.index'), $llms);
        $this->assertStringContainsString(route('standards.index'), $llms);
        $this->assertStringContainsString(route('policies.implementation', 'eu-ai-act'), $llms);
        $this->assertStringContainsString('implementation-measure', $this->get('/schema/implementation-measure.schema.json')->assertOk()->getContent());
    }

    private function copyData(): string
    {
        $this->dataDir = storage_path('framework/testing/data-'.uniqid());
        File::copyDirectory(base_path('data'), $this->dataDir);

        return $this->dataDir;
    }

    /** Appends an enforcement_events list to the copy's EU AI Act record (it carries none). */
    private function appendEvents(string $dir, array $events): void
    {
        $file = $dir.'/policies/eu/eu-ai-act.yaml';
        $this->assertStringNotContainsString("\nenforcement_events:", File::get($file));
        File::append($file, "\n".Yaml::dump(['enforcement_events' => $events], 4, 2));
    }

    /** @return array<string, list<string>> */
    private function validate(string $dir): array
    {
        $repository = new PolicyDataRepository($dir);

        return (new PolicyDataValidator($repository, new SchemaValidator($repository->schemaDir())))->run();
    }
}
