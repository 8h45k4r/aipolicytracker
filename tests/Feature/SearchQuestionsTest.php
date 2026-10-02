<?php

namespace Tests\Feature;

use App\Models\Jurisdiction;
use App\Services\Templates\TemplateCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The questions people type into search engines about this niche, answered on the page
 * they land on, from the records, in visible text and in FAQPage markup.
 */
class SearchQuestionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('policy:import');
    }

    /** @return array<string,string> question => answer, from the FAQPage markup */
    private function faq(string $url): array
    {
        $html = $this->get($url)->assertOk()->getContent();
        $this->assertSame(1, substr_count($html, 'id="faq-heading"'), "{$url} shows its questions once");
        preg_match_all('#<script type="application/ld\+json"[^>]*>(.*?)</script>#s', $html, $m);
        foreach ($m[1] as $json) {
            $node = json_decode($json, true);
            foreach (isset($node['@graph']) ? $node['@graph'] : [$node] as $n) {
                if (($n['@type'] ?? null) === 'FAQPage') {
                    $out = [];
                    foreach ($n['mainEntity'] as $q) {
                        $out[$q['name']] = $q['acceptedAnswer']['text'];
                        $this->assertStringContainsString(e($q['name']), $html, 'every marked-up question is visible');
                    }

                    return $out;
                }
            }
        }
        $this->fail("{$url} has no FAQPage");
    }

    public function test_which_countries_have_ai_laws_is_answered_from_the_records(): void
    {
        $faq = $this->faq(route('jurisdictions.index'));
        $this->assertArrayHasKey('Which countries have AI laws?', $faq);
        $this->assertStringStartsWith('European Union', explode(': ', $faq['Which countries have AI laws?'], 2)[1], 'supranational bodies lead the list');
    }

    public function test_the_eu_ai_act_page_answers_the_searched_questions_with_the_current_dates(): void
    {
        $faq = $this->faq(route('policies.show', 'eu-ai-act'));

        $this->assertStringContainsString('2 December 2027', $faq['When does the EU AI Act apply?']);
        $this->assertStringNotContainsString('2 August 2027', $faq['When does the EU AI Act apply?'], 'the superseded date is gone');
        $this->assertArrayNotHasKey('When do the EU AI Act requirements apply?', $faq, 'one dates question, not two that disagree');
        $this->assertStringContainsString('Article 2', $faq['Does the EU AI Act apply to companies outside the EU?']);
        $this->assertArrayHasKey('What is a high-risk AI system under the EU AI Act?', $faq);
        $this->assertStringContainsString('Article 5', $faq['What does the EU AI Act prohibit?']);
    }

    public function test_the_us_page_answers_federal_and_state_questions_from_the_records(): void
    {
        $faq = $this->faq(Jurisdiction::where('slug', 'us')->firstOrFail()->url());

        $this->assertArrayHasKey('Is there a federal AI law in the United States?', $faq);
        $states = $faq['Which states in the United States have AI laws?'];
        $this->assertStringContainsString('Texas', $states);
        $this->assertStringContainsString('Colorado ADMT', $states);
        $this->assertStringNotContainsString('SB 24-205', implode(' ', $faq), 'the repealed Colorado act is not offered as current');
        $this->assertStringNotContainsString('(United States)', $states);
    }

    public function test_the_templates_library_says_what_each_core_document_contains(): void
    {
        $this->artisan('templates:build');
        $faq = $this->faq(route('templates.index'));

        $this->assertStringContainsString('risk domain (MIT)', $faq['What should an AI risk register include?']);
        $this->assertStringContainsString('prohibited uses', $faq['What should an AI acceptable use policy include?']);
        $this->assertStringContainsString(TemplateCatalog::url('ai-impact-assessment'), $faq['What goes into an AI impact assessment?']);
        $this->assertArrayHasKey('What should an AI system inventory record?', $faq);
    }
}
