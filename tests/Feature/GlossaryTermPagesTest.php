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
            if ($t['canonical']) {
                // Answered on its record page instead: one address per question.
                $this->get(route('glossary.show', $id))->assertStatus(301)->assertRedirect($t['canonical']);

                continue;
            }
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
            ->assertSee('Related concepts');
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

    public function test_answer_first_entries_follow_the_format(): void
    {
        $all = GlossaryTerms::all();
        $answered = collect($all)->filter(fn ($t) => $t['answer']);
        $this->assertGreaterThanOrEqual(18, $answered->count());
        foreach ($answered as $id => $t) {
            // A lift-out answer: starts with the subject, under fifty words.
            $this->assertLessThan(50, str_word_count($t['answer']), $id);
            $this->assertStringStartsWith('What is ', $t['question'], $id);
            $this->assertStringEndsWith('?', $t['question'], $id);
            // Definitional form: the subject, then "is", within the first words.
            $this->assertMatchesRegularExpression('/^(\\S+\\s+){0,10}?(is|are)\\s/u', $t['answer'], $id);
            $this->assertGreaterThanOrEqual(2, count($t['why']), $id);
            $this->assertLessThanOrEqual(3, count($t['why']), $id);
            foreach ($t['related'] as $rel) {
                $this->assertArrayHasKey($rel, $all, "{$id} relates to {$rel}, which is not a term");
            }
            if ($t['source']) {
                $this->assertStringStartsWith('https://', $t['source'][1], $id);
            }
        }
        foreach ($all as $id => $t) {
            $this->assertCount(count(config("glossary.terms.{$id}.see", [])), $t['see'], "{$id} links a route that does not exist");
        }

        $html = $this->get(route('glossary.show', 'prompt-injection'))->assertOk()->getContent();
        $this->assertStringContainsString('What is prompt injection?', $html);
        $this->assertStringContainsString('Why it matters', $html);
        $this->assertStringContainsString('Related concepts', $html);
        $this->assertStringContainsString('<meta name="description" content="Prompt injection is an attack', $html);
    }

    public function test_the_glossary_answers_the_core_governance_questions(): void
    {
        $html = $this->get(route('glossary'))->assertOk()->getContent();
        preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $html, $m);
        $faq = collect($m[1])->map(fn ($j) => json_decode($j, true))->firstWhere('@type', 'FAQPage');
        $this->assertNotNull($faq);
        $this->assertSame(
            ['What is AI GRC?', 'What is AI risk?', 'What is the EU AI Act?', 'What is ISO/IEC 42001?', 'What is the NIST AI RMF?'],
            array_column($faq['mainEntity'], 'name'),
        );
        // Each is shown on the page, not only in the markup.
        foreach ($faq['mainEntity'] as $q) {
            $this->assertStringContainsString(e($q['acceptedAnswer']['text']), $html);
        }
        $this->assertStringContainsString(route('policies.show', 'eu-ai-act'), $html, 'the EU AI Act entry points to its record');
        $this->assertStringNotContainsString(route('glossary.show', 'eu-ai-act'), $this->get(route('sitemap.section', 'static'))->getContent());
    }
}
