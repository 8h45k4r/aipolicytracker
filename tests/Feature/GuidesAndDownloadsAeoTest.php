<?php

namespace Tests\Feature;

use App\Services\Templates\TemplateBuilder;
use App\Services\Templates\TemplateCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Guides and template pages for answer engines: each guide states when it was published and
 * last edited and how much of it rests on verified records; guides and templates have a
 * Markdown copy, served at `.md` and to a client that asks for Markdown, that is never
 * indexed in place of the page.
 */
class GuidesAndDownloadsAeoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(TemplateBuilder::DISK);
        $this->artisan('policy:import');
    }

    public function test_every_guide_has_a_publication_and_edit_date_in_order(): void
    {
        foreach (config('content.guides') as $slug => $g) {
            $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $g['published'] ?? '', $slug.' published');
            $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $g['updated'] ?? '', $slug.' updated');
            $this->assertTrue(Carbon::parse($g['updated'])->gte(Carbon::parse($g['published'])), $slug.' is not updated before it was published');
            $this->assertTrue(Carbon::parse($g['updated'])->lte(now()), $slug.' is not dated in the future');
        }
    }

    public function test_a_guide_page_states_its_dates_and_what_it_rests_on(): void
    {
        $slug = 'iso-42001-vs-eu-ai-act';
        $g = config('content.guides.'.$slug);
        $html = $this->get(route('guides.show', $slug))->assertOk()->getContent();

        $this->assertStringContainsString('Published <time datetime="'.$g['published'].'">', $html);
        $this->assertStringContainsString('text updated <time datetime="'.$g['updated'].'">', $html);
        $this->assertMatchesRegularExpression('/built from \d+ records?, \d+ verified by a named reviewer/', $html);
        $this->assertStringContainsString('"datePublished":"'.$g['published'], $html);
        $this->assertStringContainsString('"isBasedOn":["'.route('policies.show', 'eu-ai-act').'"', $html);
        $this->assertStringContainsString('<link rel="alternate" type="text/markdown" href="'.route('guides.context', $slug).'">', $html);

        // The page date is the newer of the text edit and the records' content date, never earlier than the edit.
        preg_match('#"dateModified":"([^"]+)"#', $html, $m);
        $this->assertTrue(Carbon::parse($m[1])->gte(Carbon::parse($g['updated'])));
    }

    public function test_guides_and_templates_have_markdown_copies_that_point_back_to_the_page(): void
    {
        $guide = $this->get(route('guides.context', 'us-state-ai-laws-2026'))->assertOk();
        $this->assertStringStartsWith('text/markdown', $guide->headers->get('Content-Type'));
        $this->assertSame('noindex', $guide->headers->get('X-Robots-Tag'));
        $this->assertSame('<'.route('guides.show', 'us-state-ai-laws-2026').'>; rel="canonical"', $guide->headers->get('Link'));
        $body = $guide->getContent();
        $this->assertStringContainsString('# '.config('content.guides.us-state-ai-laws-2026.h1'), $body);
        $this->assertStringContainsString('## Records this guide is built from', $body);
        $this->assertStringContainsString('Not legal advice', $body);

        app(TemplateBuilder::class)->build('ai-risk-register');
        $template = $this->get(route('templates.context', 'ai-risk-register'))->assertOk()->getContent();
        $this->assertStringContainsString('# '.TemplateCatalog::find('ai-risk-register')['title'], $template);
        $this->assertStringContainsString('## What is inside', $template);
        $this->assertStringContainsString('- **Current version**: v1', $template);
        $this->assertStringContainsString('href="'.route('templates.context', 'ai-risk-register').'"', $this->get(route('templates.show', 'ai-risk-register'))->getContent());

        $this->get('/guides/no-such-guide.md')->assertNotFound();
        $this->get('/templates/no-such-template.md')->assertNotFound();
    }

    public function test_a_client_that_asks_for_markdown_gets_it_and_a_browser_gets_the_page(): void
    {
        $url = route('guides.show', 'ai-hiring-compliance-laws');
        $md = $this->get($url, ['Accept' => 'text/markdown'])->assertOk();
        $this->assertStringStartsWith('text/markdown', $md->headers->get('Content-Type'));
        $this->assertStringContainsString('Accept', (string) $md->headers->get('Vary'));

        $page = $this->get($url, ['Accept' => 'text/html,application/xhtml+xml,*/*;q=0.8'])->assertOk();
        $this->assertStringStartsWith('text/html', $page->headers->get('Content-Type'));

        // A path under /guides that is not a guide is answered by its own route.
        $this->get('/guides/tools', ['Accept' => 'text/markdown'])->assertStatus(404);
    }

    public function test_llms_txt_and_the_guides_index_carry_the_new_signals(): void
    {
        $llms = $this->get(route('llms'))->assertOk()->getContent();
        $this->assertStringContainsString('(Markdown: '.route('guides.context', 'iso-42001-vs-eu-ai-act'), $llms);
        $this->assertStringContainsString(route('templates.context', 'ai-risk-register'), $llms);

        $newest = collect(config('content.guides'))->pluck('updated')->max();
        $this->assertStringContainsString('"dateModified":"'.$newest, $this->get(route('guides.index'))->assertOk()->getContent());
    }
}
