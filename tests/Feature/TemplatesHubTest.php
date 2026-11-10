<?php

namespace Tests\Feature;

use App\Services\Assessments\AssessmentCatalog;
use App\Services\Templates\TemplateCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The templates hub, a template page and a landing page: getting around them, and the self-assessments beside them. */
class TemplatesHubTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('policy:import');
    }

    /** @return list<string> */
    private function anchors(string $html, string $navMarker): array
    {
        preg_match('#<nav[^>]*'.preg_quote($navMarker, '#').'[^>]*>(.*?)</nav>#s', $html, $nav);
        preg_match_all('#href="\#([a-z0-9-]+)"#', $nav[1] ?? '', $m);

        return $m[1];
    }

    public function test_the_hub_has_a_jump_link_to_every_group_it_renders(): void
    {
        $html = $this->get(route('templates.index'))->assertOk()->getContent();
        $anchors = $this->anchors($html, 'data-template-jump');

        $types = TemplateCatalog::all()->pluck('type')->unique()->values();
        $this->assertGreaterThan(3, $types->count());
        foreach ($types as $type) {
            $this->assertContains('type-'.$type, $anchors);
        }
        foreach ($anchors as $id) {
            $this->assertSame(1, substr_count($html, 'id="'.$id.'"'), "the jump link #{$id} has exactly one target");
        }
    }

    public function test_the_filter_is_closed_until_it_is_used_and_each_group_links_its_landing_page(): void
    {
        $html = $this->get(route('templates.index'))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('#<details class="[^"]*" data-template-filters\s*>#', $html);
        foreach (collect(TemplateCatalog::facets()) as $facet) {
            $this->assertStringContainsString('href="'.$facet['url'].'"', $html, 'the hub links the '.$facet['label'].' landing page');
        }

        $filtered = $this->get(route('templates.index', ['type' => 'register', 'topic' => 'inventory']))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('#<details class="[^"]*" data-template-filters\s+open\s*>#', $filtered);
        $this->assertStringNotContainsString('id="self-assessments"', $filtered, 'a filtered list shows templates only');
    }

    public function test_the_hub_pairs_a_self_assessment_with_the_template_it_goes_with(): void
    {
        $html = $this->get(route('templates.index'))->assertOk()->getContent();
        $paired = AssessmentCatalog::pairedWithTemplates(fn (string $slug) => TemplateCatalog::find($slug));

        $this->assertGreaterThanOrEqual(3, $paired->count());
        $this->assertGreaterThan(1, $paired->pluck('type')->unique()->count(), 'the assessments shown are not all of one type');
        preg_match('#<section id="self-assessments".*?</section>#s', $html, $section);
        $this->assertNotEmpty($section);
        foreach ($paired as $slug => $a) {
            $this->assertStringContainsString('href="'.e($a['url']).'"', $section[0], $slug);
            $this->assertStringContainsString('href="'.route('templates.show', $a['template']['slug']).'"', $section[0], $slug);
        }
        // Who runs them is said where they are offered, and they are not presented as an audit.
        $this->assertStringContainsString(config('assessments.provider.disclosure'), $section[0]);
        $this->assertStringContainsString('not an audit', $section[0]);
        $this->assertStringContainsString('All '.AssessmentCatalog::all()->count().' free self-assessments', $section[0]);
        $this->assertStringContainsString('Is there a self-assessment to go with the templates?', $html);
    }

    public function test_a_template_page_links_every_section_it_renders_and_answers_which_assessment_fits(): void
    {
        $slug = 'ai-system-inventory';
        $html = $this->get(route('templates.show', $slug))->assertOk()->getContent();

        $anchors = $this->anchors($html, 'data-template-jump');
        $this->assertGreaterThanOrEqual(5, count($anchors));
        foreach ($anchors as $id) {
            $this->assertSame(1, substr_count($html, 'id="'.$id.'"'), "the jump link #{$id} has exactly one target");
        }

        $fits = AssessmentCatalog::for('template', $slug, 2);
        $this->assertNotEmpty($fits->all());
        $this->assertStringContainsString('Is there a self-assessment that goes with it?', $html);
        foreach ($fits as $a) {
            $this->assertStringContainsString(e($a['title']).' ('.$a['questions'].' questions, about '.$a['minutes'].' minutes)', $html);
        }

        // A template nothing is attached to does not claim one.
        $attached = AssessmentCatalog::all()->flatMap(fn ($a) => $a['templates'] ?? [])->unique();
        $bare = TemplateCatalog::all()->pluck('slug')->first(fn ($s) => ! $attached->contains($s));
        if ($bare !== null) {
            $this->get(route('templates.show', $bare))->assertOk()->assertDontSee('Is there a self-assessment that goes with it?');
        }
    }

    public function test_a_landing_page_offers_the_assessments_for_its_templates(): void
    {
        $landing = collect(TemplateCatalog::facets())->firstWhere('facet', 'framework');
        $this->assertNotNull($landing);
        $slugs = TemplateCatalog::filter(TemplateCatalog::all(), ['framework' => $landing['key']])->pluck('slug')->all();
        $fits = AssessmentCatalog::forTemplates($slugs);
        $this->assertNotEmpty($fits->all());

        $html = $this->get($landing['url'])->assertOk()->getContent();
        foreach ($fits as $a) {
            $this->assertStringContainsString('href="'.e($a['url']).'"', $html);
        }
        $this->assertStringContainsString('on Certifyi, a related product', $html);
    }

    public function test_an_assessment_served_at_another_address_links_there_and_is_attached_to_no_page(): void
    {
        $slug = 'nrb-risk-management-guidelines-2026';
        $a = AssessmentCatalog::all()->get($slug);

        $this->assertNotNull($a);
        $this->assertStringStartsWith(config('assessments.provider.assessment_url').$a['path'].'?utm_source=aipolicytracker', $a['url']);
        foreach (AssessmentCatalog::KINDS as $field) {
            $this->assertArrayNotHasKey($field, $a, 'the guideline is not a record on this site');
        }
        $this->get(route('assessments.index'))->assertOk()->assertSee($a['title'])->assertSee(e($a['url']), false);
        $this->get(route('assessments.index', ['region' => 'Nepal']))->assertOk()->assertSee($a['title']);
    }
}
