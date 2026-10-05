<?php

namespace Tests\Feature;

use App\Services\Glossary\GlossaryTerms;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Each curated glossary term has a page answering "what is X", linked from the glossary, policy pages and the sitemap. */
class GlossaryTermPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('policy:import');
    }

    public function test_every_curated_term_has_a_page_with_its_definition_and_markup(): void
    {
        foreach (GlossaryTerms::all() as $id => $t) {
            $html = $this->get(route('glossary.show', $id))->assertOk()->getContent();
            preg_match('#<title>(.*?)</title>#s', $html, $m);
            $title = html_entity_decode($m[1]);
            $this->assertStringContainsString($t['short'], $title);
            $this->assertStringNotContainsString('noindex', $html, $id);
            $this->assertStringContainsString(e($t['definition']), $html, $id);
            $this->assertStringContainsString('"@type":"DefinedTerm"', $html, $id);
            $this->assertStringContainsString('"@type":"FAQPage"', $html, $id);
            $this->assertStringContainsString('<link rel="canonical" href="'.route('glossary.show', $id).'"', $html, $id);
        }
        $this->get('/glossary/not-a-term')->assertNotFound();
    }

    public function test_a_term_page_lists_the_laws_and_duties_that_use_it(): void
    {
        $this->get(route('glossary.show', 'high-risk-ai-system'))->assertOk()
            ->assertSee('Laws and policies on record that use it')
            ->assertSee(route('policies.show', 'eu-ai-act'), false)
            ->assertSee('Duties that mention it')
            ->assertSee('Related terms');
        $this->get(route('glossary.show', 'bias-audit'))->assertOk()->assertSee('NYC Local Law 144');
    }

    public function test_phrases_match_whole_words_plurals_and_abbreviations(): void
    {
        $fria = GlossaryTerms::find('fundamental-rights-impact-assessment');
        $this->assertTrue(GlossaryTerms::mentions('Deployers must complete a FRIA first.', $fria));
        $this->assertTrue(GlossaryTerms::mentions('fundamental rights impact assessments are due', $fria));
        $card = GlossaryTerms::find('model-card');
        $this->assertTrue(GlossaryTerms::mentions('Publish model cards for each release.', $card));
        $this->assertFalse(GlossaryTerms::mentions('A model cardinality check.', $card));
    }

    public function test_the_glossary_policy_pages_and_sitemap_link_to_the_term_pages(): void
    {
        $this->get(route('glossary'))->assertOk()->assertSee(route('glossary.show', 'ai-literacy'), false);
        $this->get(route('policies.show', 'eu-ai-act'))->assertOk()->assertSee('Terms explained')->assertSee(route('glossary.show', 'high-risk-ai-system'), false);
        $this->get(route('sitemap.section', 'static'))->assertOk()->assertSee(route('glossary.show', 'fundamental-rights-impact-assessment'), false);
    }

    public function test_the_quarterly_report_answers_the_statistics_questions(): void
    {
        $html = $this->get(route('state-of.show'))->assertOk()->getContent();
        $this->assertStringContainsString('AI regulation statistics', $html);
        $this->assertStringContainsString('How many AI laws are there?', $html);
        $this->assertStringContainsString('How many countries have a national AI strategy?', $html);
        preg_match('#<meta name="description" content="([^"]*)"#', $html, $m);
        $this->assertStringNotContainsString('…', html_entity_decode($m[1]));
    }
}
