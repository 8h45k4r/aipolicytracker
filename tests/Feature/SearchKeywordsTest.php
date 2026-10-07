<?php

namespace Tests\Feature;

use App\Models\Jurisdiction;
use App\Models\PolicyInstrument;
use App\Services\Templates\TemplateCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The pages that answer the searches people make for this site's subject. */
class SearchKeywordsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('policy:import');
    }

    private function title(string $url): string
    {
        preg_match('#<title>(.*?)</title>#s', $this->get($url)->assertOk()->getContent(), $m);

        return html_entity_decode($m[1]);
    }

    public function test_the_head_terms_are_in_the_titles_of_the_pages_that_answer_them(): void
    {
        $this->assertStringContainsString('AI Policy Tracker', $this->title('/'));
        $this->assertStringContainsString('Global AI Law and Policy Tracker', $this->title('/'));
        $this->assertStringContainsString('AI Regulations Around the World', $this->title(route('jurisdictions.index')));
        // The country landings were retired in favour of the pages search engines preferred
        // (the Nepal hub, the India jurisdiction page); the head terms now sit on those pages.
        $nepal = Jurisdiction::where('slug', 'nepal')->firstOrFail()->url();
        $this->assertStringContainsString('Nepal AI Policy', $this->title($nepal));
        $this->assertStringContainsString('2082', $this->get($nepal)->assertOk()->getContent(), 'the hub names the National AI Policy 2082');
        $this->assertStringContainsString('India AI Policy', $this->title(Jurisdiction::where('slug', 'india')->firstOrFail()->url()));
        $this->assertStringContainsString('AI Policy & Regulation', $this->title(Jurisdiction::where('slug', 'south-africa')->firstOrFail()->url()));
        foreach ([$this->title('/'), $this->title(route('jurisdictions.index')), $this->title(route('ai-policy-examples'))] as $t) {
            $this->assertLessThanOrEqual(60, mb_strlen($t), $t);
        }

        $this->artisan('templates:build');
        $this->assertStringStartsWith('AI Policy Template', $this->title(TemplateCatalog::url('acceptable-use-policy')));
    }

    public function test_the_home_page_answers_what_an_ai_policy_tracker_is(): void
    {
        $html = $this->get('/')->assertOk()->getContent();
        foreach (['What is an AI policy tracker?', 'Is this global AI law and policy tracker free?', 'Is there a map of AI regulations around the world?', 'Is there a free AI policy template?'] as $q) {
            $this->assertStringContainsString($q, $html);
        }
        $this->assertStringContainsString('"@type":"FAQPage"', $html);
    }

    public function test_ai_policy_examples_come_from_the_records(): void
    {
        $html = $this->get(route('ai-policy-examples'))->assertOk()->getContent();
        $nepal = PolicyInstrument::where('slug', 'nepal-national-ai-policy')->firstOrFail();

        $this->assertStringContainsString($nepal->url(), $html);
        $this->assertStringContainsString(TemplateCatalog::url('acceptable-use-policy'), $html, 'and the company policy for readers who meant their own');
        $this->assertStringContainsString('What is the difference between an AI policy and an AI law?', $html);
        $this->assertStringContainsString(route('ai-policy-examples'), $this->get(route('sitemap.section', 'static'))->getContent());
    }
}
