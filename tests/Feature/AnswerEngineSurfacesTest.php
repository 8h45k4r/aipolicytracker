<?php

namespace Tests\Feature;

use App\Models\ChangeEvent;
use App\Models\Control;
use App\Models\PolicyInstrument;
use App\Services\Seo\IndexNow;
use App\Services\Templates\TemplateCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AnswerEngineSurfacesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('policy:import');
    }

    /** @return list<array<string,mixed>> */
    private function jsonLd(string $html): array
    {
        preg_match_all('#<script type="application/ld\+json"[^>]*>(.*?)</script>#s', $html, $m);

        return array_map(fn ($j) => json_decode($j, true, 512, JSON_THROW_ON_ERROR), $m[1]);
    }

    public function test_the_glossary_is_a_defined_term_set_with_an_anchor_per_term_and_working_links(): void
    {
        $html = $this->get('/glossary')->assertOk()->getContent();
        $set = collect($this->jsonLd($html))->firstWhere('@type', 'DefinedTermSet');

        $this->assertNotNull($set);
        $terms = collect($set['hasDefinedTerm']);
        $this->assertGreaterThanOrEqual(30, $terms->count(), 'curated terms plus the defined vocabulary');
        foreach ($terms as $term) {
            $this->assertSame('DefinedTerm', $term['@type']);
            $anchor = substr($term['@id'], strpos($term['@id'], '#') + 1);
            $this->assertStringContainsString('id="'.$anchor.'"', $html, "the {$term['name']} anchor exists on the page");
        }
        $this->assertStringContainsString('id="deployer"', $html, 'actors use their bare slug, so /glossary#deployer works');
        $this->assertStringContainsString('<dfn', $html);

        // Every internal "see" link resolves: a glossary that points at 404s teaches nothing.
        preg_match_all('#href="(http://[^"]+)"[^>]*>[^<]+ →</a>#', $html, $links);
        $this->assertNotEmpty($links[1]);
        foreach (array_unique($links[1]) as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_the_glossary_and_measures_are_in_the_sitemap_and_llms_txt(): void
    {
        $this->assertStringContainsString(route('glossary'), $this->get(route('sitemap.section', 'static'))->getContent());

        $llms = $this->get('/llms.txt')->assertOk()->getContent();
        $this->assertStringContainsString(route('glossary'), $llms);
        foreach (TemplateCatalog::all()->keys()->take(5) as $slug) {
            $this->assertStringContainsString(TemplateCatalog::url($slug), $llms, "template {$slug} is listed in llms.txt");
        }
        $this->assertStringContainsString(route('compare.show', 'eu-vs-uk-ai-regulation'), $llms);
    }

    public function test_curated_comparisons_without_written_questions_carry_computed_ones(): void
    {
        $this->assertSame([], config('content.comparisons.eu-vs-uk-ai-regulation.faq'), 'the case this covers');
        $html = $this->get(route('compare.show', 'eu-vs-uk-ai-regulation'))->assertOk()->getContent();

        $faq = collect($this->jsonLd($html))->firstWhere('@type', 'FAQPage');
        $this->assertNotNull($faq);
        $this->assertStringContainsString('stricter AI rules', $faq['mainEntity'][0]['name']);
        $this->assertStringContainsString($faq['mainEntity'][0]['name'], html_entity_decode($html), 'the questions are visible, not only in the markup');
    }

    public function test_control_and_change_pages_open_with_an_answer(): void
    {
        $control = Control::published()->whereHas('obligations')->firstOrFail();
        $html = $this->get($control->url())->assertOk()->getContent();
        $this->assertStringContainsString('data-answer-box', $html);
        $this->assertMatchesRegularExpression('/\d+ recorded (duty|duties) in \d+ jurisdiction/', $html);

        $change = ChangeEvent::published()->whereNotNull('official_source_url')->where('slug', 'not like', 'template-%')->orderByDesc('occurred_on')->firstOrFail();
        $html = $this->get($change->url())->assertOk()->getContent();
        $this->assertStringContainsString('data-answer-box', $html);
        $this->assertStringContainsString('Recorded for', $html);
    }

    public function test_the_organisation_states_its_editorial_policies_and_verified_records_their_review_date(): void
    {
        $html = $this->get('/')->assertOk()->getContent();
        $org = collect($this->jsonLd($html))->firstWhere('@type', 'Organization');
        $this->assertSame(route('methodology'), $org['publishingPrinciples']);
        $this->assertSame(route('corrections'), $org['correctionsPolicy']);

        $policy = PolicyInstrument::published()->where('review_status', 'verified')->whereNotNull('last_verified_at')->whereNotNull('reviewed_by')->firstOrFail();
        $html = $this->get($policy->url())->assertOk()->getContent();
        $page = collect($this->jsonLd($html))->first(fn ($n) => isset($n['reviewedBy']));
        $this->assertSame($policy->last_verified_at->format('Y-m-d'), $page['lastReviewed']);
    }

    public function test_template_markup_says_how_the_files_are_obtained(): void
    {
        $this->artisan('templates:build');
        $slug = TemplateCatalog::all()->keys()->first();
        $html = $this->get(TemplateCatalog::url($slug))->assertOk()->getContent();
        $doc = collect($this->jsonLd($html))->firstWhere('@type', 'DigitalDocument');

        $this->assertStringContainsString('work email', $doc['conditionsOfAccess']);
        $this->assertSame(0, $doc['offers']['price']);
        $this->assertArrayNotHasKey('hasDigitalDocumentPermission', $doc);
    }

    public function test_robots_txt_gives_answer_engine_crawlers_the_same_rules_as_everyone(): void
    {
        $robots = file_get_contents(public_path('robots.txt'));
        preg_match_all('/^(?:Allow|Disallow): .+$/m', substr($robots, 0, strpos($robots, '# Answer-engine')), $general);
        preg_match_all('/^(?:Allow|Disallow): .+$/m', substr($robots, strpos($robots, 'User-agent: GPTBot')), $named);

        $this->assertSame($general[0], $named[0]);
    }

    public function test_the_indexnow_key_file_is_served_only_for_the_configured_key(): void
    {
        $this->get('/0123456789abcdef.txt')->assertNotFound();

        config(['services.indexnow.key' => '0123456789abcdef']);
        $this->get('/0123456789abcdef.txt')->assertOk()->assertSee('0123456789abcdef');
        $this->get('/fedcba9876543210.txt')->assertNotFound();
        $this->get('/llms.txt')->assertOk()->assertSee('# AIPolicyTracker', false);
    }

    public function test_an_import_submits_only_the_pages_whose_records_changed(): void
    {
        config(['services.indexnow.key' => '0123456789abcdef', 'app.url' => 'http://localhost']);
        Http::fake(['*' => Http::response('', 202)]);

        // Nothing changed since the import in setUp: nothing is sent.
        $this->travel(1)->hours();
        $this->artisan('policy:import')->assertSuccessful();
        Http::assertNothingSent();

        // One record differs from its file, so the next import writes it back: one page, plus the listings.
        $policy = PolicyInstrument::where('slug', 'eu-ai-act')->firstOrFail();
        $policy->forceFill(['summary_plain' => 'Stale'])->saveQuietly();
        $this->travel(1)->hours();
        $this->artisan('policy:import')->assertSuccessful();

        Http::assertSent(function (HttpRequest $request) use ($policy) {
            $urls = $request['urlList'];

            return $request['key'] === '0123456789abcdef'
                && $request['keyLocation'] === url('0123456789abcdef.txt')
                && in_array($policy->url(), $urls, true)
                && count($urls) <= 4;
        });
    }

    public function test_indexnow_does_nothing_without_a_valid_key(): void
    {
        Http::fake();
        $this->assertSame(0, app(IndexNow::class)->submit([url('/')]));
        config(['services.indexnow.key' => 'short']);
        $this->assertSame(0, app(IndexNow::class)->submit([url('/')]));
        Http::assertNothingSent();
    }
}
