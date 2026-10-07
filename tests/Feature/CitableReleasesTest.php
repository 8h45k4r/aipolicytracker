<?php

namespace Tests\Feature;

use App\Models\PolicyInstrument;
use App\Support\DatasetCitation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

/**
 * The dataset can be cited: CITATION.cff and .zenodo.json are well formed, and the DOI
 * appears in structured data and in the "Cite this record" box only once it has been
 * configured. An unset DOI must leave no trace, because a placeholder in a citation is
 * copied into reference lists.
 */
class CitableReleasesTest extends TestCase
{
    use RefreshDatabase;

    private const DOI = '10.5281/zenodo.1234567';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->artisan('policy:import');
    }

    public function test_citation_cff_parses_and_carries_the_cff_1_2_0_required_fields(): void
    {
        $cff = Yaml::parseFile(base_path('CITATION.cff'));

        $this->assertSame('1.2.0', $cff['cff-version']);
        $this->assertSame('dataset', $cff['type']);
        foreach (['message', 'title', 'authors'] as $required) {
            $this->assertNotEmpty($cff[$required] ?? null, "CITATION.cff lacks {$required}");
        }
        $this->assertSame('CC-BY-4.0', $cff['license']);
        $this->assertSame([['given-names' => 'Bhaskar', 'family-names' => 'Bhatt']], array_map(fn ($a) => array_intersect_key($a, array_flip(['given-names', 'family-names'])), $cff['authors']));
        $this->assertStringContainsString('Apache-2.0', $cff['abstract'], 'the code licence is stated alongside the data licence');
        $this->assertSame('https://aipolicytracker.org', $cff['url']);
        $this->assertStringStartsWith('https://github.com/', $cff['repository-code']);
        // Keys outside the CFF 1.2.0 schema make the file invalid.
        $allowed = ['abstract', 'authors', 'cff-version', 'commit', 'contact', 'date-released', 'doi', 'identifiers', 'keywords', 'license', 'license-url', 'message', 'preferred-citation', 'references', 'repository', 'repository-artifact', 'repository-code', 'title', 'type', 'url', 'version'];
        $this->assertSame([], array_values(array_diff(array_keys($cff), $allowed)));
        $this->assertArrayNotHasKey('doi', $cff, 'no DOI until Zenodo has minted one');
    }

    public function test_zenodo_metadata_is_valid_json_for_a_cc_by_dataset(): void
    {
        $zenodo = json_decode((string) file_get_contents(base_path('.zenodo.json')), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('dataset', $zenodo['upload_type']);
        $this->assertSame('cc-by-4.0', $zenodo['license']);
        $this->assertSame([['name' => 'Bhatt, Bhaskar']], $zenodo['creators']);
        $this->assertNotEmpty($zenodo['keywords']);
        $this->assertContains('https://aipolicytracker.org', array_column($zenodo['related_identifiers'], 'identifier'));
    }

    /** Every Dataset node on a page, wherever it sits in the graph. @return list<array> */
    private function datasets(string $url): array
    {
        $html = $this->get($url)->assertOk()->getContent();
        preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $html, $m);
        $found = [];
        $walk = function ($node) use (&$walk, &$found) {
            if (! is_array($node)) {
                return;
            }
            if (($node['@type'] ?? null) === 'Dataset') {
                $found[] = $node;
            }
            foreach ($node as $child) {
                $walk($child);
            }
        };
        foreach ($m[1] as $json) {
            $walk(json_decode($json, true, 512, JSON_THROW_ON_ERROR));
        }

        return $found;
    }

    public function test_no_doi_renders_anywhere_when_none_is_configured(): void
    {
        config(['aipolicytracker.dataset_doi' => null]);
        $policy = PolicyInstrument::published()->firstOrFail();

        foreach ([route('open-data'), $policy->url(), '/'] as $url) {
            $this->get($url)->assertOk()->assertDontSee('doi.org', false)->assertDontSee('data-dataset-doi', false)->assertDontSee('data-cite-doi', false);
        }
        // The BibTeX block is there without a doi field.
        $html = $this->get($policy->url())->getContent();
        $this->assertMatchesRegularExpression('#data-cite-bibtex>@misc\{#', $html);
        $this->assertDoesNotMatchRegularExpression('#^\s*doi\s*=#m', html_entity_decode($html));
    }

    public function test_a_malformed_doi_is_treated_as_unset(): void
    {
        config(['aipolicytracker.dataset_doi' => 'TBD']);
        $this->assertNull(DatasetCitation::doi());
        $this->get(route('open-data'))->assertOk()->assertDontSee('doi.org', false);

        config(['aipolicytracker.dataset_doi' => 'https://doi.org/'.self::DOI]);
        $this->assertSame(self::DOI, DatasetCitation::doi(), 'a resolver URL is reduced to the bare DOI');
    }

    public function test_a_configured_doi_identifies_the_corpus_dataset_and_is_cited_on_records(): void
    {
        config(['aipolicytracker.dataset_doi' => self::DOI]);
        $policy = PolicyInstrument::published()->firstOrFail();

        // /open-data: the corpus Dataset carries the DOI as identifier and sameAs.
        $corpus = collect($this->datasets(route('open-data')))->firstWhere('url', route('open-data'));
        $this->assertNotNull($corpus);
        $this->assertSame(['@type' => 'PropertyValue', 'propertyID' => 'DOI', 'value' => self::DOI, 'url' => 'https://doi.org/'.self::DOI], $corpus['identifier']);
        $this->assertSame('https://doi.org/'.self::DOI, $corpus['sameAs']);

        // A record's Dataset is declared part of the DOI'd corpus; the DOI is not its own.
        $record = collect($this->datasets($policy->url()))->firstWhere('url', $policy->url());
        $this->assertNotNull($record);
        $this->assertNotSame(self::DOI, $record['identifier']['value'] ?? null);
        $parent = collect($record['isPartOf'])->firstWhere('@type', 'Dataset');
        $this->assertSame(self::DOI, $parent['identifier']['value']);
        $this->assertSame('https://doi.org/'.self::DOI, $parent['sameAs']);

        // The cite box names the DOI and the BibTeX carries it.
        $html = html_entity_decode($this->get($policy->url())->assertOk()->getContent());
        $this->assertStringContainsString('data-cite-doi>https://doi.org/'.self::DOI.'<', $html);
        $this->assertMatchesRegularExpression('#^\s*doi\s*=\s*\{'.preg_quote(self::DOI, '#').'\}#m', $html);
        $this->assertMatchesRegularExpression('#^\s*note\s*=\s*\{Data licensed CC BY 4\.0#m', $html);

        $this->get(route('open-data'))->assertSee('data-dataset-doi', false);
    }

    public function test_bibtex_escapes_markup_characters_in_titles(): void
    {
        config(['aipolicytracker.dataset_doi' => null]);
        $bib = DatasetCitation::bibtex('R&D 100% {draft} #1_a', 'https://aipolicytracker.org/x', new \DateTimeImmutable('2026-10-07'));

        $this->assertStringContainsString('title        = {{R\&D 100\% \{draft\} \#1\_a}}', $bib);
        $this->assertStringContainsString('year         = {2026}', $bib);
        $this->assertStringContainsString('urldate      = {2026-10-07}', $bib);
        $this->assertStringNotContainsString('doi', $bib);
        $this->assertStringStartsWith('@misc{aipolicytracker2026_', $bib);
    }
}
