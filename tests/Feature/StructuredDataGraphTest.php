<?php

namespace Tests\Feature;

use App\Models\Jurisdiction;
use App\Models\Obligation;
use App\Models\PolicyInstrument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Structured data was per-page and partly dangling. `Organization` and `WebSite`
 * were emitted on the homepage alone, while every inner page referenced
 * `#organization` and `#website` identifiers that resolved to nothing when that
 * page was fetched on its own — and a record page fetched on its own is exactly
 * how an answer engine reads this site. Thirteen page types carried nothing but
 * a breadcrumb trail.
 *
 * These assert the properties rather than the markup: that the graph closes on
 * every page, that each page describes itself exactly once, and that a page which
 * enumerates records says which ones.
 */
class StructuredDataGraphTest extends TestCase
{
    use RefreshDatabase;

    /** Page types that describe the page itself; exactly one is allowed per page. */
    private const PAGE_TYPES = [
        'WebPage', 'CollectionPage', 'AboutPage', 'ContactPage', 'ProfilePage',
        'ItemPage', 'SearchResultsPage', 'CheckoutPage', 'Article', 'NewsArticle', 'BlogPosting',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->artisan('policy:import');
    }

    /** @return list<array<string,mixed>> */
    private function schemas(string $url): array
    {
        $html = $this->get($url)->assertOk()->getContent();
        preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $html, $m);
        $this->assertNotEmpty($m[1], "no structured data on {$url}");

