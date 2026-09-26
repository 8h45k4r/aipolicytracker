<?php

namespace Tests\Feature;

use App\Models\Jurisdiction;
use App\Models\Obligation;
use App\Models\PolicyInstrument;
use App\Services\Records\AnswerBox;
use App\Services\Records\KeyFacts;
use App\Services\Records\QuestionBank;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P3: every policy, obligation and jurisdiction page opens with an answer of
 * 40–60 words composed from checked fields, then a key-facts table, then the
 * record; the FAQ asks only what the record can answer; the same answer leads
 * the .md context file and llms-full.txt.
 */
class AnswerFirstRecordsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('policy:import');
    }

    public function test_every_answer_in_the_corpus_is_forty_to_sixty_words_and_says_nothing_the_record_does_not(): void
    {
        foreach (PolicyInstrument::published()->with(['jurisdiction', 'obligations'])->get()->filter->isIndexable() as $p) {
            $a = AnswerBox::policy($p);
            $this->assertBetween(40, 60, AnswerBox::wordCount($a), $p->slug.': '.$a);
            $this->assertTrue(str_contains($a, $p->jurisdiction->name) || str_contains($a, (string) $p->jurisdiction->short_name) || str_contains($p->short_title ?: $p->title, $p->jurisdiction->name), $p->slug.': the answer does not place the instrument: '.$a);
            $this->assertMatchesRegularExpression('/^[A-Z(“"]/u', $a, 'starts with a capital, or a bracket or quote that the name itself opens with');
        }
        foreach (Obligation::published()->with(['policyInstrument.jurisdiction', 'evidenceArtifacts', 'frameworkMappings'])->get() as $o) {
            $a = AnswerBox::obligation($o);
            $this->assertBetween(40, 60, AnswerBox::wordCount($a), $o->slug.': '.$a);
            $this->assertStringContainsString($o->is_binding ? 'a legal requirement' : 'a voluntary measure', $a);
        }
        foreach (Jurisdiction::published()->get()->filter->isIndexable() as $j) {
            $policies = $j->policyInstruments()->published()->get();
            $a = AnswerBox::jurisdiction($j, $policies, collect());
            $this->assertBetween(40, 60, AnswerBox::wordCount($a), $j->slug.': '.$a);
            $this->assertStringContainsString((string) $policies->count(), $a, 'states the instrument count');
        }
    }

    public function test_the_answer_uses_articles_and_the_status_verb_matches_the_record(): void
    {
        $eu = PolicyInstrument::with('jurisdiction')->where('slug', 'eu-ai-act')->firstOrFail();
        $a = AnswerBox::policy($eu);
        $this->assertStringStartsWith('The EU AI Act is a binding regulation of the European Union', $a);
        $this->assertStringContainsString('has applied in part since 1 August 2024', $a);
        $this->assertStringContainsString('44 obligations are recorded', $a);

        $this->assertSame('the Ministry of Digital Affairs', AnswerBox::withArticle('Ministry of Digital Affairs'));
        $this->assertSame('NIST', AnswerBox::withArticle('NIST'));
        $this->assertSame('Meta', AnswerBox::withArticle('Meta'));
        $this->assertSame('the Cabinet Office', AnswerBox::withArticle('the Cabinet Office'));
    }

    public function test_the_pages_open_with_the_answer_then_the_facts_and_carry_one_faq_node(): void
    {
        $o = Obligation::published()->with('policyInstrument')->firstOrFail();
        foreach (['/policies/eu-ai-act', '/obligations/'.$o->slug, '/jurisdictions/eu'] as $url) {
            $html = $this->get($url)->assertOk()->getContent();
            $main = strpos($html, 'lg:col-span-2 min-w-0');
            $this->assertNotFalse($main, $url);
            $box = strpos($html, 'data-answer-box', $main);
            $firstSection = strpos($html, '<section', $main);
            $firstSectionEnd = strpos($html, '>', $firstSection);
            $this->assertNotFalse($box, "{$url}: no answer box");
            $this->assertLessThan($firstSectionEnd, $box, "{$url}: the answer box is not the first section in the main column");
            $this->assertStringContainsString('data-key-facts', $html, $url);
            $this->assertSame(1, substr_count($html, '"@type":"FAQPage"'), "{$url}: exactly one FAQPage node");
            $this->assertSame(1, preg_match_all('#<h1\b#', $html), $url);
            $this->assertStringContainsString('Cite this record', $html, $url);
            preg_match('#<meta name="description" content="([^"]+)"#', $html, $m);
            $this->assertLessThanOrEqual(155, mb_strlen(html_entity_decode($m[1])), $url);
        }
        $policy = $this->get('/policies/eu-ai-act')->getContent();
        $this->assertStringContainsString('"@type":"Article"', $policy);
        $this->assertStringContainsString('"@type":"Legislation"', $policy);
        $this->assertStringContainsString('"@type":"Dataset"', $policy);
        $this->assertStringContainsString('"@type":"Article"', $this->get('/obligations/'.$o->slug)->getContent());
        $this->assertStringContainsString('"@type":"Dataset"', $this->get('/jurisdictions/eu')->getContent());
    }

    public function test_the_question_bank_asks_only_what_the_record_can_answer(): void
    {
        $eu = PolicyInstrument::with('jurisdiction')->where('slug', 'eu-ai-act')->firstOrFail();
        $items = QuestionBank::policy($eu);
        $questions = array_column($items, 'question');
        $this->assertContains('Is the EU AI Act in force?', $questions);
        $this->assertContains('Is the EU AI Act legally binding?', $questions);
        foreach ($items as $item) {
            $this->assertNotSame('', trim($item['answer']), $item['question']);
        }
        $this->assertSame(count($questions), count(array_unique(array_map('strtolower', $questions))), 'no duplicate questions');

        // A record with nothing to say about penalties is not asked about them.
        $bare = PolicyInstrument::published()->whereNull('penalties_summary')->with('jurisdiction')->first();
        if ($bare) {
            $this->assertNotContains('What are the penalties under '.$bare->definiteName().'?', array_column(QuestionBank::policy($bare), 'question'));
        }
        // Hand-written questions come first and win on a duplicate.
        $written = PolicyInstrument::published()->whereNotNull('faq')->with('jurisdiction')->get()->first(fn ($p) => ! empty($p->faq));
        if ($written) {
            $this->assertSame(trim($written->faq[0]['question']), QuestionBank::policy($written)[0]['question']);
        }

        $j = Jurisdiction::where('slug', 'eu')->firstOrFail();
        $policies = $j->policyInstruments()->published()->get();
        $jq = array_column(QuestionBank::jurisdiction($j, $policies, collect()), 'question');
        $this->assertContains('Does the European Union have an AI law?', $jq);
        $this->assertNotContains('What AI compliance deadlines are coming up in the European Union?', $jq, 'no deadlines were passed, so the question is not asked');
    }

    public function test_key_facts_omit_what_is_not_recorded_and_link_what_is_a_record(): void
    {
        $eu = PolicyInstrument::with(['jurisdiction', 'obligations'])->where('slug', 'eu-ai-act')->firstOrFail();
        $facts = collect(KeyFacts::policy($eu));
        $this->assertSame('European Union', $facts->firstWhere('label', 'Jurisdiction')['value']);
        $this->assertSame($eu->jurisdiction->url(), $facts->firstWhere('label', 'Jurisdiction')['href']);
        $this->assertSame('Binding', $facts->firstWhere('label', 'Legal force')['value']);
        foreach ($facts as $f) {
            $this->assertNotSame('', $f['value']);
            $this->assertNotSame('—', $f['value']);
        }
        $this->assertSame('1 August 2024', $facts->firstWhere('label', 'In force')['value']);
        $this->assertSame('2 August 2026', $facts->firstWhere('label', 'Applies from')['value']);
        // A fact the record does not carry is left out, not shown as a dash.
        $bare = PolicyInstrument::published()->whereNull('in_force_on')->with('jurisdiction')->firstOrFail();
        $this->assertNull(collect(KeyFacts::policy($bare))->firstWhere('label', 'In force'), $bare->slug);
    }

    public function test_the_answer_leads_the_context_file_and_llms_full(): void
    {
        $eu = PolicyInstrument::with('jurisdiction')->where('slug', 'eu-ai-act')->firstOrFail();
        $md = $this->get('/policies/eu-ai-act.md')->assertOk()->getContent();
        preg_match('/\n## ([^\n]+)\n/', $md, $m);
        $this->assertSame('In brief', $m[1], 'the first section of the context file is the answer');
        $this->assertStringContainsString(AnswerBox::policy($eu), $md);

        $o = Obligation::published()->firstOrFail();
        $this->assertStringContainsString("## In brief\n", $this->get('/obligations/'.$o->slug.'.md')->assertOk()->getContent());
        $this->assertStringContainsString("## In brief\n", $this->get('/jurisdictions/eu.md')->assertOk()->getContent());

        $full = $this->get('/llms-full.txt')->assertOk()->getContent();
        $this->assertStringContainsString('- In brief: '.AnswerBox::policy($eu), $full);
    }

    private function assertBetween(int $min, int $max, int $n, string $message): void
    {
        $this->assertGreaterThanOrEqual($min, $n, $message);
        $this->assertLessThanOrEqual($max, $n, $message);
    }
}
