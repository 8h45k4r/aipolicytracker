<?php

namespace Tests\Feature;

use App\Models\Obligation;
use App\Models\PolicyInstrument;
use App\Services\Templates\TemplateCatalog;
use App\Support\PageTitle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TemplatesAndGuidesSeoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('policy:import');
        $this->artisan('templates:build');
    }

    /** @return list<array<string,mixed>> */
    private function jsonLd(string $html): array
    {
        preg_match_all('#<script type="application/ld\+json"[^>]*>(.*?)</script>#s', $html, $m);

        return array_map(fn ($j) => json_decode($j, true, 512, JSON_THROW_ON_ERROR), $m[1]);
    }

    public function test_every_template_title_says_what_it_is_fits_and_is_unique(): void
    {
        $titles = TemplateCatalog::all()->map(fn ($m) => PageTitle::template($m));
        foreach ($titles as $slug => $title) {
            $this->assertMatchesRegularExpression('/\b(Template|Kit|Pack)\b/', $title, $slug);
            $this->assertLessThanOrEqual(PageTitle::MAX, mb_strlen($title), $slug);
            $this->assertStringNotContainsString('…', $title, "{$slug} is cut: give it a seo_name");
        }
        $this->assertSame($titles->count(), $titles->unique()->count());
    }

    public function test_a_template_page_answers_first_and_its_markup_matches_what_is_visible(): void
    {
        $slug = 'high-risk-deployer-compliance-pack';
        $html = $this->get(TemplateCatalog::url($slug))->assertOk()->getContent();

        $this->assertStringContainsString('data-answer-box', $html);
        $this->assertStringContainsString('Written for', $html, 'the audience comes from the duties it cites');

        preg_match('#<meta name="description" content="(.*?)"#', $html, $d);
        $description = html_entity_decode($d[1]);
        $this->assertLessThanOrEqual(155, mb_strlen($description));
        $this->assertStringContainsString('recorded duties', $description);

        $nodes = collect($this->jsonLd($html));
        $page = $nodes->firstWhere('@type', 'ItemPage');
        $this->assertSame('DigitalDocument', $page['mainEntity']['@type']);
        $this->assertStringEndsWith('#document', $page['mainEntity']['@id']);

        $howTo = $nodes->firstWhere('@type', 'HowTo');
        $this->assertNotNull($howTo);
        foreach ($howTo['step'] as $step) {
            $this->assertStringContainsString(e($step['name']), $html, 'every HowTo step is shown on the page');
        }

        $faq = $nodes->firstWhere('@type', 'FAQPage');
        $this->assertSame('What is in the '.TemplateCatalog::find($slug)['title'].'?', $faq['mainEntity'][0]['name'], 'the first question is about this template');

        $crumbs = $nodes->firstWhere('@type', 'BreadcrumbList')['itemListElement'];
        $this->assertSame(route('templates.facet', ['framework', 'eu-ai-act']), $crumbs[2]['item'], 'the framework landing page sits between the hub and the template');
    }

    public function test_every_template_is_somebody_s_related_template(): void
    {
        $linked = TemplateCatalog::all()->keys()->flatMap(fn ($slug) => TemplateCatalog::related($slug)->keys())->unique();

        $this->assertSame([], TemplateCatalog::all()->keys()->diff($linked)->values()->all());
    }

    public function test_framework_and_type_landing_pages_are_indexable_listed_and_reached_from_the_filters(): void
    {
        $landing = TemplateCatalog::facet('framework', 'eu-ai-act');
        $html = $this->get($landing['url'])->assertOk()->getContent();
        $this->assertStringContainsString('<title>'.e(PageTitle::templateFacet('EU AI Act', $landing['count'])), $html);
        $this->assertStringNotContainsString('noindex', $html);
        $list = collect($this->jsonLd($html))->firstWhere('@type', 'CollectionPage')['mainEntity'];
        $this->assertSame($landing['count'], $list['numberOfItems']);

        $this->get(route('templates.index', ['framework' => 'eu-ai-act']))->assertRedirect($landing['url'])->assertStatus(301);
        $this->get(route('templates.index', ['framework' => 'eu-ai-act', 'topic' => 'risk']))->assertOk();
        $this->get('/templates/type/crosswalk')->assertNotFound();
        $this->get('/templates/framework/not-a-framework')->assertNotFound();

        $sitemap = $this->get(route('sitemap.section', 'templates'))->getContent();
        foreach (TemplateCatalog::facets() as $f) {
            $this->assertStringContainsString($f['url'], $sitemap);
        }
        $this->assertStringContainsString($landing['url'], $this->get(route('templates.index'))->getContent());
    }

    public function test_policy_pages_link_the_templates_built_on_them_and_llms_full_lists_templates(): void
    {
        $html = $this->get(PolicyInstrument::where('slug', 'eu-ai-act')->firstOrFail()->url())->assertOk()->getContent();
        $this->assertStringContainsString('Templates built on this law', $html);
        $this->assertStringContainsString(TemplateCatalog::url(TemplateCatalog::forPolicy('eu-ai-act')->keys()->first()), $html);
        $this->assertTrue(in_array('eu-ai-act', TemplateCatalog::forPolicy('eu-ai-act')->first()['covers']['policies'] ?? [], true), 'templates built for the law rank first');

        $full = $this->get('/llms-full.txt')->assertOk()->getContent();
        $this->assertStringContainsString('## Templates ('.TemplateCatalog::all()->count().')', $full);
    }

    public function test_every_guide_cites_records_that_exist_and_links_templates(): void
    {
        foreach (config('content.guides') as $slug => $guide) {
            foreach ($guide['policies'] ?? [] as $policy) {
                $this->assertTrue(PolicyInstrument::published()->where('slug', $policy)->exists(), "Guide {$slug} cites policy {$policy}, which is not a published record.");
            }
            foreach ($guide['obligations'] ?? [] as $obligation) {
                $this->assertTrue(Obligation::published()->where('slug', $obligation)->exists(), "Guide {$slug} cites obligation {$obligation}, which is not a published record.");
            }
            $this->assertLessThanOrEqual(60, mb_strlen($guide['title']), "Guide {$slug} title is longer than a search result shows");
        }

        $html = $this->get(route('guides.show', 'eu-ai-act-digital-omnibus-new-deadlines'))->assertOk()->getContent();
        $this->assertStringContainsString('Templates for this', $html);
        $this->assertStringContainsString('2 December 2027', $html);
        $this->assertStringContainsString(route('guides.show', 'colorado-ai-act-repeal-sb-26-189'), $this->get('/llms.txt')->getContent());
    }

    public function test_no_guide_repeats_the_dates_the_omnibus_replaced_or_cites_the_repealed_colorado_act_as_current(): void
    {
        $text = json_encode(config('content.guides'));
        $this->assertStringNotContainsString('most high-risk obligations land in August 2026', $text);
        $this->assertStringNotContainsString('2025 Digital Omnibus proposal', $text);
        $this->assertStringNotContainsString('us-colorado-deployer-impact-assessment', $text);
    }
}
