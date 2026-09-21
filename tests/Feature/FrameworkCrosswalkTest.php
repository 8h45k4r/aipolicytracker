<?php

namespace Tests\Feature;

use App\Models\FrameworkMapping;
use App\Services\PolicyData\FrameworkCrosswalk;
use App\Services\PolicyData\PolicyDataRepository;
use App\Services\PolicyData\PolicyImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FrameworkCrosswalkTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        (new PolicyImporter(PolicyDataRepository::default()))->run();
    }

    public function test_the_hub_lists_every_framework_that_has_mappings(): void
    {
        $this->get(route('frameworks.index'))
            ->assertOk()
            ->assertSee('ISO/IEC 42001:2023')
            ->assertSee('NIST AI Risk Management Framework 1.0')
            ->assertSee('Which AI legal duties map to which standard');
    }

    public function test_a_framework_page_groups_duties_by_clause_and_states_its_denominator(): void
    {
        $this->get(route('frameworks.show', 'iso-42001'))
            ->assertOk()
            ->assertSee('Annex A')
            ->assertSee('Clause 6')
            ->assertSee('Legal duties by clause')
            ->assertSee('distinct duties', false);
    }

    public function test_nist_functions_are_listed_in_lifecycle_order(): void
    {
        $body = $this->get(route('frameworks.show', 'nist-ai-rmf'))->assertOk()->getContent();

        $this->assertLessThan(strpos($body, 'MAP'), strpos($body, 'GOVERN'));
        $this->assertLessThan(strpos($body, 'MEASURE'), strpos($body, 'MAP'));
        $this->assertLessThan(strpos($body, 'MANAGE'), strpos($body, 'MEASURE'));
    }

    public function test_the_eu_crosswalk_shows_every_mapped_duty_with_its_clause(): void
    {
        $mapped = FrameworkMapping::where('framework', 'iso_42001')
            ->whereHas('obligation.policyInstrument.jurisdiction', fn ($q) => $q->where('slug', 'eu'))->count();

        $this->assertSame(18, $mapped, 'The EU AI Act crosswalk is the flagship page; if this count moves, the page copy needs rechecking.');

        $this->get(route('frameworks.crosswalk', ['iso-42001', 'eu']))
            ->assertOk()
            ->assertSee('European Union AI rules mapped to ISO/IEC 42001')
            ->assertSee('Coverage of this crosswalk')
            ->assertSee('recorded European Union duties carry a mapping')
            ->assertSee('does not discharge a legal duty', false);
    }

    public function test_a_substantial_crosswalk_is_indexable_and_a_thin_one_is_not(): void
    {
        $this->get(route('frameworks.crosswalk', ['iso-42001', 'eu']))
            ->assertOk()->assertDontSee('noindex', false);

        // Nepal records a single mapping, which is too thin to be worth indexing.
        $this->get(route('frameworks.crosswalk', ['iso-42001', 'nepal']))
            ->assertOk()->assertSee('noindex', false);
    }

    public function test_unknown_frameworks_jurisdictions_and_empty_crosswalks_are_not_found(): void
    {
        $this->get('/frameworks/not-a-framework')->assertNotFound();
        $this->get('/frameworks/iso-42001/not-a-jurisdiction')->assertNotFound();
        // Recorded in config but with no mappings, so it has no page of its own.
        $this->get(route('frameworks.show', 'oecd-ai-principles'))->assertNotFound();
        // A real jurisdiction with no mapping to this framework.
        $this->get(route('frameworks.crosswalk', ['iso-27001', 'uk']))->assertNotFound();
    }

    public function test_the_sitemap_lists_exactly_the_framework_pages_that_allow_indexing(): void
    {
        $listed = [];
        preg_match_all('#<loc>([^<]*/frameworks[^<]*)</loc>#', $this->get('/sitemap-static.xml')->assertOk()->getContent(), $m);
        foreach ($m[1] as $url) {
            $listed[] = parse_url(html_entity_decode($url), PHP_URL_PATH);
        }

        $this->assertNotEmpty($listed, 'The framework pages must appear in a sitemap or nothing will crawl them.');

        // Nothing advertised may serve noindex, and nothing indexable may be left out.
        foreach ($listed as $path) {
            $this->assertStringNotContainsString('noindex', $this->get($path)->assertOk()->getContent(), "{$path} is in the sitemap but asks not to be indexed.");
        }

        foreach ([['iso-42001', 'eu'], ['nist-ai-rmf', 'eu']] as [$framework, $jurisdiction]) {
            $this->assertContains("/frameworks/{$framework}/{$jurisdiction}", $listed);
        }
        // Thin pages stay reachable but out of the sitemap.
        $this->assertNotContains('/frameworks/iso-42001/nepal', $listed);
        $this->assertNotContains('/frameworks/iso-27001', $listed);
    }

    public function test_the_agent_index_advertises_the_crosswalks_with_their_denominators(): void
    {
        $body = $this->get('/llms.txt')->assertOk()->getContent();

        $this->assertStringContainsString('## Law-to-standard crosswalks', $body);
        $this->assertStringContainsString('European Union AI rules mapped to ISO/IEC 42001', $body);
        $this->assertStringContainsString('18 of 19 recorded duties mapped', $body);
        $this->assertStringContainsString('never means certification discharges the duty', $body);
    }

    public function test_clause_references_are_parsed_into_families_including_ranges_and_lists(): void
    {
        $iso = fn (string $reference) => (new FrameworkMapping(['framework' => 'iso_42001', 'reference' => $reference]))->families();
        $nist = fn (string $reference) => (new FrameworkMapping(['framework' => 'nist_ai_rmf', 'reference' => $reference]))->families();

        $this->assertSame(['Clause 6'], $iso('Clause 6.1.4 AI system impact assessment'));
        $this->assertSame(['Clause 4', 'Clause 5', 'Clause 7'], $iso('Clauses 4–5 and 7'));
        $this->assertSame(['Clause 8', 'Clause 10'], $iso('Clauses 8 and 10'));
        $this->assertSame(['Annex A', 'Clause 9'], $iso('Clause 9 Performance evaluation; Annex A verification controls'));
        $this->assertSame(['GOVERN', 'MANAGE'], $nist('GOVERN 5.x, MANAGE 4.x'));
        $this->assertSame([], $nist('Whole framework (named in the statute)'));
    }

    public function test_no_mapping_is_dropped_from_a_framework_page(): void
    {
        $crosswalk = app(FrameworkCrosswalk::class);

        foreach (['iso_42001', 'nist_ai_rmf'] as $key) {
            $data = $crosswalk->framework($key);
            $listed = $data['families']->flatten(1)->pluck('id')->unique()->count();

            $this->assertSame($data['mappings'], $listed, "Every {$key} mapping must appear under some heading.");
        }
    }
}
