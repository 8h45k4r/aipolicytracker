<?php

namespace Tests\Feature;

use App\Models\PolicyInstrument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** Search finds the record a reader names first, and offers suggestions while they type. */
class SearchRankingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('policy:import');
    }

    /** The URLs listed under "Laws and policies" on the results page, in order. */
    private function laws(string $q): array
    {
        $html = $this->get(route('search', ['q' => $q]))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('#>Laws and policies <span.*?</ul>#s', $html, "laws are listed for {$q}");
        preg_match('#>Laws and policies <span.*?</ul>#s', $html, $section);
        preg_match_all('#<li class="py-2.5"><a href="([^"]+)"#', $section[0], $m);

        return $m[1];
    }

    private function url(string $slug): string
    {
        return PolicyInstrument::where('slug', $slug)->firstOrFail()->url();
    }

    public static function namedRecords(): array
    {
        return [
            'short title' => ['eu ai act', 'eu-ai-act'],
            'short title without the jurisdiction' => ['ai act', 'eu-ai-act'],
            'any case and punctuation' => ['EU AI-Act', 'eu-ai-act'],
            'actor term, by its duties' => ['deployer', 'eu-ai-act'],
            'acronym, by its duties' => ['gpai', 'eu-ai-act'],
            'name in brackets in the title' => ['colorado ai act', 'us-colorado-ai-act'],
            'acronym short title' => ['nist ai rmf', 'us-nist-ai-rmf'],
            'acronym without the issuer' => ['ai rmf', 'us-nist-ai-rmf'],
            'words of the title in any spacing' => ['iso 42001', 'iso-iec-42001-2023-ai-management-system'],
        ];
    }

    #[DataProvider('namedRecords')]
    public function test_the_named_record_is_the_first_law_listed(string $q, string $slug): void
    {
        $this->assertSame($this->url($slug), $this->laws($q)[0] ?? null, "first law for \"{$q}\"");
    }

    public function test_in_force_laws_rank_above_repealed_and_proposed_ones_that_match_as_well(): void
    {
        $laws = $this->laws('ai act');
        $this->assertLessThan(array_search($this->url('us-colorado-ai-act'), $laws, true), array_search($this->url('eu-ai-act'), $laws, true));

        $laws = $this->laws('deployer');
        $this->assertContains($this->url('us-colorado-ai-act'), $laws);
        $this->assertSame($this->url('us-colorado-ai-act'), collect($laws)->last(fn ($u) => in_array($u, [$this->url('eu-ai-act'), $this->url('us-colorado-ai-act'), $this->url('us-texas-responsible-ai-governance-act-traiga')], true)), 'the repealed act comes after the laws in force with the same duties');
    }

    public function test_equal_matches_come_out_in_the_same_order_every_time(): void
    {
        $this->assertSame($this->laws('strategy'), $this->laws('strategy'));
    }

    public function test_a_plainly_named_record_is_shown_as_the_top_match(): void
    {
        $this->get(route('search', ['q' => 'EU AI Act']))->assertOk()
            ->assertSeeInOrder(['id="top-match"', 'href="'.$this->url('eu-ai-act').'"', '>Laws and policies <span'], false);
        $this->get(route('search', ['q' => 'deployer']))->assertOk()
            ->assertSeeInOrder(['id="top-match"', 'Deployer / user organisation', 'Glossary term'], false);
        $this->get(route('search', ['q' => 'strategy']))->assertOk()->assertDontSee('id="top-match"', false);
    }

    public function test_suggestions_are_a_short_cached_json_list_of_titles_types_and_links(): void
    {
        $response = $this->getJson(route('search.suggest', ['q' => 'ai']))->assertOk()
            ->assertJsonStructure(['query', 'search_url', 'items' => [['title', 'type', 'url']]]);
        $this->assertCount(8, $response->json('items'));
        $this->assertStringContainsString('public', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('max-age=600', $response->headers->get('Cache-Control'));

        $first = $this->getJson(route('search.suggest', ['q' => 'eu ai act']))->assertOk()->json('items.0');
        $this->assertSame(['title' => 'EU AI Act', 'type' => 'Law or policy', 'url' => $this->url('eu-ai-act')], $first);
        $urls = collect($this->getJson(route('search.suggest', ['q' => 'eu ai act']))->json('items'))->pluck('url');
        $this->assertSame($urls->unique()->values()->all(), $urls->all(), 'no link is suggested twice');
    }

    public function test_suggestions_need_two_characters_and_ignore_malformed_input(): void
    {
        $this->getJson(route('search.suggest', ['q' => 'a']))->assertOk()->assertJsonPath('items', []);
        $this->getJson(route('search.suggest'))->assertOk()->assertJsonPath('items', []);
        $this->getJson(route('search.suggest').'?q[]=eu')->assertOk()->assertJsonPath('items', []);
        $this->getJson(route('search.suggest', ['q' => 'zz%_qq']))->assertOk()->assertJsonPath('items', []);
    }

    public function test_the_search_boxes_point_the_typeahead_at_the_suggest_endpoint(): void
    {
        $this->get('/')->assertOk()->assertSee('data-suggest="'.route('search.suggest').'"', false);
        $this->get(route('search', ['q' => 'eu']))->assertOk()->assertSee('id="search-page-q" name="q" type="search" value="eu"', false)
            ->assertSee('data-suggest="'.route('search.suggest').'"', false);
    }
}
