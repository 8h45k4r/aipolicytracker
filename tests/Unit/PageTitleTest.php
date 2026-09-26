<?php

namespace Tests\Unit;

use App\Services\ExternalData\RecordSlugs;
use App\Support\PageTitle;
use Tests\TestCase;

class PageTitleTest extends TestCase
{
    public function test_an_identifier_is_a_word_glued_to_a_long_number_or_a_hash_number(): void
    {
        foreach (['infocomm2023', 'Deception (Hendrycks2023)', 'ai202240', 'tencent679780', 'AI incident #1382: x'] as $leak) {
            $this->assertTrue(PageTitle::leaksIdentifier($leak), $leak);
        }
        foreach (['EU AI Act (2024): Status', 'AI Regulation 2026', 'Article 26(5)', 'Deception (Hendrycks, 2023)', 'National AI Policy 2026-2030', 'GPT-4o', 'Storm-2139'] as $fine) {
            $this->assertFalse(PageTitle::leaksIdentifier($fine), $fine);
        }
    }

    public function test_a_path_leaks_when_its_record_segment_is_a_key(): void
    {
        $this->assertTrue(PageTitle::pathLeaksIdentifier('/ai-risk/incidents/1382'));
        $this->assertTrue(PageTitle::pathLeaksIdentifier('/ai-risk/risks/05.17.00'));
        $this->assertTrue(PageTitle::pathLeaksIdentifier('/ai-risk/3'));
        $this->assertFalse(PageTitle::pathLeaksIdentifier('/changes/2026'));
        $this->assertFalse(PageTitle::pathLeaksIdentifier('/ai-risk/incidents/purported-deepfake-video-2024'));
        $this->assertFalse(PageTitle::pathLeaksIdentifier('/ai-risk/misinformation'));
    }

    public function test_fit_tries_tails_in_order_and_never_exceeds_the_budget(): void
    {
        $this->assertSame('EU AI Act: Status, Duties & Dates', PageTitle::fit('EU AI Act', [': Status, Duties & Dates', ': Status']));
        $long = str_repeat('Governance ', 4).'Act';
        $this->assertSame($long.': Status', PageTitle::fit($long, [': Status, Duties & Dates and more', ': Status']));
        for ($i = 0; $i < 200; $i++) {
            $text = implode(' ', array_map(fn () => substr(str_shuffle('abcdefghijklmnop'), 0, random_int(2, 14)), range(1, random_int(3, 20))));
            $this->assertLessThanOrEqual(PageTitle::MAX, mb_strlen(PageTitle::fit($text, [': a tail'])), $text);
        }
    }

    public function test_shortening_cuts_at_a_word_or_a_true_clause_never_inside_a_word_or_at_a_bracket(): void
    {
        $t = PageTitle::shorten('Department for Work and Pensions (DWP) Algorithm Wrongly Flags 200,000 People for Fraud Investigation');
        $this->assertStringStartsWith('Department for Work and Pensions (DWP) Algorithm', $t, 'a bracket is not a clause boundary');
        $this->assertStringEndsWith('…', $t);
        $this->assertLessThanOrEqual(60, mb_strlen($t));
        $words = preg_split('/\s+/', 'Department for Work and Pensions (DWP) Algorithm Wrongly Flags 200,000 People for Fraud Investigation');
        foreach (preg_split('/\s+/', rtrim($t, '…')) as $w) {
            $this->assertContains($w, $words, "'{$w}' is a cut word");
        }
        $clause = PageTitle::shorten('Winning the Race: America’s AI Action Plan and the executive orders that implement it');
        $this->assertSame('Winning the Race: America’s AI Action Plan and the…', $clause, 'a colon too early to keep most of the budget is not used');
    }

    public function test_artificial_intelligence_is_abbreviated_before_any_word_is_cut(): void
    {
        $this->assertSame('National AI Policy 2026-2030', PageTitle::compact('National Artificial Intelligence Policy 2026-2030'));
        $this->assertSame('Plano Brasileiro de IA 2024–2028', PageTitle::compact('Plano Brasileiro de Inteligência Artificial 2024–2028'));
        $this->assertSame('Framework Act on the Development of AI and Establishment', PageTitle::shorten('Framework Act on the Development of Artificial Intelligence and Establishment'));
    }

    public function test_the_brand_is_added_only_while_the_title_still_fits(): void
    {
        $this->assertSame('Short title | AIPolicyTracker', PageTitle::withBrand('Short title'));
        $long = str_repeat('x', 50);
        $this->assertSame($long, PageTitle::withBrand($long));
        $this->assertSame('Home | AIPolicyTracker', PageTitle::withBrand('Home | AIPolicyTracker'));
    }

    public function test_a_citation_key_becomes_a_citation(): void
    {
        $this->assertSame('Hendrycks, 2023', PageTitle::citation('Hendrycks2023'));
        $this->assertSame('Gabriel, 2024a', PageTitle::citation('Gabriel2024a'));
        $this->assertSame('Gipiškis, 2024', PageTitle::citation('Gipiškis2024'));
        $this->assertNull(PageTitle::citation(null));
    }

    public function test_descriptions_are_cut_at_a_word_within_budget(): void
    {
        $d = PageTitle::description(str_repeat('regulation ', 30));
        $this->assertLessThanOrEqual(PageTitle::MAX_DESCRIPTION, mb_strlen($d));
        $this->assertStringEndsWith('regulation…', $d);
    }

    public function test_slugs_never_glue_a_word_to_a_number_or_end_mid_phrase(): void
    {
        $this->assertSame('ottawa-couple-loses-ca-177023-after-deepfake', RecordSlugs::slug('Ottawa Couple Loses CA$177,023 After Deepfake'));
        $this->assertSame('father-lost-140000-after-scam', RecordSlugs::slug('Father Lost £140,000 After Scam'));
        $this->assertSame('covid-2019-chatbot', RecordSlugs::slug('COVID2019 Chatbot'));
        $this->assertSame('ai-2024', RecordSlugs::slug('2024'));
        $long = RecordSlugs::slug('Facebook Content Moderators Demand Better Working Conditions Due to the Psychological Toll of Reviewing Content');
        $this->assertLessThanOrEqual(72, strlen($long));
        $this->assertDoesNotMatchRegularExpression('/-(to|due|the|of|and)$/', $long);
        $this->assertFalse(PageTitle::pathLeaksIdentifier($long));
    }
}
