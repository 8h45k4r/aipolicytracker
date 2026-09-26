<?php

namespace Tests\Feature;

use App\Models\ChangeEvent;
use App\Models\PolicyInstrument;
use App\Services\PolicyData\FrameworkCrosswalk;
use App\Support\PageTitle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The signals that decide whether a record is found, read and cited: one
 * indexable address per record, machine forms that point back at it, honest
 * sitemap dates, a page per change, and the head links a crawler reads first.
 */
class SeoSurfacesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->artisan('policy:import');
    }

    public function test_machine_forms_of_a_record_are_not_pages_of_their_own(): void
    {
        $policy = PolicyInstrument::published()->where('slug', 'eu-ai-act')->firstOrFail();

        foreach ([route('policies.context', $policy->slug), route('policies.json', $policy->slug)] as $url) {
            $this->get($url)->assertOk()
                ->assertHeader('X-Robots-Tag', 'noindex')
                ->assertHeader('Link', '<'.$policy->url().'>; rel="canonical"');
        }
        $this->get('/open-data/policies.csv')->assertOk()->assertHeader('X-Robots-Tag', 'noindex');
        $this->get('/open-data/policies.ndjson')->assertOk()->assertHeader('X-Robots-Tag', 'noindex');

        // And the page names them as its alternates, so the relationship reads both ways.
        $html = $this->get($policy->url())->assertOk()->getContent();
        $this->assertStringContainsString('<link rel="alternate" type="text/markdown" href="'.route('policies.context', $policy->slug).'">', $html);
        $this->assertStringContainsString('<link rel="alternate" type="application/json" href="'.route('policies.json', $policy->slug).'">', $html);
    }

    public function test_the_head_carries_the_signals_a_crawler_reads_first(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('<meta property="og:locale" content="en_US">', $html);
        $this->assertStringContainsString('<meta name="twitter:site" content="@aipolicytracker">', $html);
        $this->assertStringContainsString('<link rel="license" href="https://creativecommons.org/licenses/by/4.0/">', $html);
        $this->assertStringContainsString('rel="apple-touch-icon"', $html);
        $this->assertStringContainsString('rel="manifest"', $html);
        $this->assertStringContainsString('<link rel="preconnect" href="https://fonts.bunny.net" crossorigin>', $html);
        $this->assertStringContainsString('"logo":{"@type":"ImageObject"', $html);

        foreach (['favicon.ico', 'icon-192.png', 'icon-512.png', 'apple-touch-icon.png', 'site.webmanifest'] as $file) {
            $this->assertGreaterThan(100, filesize(public_path($file)), "{$file} must be a real file");
        }
        $this->assertSame('image/vnd.microsoft.icon', mime_content_type(public_path('favicon.ico')));
        $manifest = json_decode(file_get_contents(public_path('site.webmanifest')), true);
        $this->assertSame('#002147', $manifest['theme_color']);
    }

    /**
     * Every policy <title> is at most sixty characters, brand included, and is
     * cut only when the name, abbreviated where that loses nothing, cannot sit
     * beside its jurisdiction in that space. A name that fits is never cut.
     */
    public function test_titles_fit_a_result_page_and_are_cut_only_when_they_must_be(): void
    {
        foreach (PolicyInstrument::published()->with('jurisdiction')->get()->filter->isIndexable() as $policy) {
            $html = $this->get($policy->url())->assertOk()->getContent();
            preg_match('#<title>(.*?)</title>#s', $html, $m);
            $title = html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5);
            $this->assertLessThanOrEqual(PageTitle::MAX, mb_strlen($title), $policy->slug);
            $this->assertFalse(PageTitle::leaksIdentifier($title), $policy->slug.': '.$title);

            $name = PageTitle::compact($policy->short_title ?: $policy->title);
            $place = $policy->jurisdiction?->short_name ?: $policy->jurisdiction?->name;
            $needed = mb_strlen($name) + (str_contains($name, (string) $place) ? 0 : mb_strlen(" ({$place})"));
            if ($needed <= PageTitle::MAX) {
                $this->assertStringNotContainsString('…', $title, $policy->slug.' was cut although it fits');
            }
        }
    }

    public function test_every_change_has_a_page_that_a_feed_and_a_sitemap_point_at(): void
    {
        $change = ChangeEvent::published()->with(['jurisdiction', 'policyInstrument'])->whereNotNull('official_source_url')->orderByDesc('occurred_on')->firstOrFail();

        $page = $this->get($change->url())->assertOk();
        $page->assertSee($change->title)->assertSee('What changed?')->assertSee('Cite this record')->assertSee($change->official_source_url, false);
        $html = $page->getContent();
        $this->assertStringContainsString('<link rel="canonical" href="'.$change->url().'">', $html);
        $this->assertStringContainsString('"@type":"Article"', $html);
        $this->assertStringContainsString('"datePublished":"'.$change->occurred_on->format(DATE_ATOM).'"', $html);
        $this->assertStringContainsString('<link rel="alternate" type="text/markdown" href="'.route('changes.context', $change->slug).'">', $html);

        $this->get(route('changes.context', $change->slug))->assertOk()->assertHeader('Link', '<'.$change->url().'>; rel="canonical"');
        $this->get('/changes/feed')->assertOk()->assertSee('<link>'.$change->url().'</link>', false)->assertSee('isPermaLink="true"', false);
        $this->get('/sitemap-changes.xml')->assertOk()->assertSee('<loc>'.$change->url().'</loc>', false);
        $this->get('/changes')->assertOk()->assertSee('href="'.$change->url().'"', false);
        $this->get('/changes/no-such-change')->assertNotFound();
    }

    public function test_paginated_listings_are_canonical_to_themselves(): void
    {
        $html = $this->get('/obligations?page=2')->assertOk()->getContent();
        $this->assertStringContainsString('<link rel="canonical" href="'.route('obligations.index').'?page=2">', $html);
        $this->assertStringContainsString('content="index,follow', $html);

        $html = $this->get('/policies?jurisdiction=eu&page=2')->assertOk()->getContent();
        $this->assertStringContainsString('<link rel="canonical" href="'.route('policies.index').'?jurisdiction=eu&amp;page=2">', $html, 'a filter query and a page number join with & not a second ?');
    }

    public function test_sitemap_dates_are_only_claimed_where_they_are_known(): void
    {
        $xml = $this->get('/sitemap-static.xml')->assertOk()->getContent();
        preg_match('#<url>\s*<loc>'.preg_quote(route('privacy'), '#').'</loc>(.*?)</url>#s', $xml, $m);
        $this->assertNotEmpty($m, 'privacy is listed');
        $this->assertStringNotContainsString('<lastmod>', $m[1], 'a static page must not carry the corpus import time as its own date');
        $this->assertSame(1, substr_count($xml, '<loc>'.route('guides.show', array_key_first(config('content.guides'))).'</loc>') + substr_count($this->get('/sitemap-resources.xml')->getContent(), '<loc>'.route('guides.show', array_key_first(config('content.guides'))).'</loc>'), 'a guide is listed exactly once across the sitemaps');
    }

    public function test_robots_txt_does_not_block_what_the_site_advertises(): void
    {
        $robots = file_get_contents(public_path('robots.txt'));
        $this->assertStringNotContainsString('Disallow: /api/', $robots, 'the API is a published Dataset distribution and sets its own noindex');
        $this->assertStringNotContainsString('?*q=', $robots, 'search pages are noindex, which a blocked crawler could never read');
        $this->assertStringContainsString("User-agent: GPTBot\n", $robots);
        $this->assertStringContainsString("User-agent: ClaudeBot\n", $robots);
        $this->assertStringContainsString('Sitemap: https://aipolicytracker.org/sitemap.xml', $robots);
    }

    public function test_framework_pages_show_the_questions_their_markup_claims(): void
    {
        $framework = app(FrameworkCrosswalk::class)->summary()->first(fn ($f) => $f['obligations'] >= FrameworkCrosswalk::MIN_INDEXABLE_OBLIGATIONS);
        if (! $framework) {
            $this->markTestSkipped('no indexable framework in the corpus');
        }
        $html = $this->get(route('frameworks.show', $framework['slug']))->assertOk()->getContent();
        $this->assertSame(1, substr_count(str_replace(' ', '', $html), '"@type":"FAQPage"'));
        $this->assertStringContainsString('Does '.$framework['short'].' make an organisation legally compliant?', $html, 'the question is visible, not only in the markup');
    }
}