        return array_map(fn ($json) => json_decode($json, true, 512, JSON_THROW_ON_ERROR), $m[1]);
    }

    /** Every public page shape the site serves, by URL. */
    private function everyPageShape(): array
    {
        $policy = PolicyInstrument::published()->firstOrFail();
        $jurisdiction = Jurisdiction::published()->firstOrFail();
        $obligation = Obligation::published()->firstOrFail();
        $guide = array_key_first(config('content.guides', []));

        return array_filter([
            'home' => '/',
            'policies index' => route('policies.index'),
            'policy' => $policy->url(),
            'jurisdictions index' => route('jurisdictions.index'),
            'jurisdiction' => $jurisdiction->url(),
            'obligations index' => route('obligations.index'),
            'obligation' => $obligation->url(),
            'compare' => route('compare.index'),
            'changes' => route('changes.index'),
            'calendar' => route('calendar'),
            'risk' => route('risk.index'),
            'applicability' => route('tools.applicability'),
            'open data' => route('open-data'),
            'methodology' => route('methodology'),
            'verification' => route('verification'),
            'coverage' => route('coverage'),
            'gaps' => route('gaps'),
            'corrections' => route('corrections'),
            'reviewers' => route('reviewers'),
            'about' => route('about'),
            'contribute' => route('contribute'),
            'subscribe' => route('subscribe.show'),
            'guides index' => route('guides.index'),
            'guide' => $guide ? route('guides.show', $guide) : null,
            'privacy' => route('privacy'),
            'terms' => route('terms'),
        ]);
    }

    public function test_every_page_carries_the_publisher_and_the_site_it_belongs_to(): void
    {
        foreach ($this->everyPageShape() as $label => $url) {
            $types = array_column($this->schemas($url), '@type');

            $this->assertContains('Organization', $types, "no Organization on {$label}");
            $this->assertContains('WebSite', $types, "no WebSite on {$label}");
        }
    }

    public function test_every_page_describes_itself_exactly_once(): void
    {
        foreach ($this->everyPageShape() as $label => $url) {
            $pageNodes = array_values(array_filter(
                $this->schemas($url),
                fn ($s) => in_array($s['@type'] ?? '', self::PAGE_TYPES, true)
            ));

            $this->assertCount(1, $pageNodes, "{$label} should have exactly one page node, found ".count($pageNodes));

            $node = $pageNodes[0];
            $this->assertStringEndsWith('#webpage', $node['@id'] ?? '', "the page node on {$label} has no identifier");
            $this->assertSame('en', $node['inLanguage'] ?? null, "no language stated on {$label}");
            // `isPartOf` may name more than the site: an obligation is also part of
            // the instrument it was read out of, which is worth saying. The site must
            // be among them either way.
            $partOf = $node['isPartOf'] ?? [];
            $partOfIds = isset($partOf['@id']) ? [$partOf['@id']] : array_column($partOf, '@id');
            $this->assertContains(url('/').'#website', $partOfIds, "{$label} is not attached to the site");
            $this->assertSame(url('/').'#organization', $node['publisher']['@id'] ?? null, "no publisher on {$label}");
            $this->assertNotEmpty($node['name'] ?? '', "no name on {$label}");
            $this->assertNotEmpty($node['description'] ?? '', "no description on {$label}");
        }
    }

    public function test_no_page_references_an_identifier_it_does_not_define(): void
    {
        // The actual bug this replaced: `isPartOf: #website` on a record page
        // pointed at a node that only existed on the homepage.
        foreach ($this->everyPageShape() as $label => $url) {
            $schemas = $this->schemas($url);

            $defined = [];
            $referenced = [];
            $walk = function ($value) use (&$walk, &$defined, &$referenced) {
                if (! is_array($value)) {
                    return;
                }
                // A map whose only key is @id is a reference; anything else that
                // carries an @id is defining one.
                if (isset($value['@id']) && is_string($value['@id'])) {
                    if (array_keys($value) === ['@id']) {
                        $referenced[] = $value['@id'];
                    } else {
                        $defined[] = $value['@id'];
                    }
                }
                foreach ($value as $child) {
                    $walk($child);
                }
            };
            foreach ($schemas as $schema) {
                $walk($schema);
            }

            $dangling = array_values(array_unique(array_diff($referenced, $defined)));
            $this->assertSame([], $dangling, "{$label} references identifiers it does not define: ".implode(', ', $dangling));
        }
    }

    public function test_every_block_on_every_page_is_valid_and_typed(): void
    {
        foreach ($this->everyPageShape() as $label => $url) {
            foreach ($this->schemas($url) as $schema) {
                $this->assertSame('https://schema.org', $schema['@context'] ?? null, "a block on {$label} has no context");
                $this->assertNotEmpty($schema['@type'] ?? '', "an untyped block on {$label}");
            }
        }
    }

    public function test_a_record_page_offers_its_record_as_an_openly_licensed_dataset(): void
    {
        $policy = PolicyInstrument::published()->firstOrFail();

        $dataset = collect($this->schemas($policy->url()))->firstWhere('@type', 'Dataset');
        $this->assertNotNull($dataset, 'a record page should describe the record behind it');

        $this->assertSame(config('aipolicytracker.data_license_url'), $dataset['license']);
        $this->assertTrue($dataset['isAccessibleForFree']);
        $this->assertSame(url('/').'#organization', $dataset['publisher']['@id']);

        $urls = array_column($dataset['distribution'], 'contentUrl');
        $this->assertContains(route('policies.json', $policy->slug), $urls);
        $this->assertContains(route('policies.context', $policy->slug), $urls);

        // And the files it advertises actually serve something.
        foreach ($urls as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_a_page_that_lists_records_says_which_ones(): void
    {
        $cases = [
            route('policies.index') => PolicyInstrument::published()->count(),
            route('jurisdictions.index') => Jurisdiction::published()->count(),
        ];

        foreach ($cases as $url => $expected) {
            $list = collect($this->schemas($url))
                ->map(fn ($s) => $s['mainEntity'] ?? null)
                ->first(fn ($e) => is_array($e) && ($e['@type'] ?? '') === 'ItemList');

            $this->assertNotNull($list, "no item list on {$url}");
            $this->assertGreaterThan(0, $list['numberOfItems'] ?? 0);
            $this->assertSame($list['numberOfItems'], count($list['itemListElement']), 'the stated count must match the list');
            $this->assertNotEmpty($list['itemListElement'][0]['url'] ?? '', 'a listed record must be reachable');
            $this->assertSame(1, $list['itemListElement'][0]['position']);
        }
    }

    public function test_what_the_publisher_claims_to_cover_comes_from_what_is_published(): void
    {
        // A global remit the corpus does not support would be the same overclaim
        // this project exists to avoid, in machine-readable form.
        $organization = collect($this->schemas('/'))->firstWhere('@type', 'Organization');

        $claimed = array_column($organization['areaServed'] ?? [], 'name');
        $recorded = Jurisdiction::published()->whereNotNull('region')->distinct()->pluck('region')->filter()->values()->all();

        $this->assertNotEmpty($claimed, 'the publisher should say what it covers');
        sort($claimed);
        sort($recorded);
        $this->assertSame($recorded, $claimed, 'every region claimed must have published jurisdictions in it');

        $this->assertContains('AI regulation', $organization['knowsAbout'] ?? []);
    }

    public function test_every_dataset_the_site_publishes_carries_the_fields_that_make_it_usable(): void
    {
        // Search Console reports a Dataset without a description as a missing
        // required field, and it is right to: a dataset a machine cannot summarise
        // is a dataset it will not cite. Two of the four on the research pages had
        // no description at all.
        $pages = [
            route('risk.index'), route('risk.incidents'), route('risk.incidents.browse'),
            route('risk.risks'), route('open-data'), route('calendar'), '/',
        ];

        $found = 0;
        foreach ($pages as $url) {
            foreach ($this->schemas($url) as $schema) {
                if (($schema['@type'] ?? '') !== 'Dataset') {
                    continue;
                }
                $found++;
                foreach (['name', 'description', 'url'] as $field) {
                    $this->assertNotEmpty($schema[$field] ?? '', "a Dataset on {$url} has no {$field}");
                }
                $this->assertGreaterThan(40, mb_strlen($schema['description']), "the Dataset description on {$url} says too little to be useful");
                $this->assertArrayHasKey('license', $schema, "a Dataset on {$url} does not state its licence");
            }
        }

        $this->assertGreaterThanOrEqual(6, $found, 'the research and open-data pages should each describe their dataset');
    }

    public function test_the_research_corpus_is_in_a_sitemap(): void
    {
        // Roughly 4,000 incident and risk pages were reachable, indexable and in no
        // sitemap, which is most of what Search Console reported as discovered and
        // not indexed. A page nothing points a crawler at is a page nobody finds.
        // Two rows rather than the whole weekly import: the mechanism under test is
        // whether the sitemap enumerates the table, not how big the table is.
        \App\Models\ExternalIncident::create([
            'incident_id' => 4242, 'title' => 'Recruitment model rejected applicants by postcode',
            'occurred_on' => '2026-03-04', 'year' => 2026, 'report_count' => 3,
            'snapshot_date' => '2026-09-07', 'mit_domain' => '1', 'mit_subdomain' => '1.1',
        ]);
        \App\Models\ExternalRisk::create([
            'ev_id' => '99.01.00', 'quick_ref' => 'Test2026', 'paper_title' => 'A framework used in this test',
            'level' => 'Risk Category', 'domain' => '1', 'subdomain' => '1.1',
        ]);

        $index = $this->get('/sitemap.xml')->assertOk()->getContent();
        $this->assertStringContainsString(route('sitemap.section', 'incidents'), $index);
        $this->assertStringContainsString(route('sitemap.section', 'risks'), $index);

        foreach (['incidents' => \App\Models\ExternalIncident::class, 'risks' => \App\Models\ExternalRisk::class] as $section => $model) {
            $xml = $this->get(route('sitemap.section', $section))->assertOk()
                ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')->getContent();

            $expected = $model::query()->count();
            $this->assertSame($expected, substr_count($xml, '<loc>'), "the {$section} sitemap does not list every record");
            // Every entry carries a date, so a crawler can tell what has moved.
            $this->assertSame($expected, substr_count($xml, '<lastmod>'), "an entry in the {$section} sitemap has no lastmod");

            $first = $model::query()->first();
            $this->assertStringContainsString('<loc>'.$first->url().'</loc>', $xml);
            // And the page it points at is actually served.
            $this->get($first->url())->assertOk();
        }
    }

    public function test_the_breadcrumb_trail_is_addressable_from_the_page_node(): void
    {
        $policy = PolicyInstrument::published()->firstOrFail();
        $schemas = $this->schemas($policy->url());

        $page = collect($schemas)->first(fn ($s) => in_array($s['@type'] ?? '', self::PAGE_TYPES, true));
        $crumbs = collect($schemas)->firstWhere('@type', 'BreadcrumbList');

        $this->assertNotNull($crumbs);
        $this->assertSame($crumbs['@id'], $page['breadcrumb']['@id'] ?? null, 'the page should point at its own trail');
    }
}
