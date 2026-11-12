<?php

namespace Tests\Feature;

use App\Models\NewsItem;
use App\Services\News\NewsCollector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * AI policy news: headlines from official feeds only, kept when they are about AI, tagged by
 * topic, merged with the change log and incident records, newest first; a feed that fails is
 * reported and the others still run; no article text is shown.
 */
class NewsTest extends TestCase
{
    use RefreshDatabase;

    private function rss(array $items): string
    {
        $x = '<?xml version="1.0"?><rss version="2.0"><channel><title>t</title>';
        foreach ($items as [$title, $link, $date, $desc]) {
            $x .= '<item><title>'.htmlspecialchars($title).'</title><link>'.$link.'</link><guid>'.$link.'</guid><pubDate>'.$date.'</pubDate><description>'.htmlspecialchars($desc).'</description></item>';
        }

        return $x.'</channel></rss>';
    }

    private function atom(array $items): string
    {
        $x = '<?xml version="1.0"?><feed xmlns="http://www.w3.org/2005/Atom"><title>t</title>';
        foreach ($items as [$title, $link, $date, $summary]) {
            $x .= '<entry><id>'.$link.'</id><title>'.htmlspecialchars($title).'</title><link rel="alternate" href="'.$link.'"/><updated>'.$date.'</updated><summary>'.htmlspecialchars($summary).'</summary></entry>';
        }

        return $x.'</feed>';
    }

    private function fakeFeeds(bool $euDown = false): void
    {
        $recent = now()->subDays(2);
        Http::fake([
            'www.nist.gov/*' => Http::response($this->rss([
                ['NIST releases AI risk management profile for generative AI', 'https://www.nist.gov/news/a', $recent->toRfc2822String(), 'New guidance on AI safety.'],
                ['NIST updates cybersecurity guide for small business', 'https://www.nist.gov/news/b', $recent->toRfc2822String(), 'Password advice.'],
            ])),
            'www.gov.uk/government/organisations/ai-security-institute.atom' => Http::response($this->atom([
                ['Institute publishes evaluation results', 'https://www.gov.uk/government/news/eval', $recent->toAtomString(), 'Results of frontier model testing.'],
            ])),
            'www.gov.uk/*' => Http::response($this->atom([])),
            'digital-strategy.ec.europa.eu/*' => $euDown ? Http::response('down', 503) : Http::response($this->rss([
                ['Commission publishes guidelines under the AI Act', 'https://digital-strategy.ec.europa.eu/en/news/x', $recent->toRfc2822String(), 'Guidelines for providers.'],
            ])),
            'www.federalregister.gov/*' => Http::response(['results' => []]),
        ]);
    }

    public function test_only_ai_headlines_from_official_feeds_are_kept_once_with_topics(): void
    {
        $this->fakeFeeds();
        $this->artisan('news:sync')->assertSuccessful();

        $titles = NewsItem::pluck('title')->all();
        $this->assertContains('NIST releases AI risk management profile for generative AI', $titles);
        $this->assertNotContains('NIST updates cybersecurity guide for small business', $titles, 'not about AI');
        $this->assertContains('Institute publishes evaluation results', $titles, 'an AI-only source keeps everything');
        $this->assertContains('Commission publishes guidelines under the AI Act', $titles);
        $nist = NewsItem::where('title', 'like', 'NIST releases%')->sole();
        $this->assertSame(['us', 'nist'], [$nist->jurisdiction_slug, $nist->source]);
        $this->assertContains('risk', $nist->topics);
        $this->assertContains('safety', $nist->topics);

        $count = NewsItem::count();
        $this->artisan('news:sync')->assertSuccessful();
        $this->assertSame($count, NewsItem::count(), 'a second run adds nothing');
    }

    public function test_a_failing_feed_is_reported_and_the_others_still_run(): void
    {
        $this->fakeFeeds(euDown: true);
        $this->artisan('news:sync')->expectsOutputToContain('FAILED')->assertSuccessful();
        $this->assertSame(0, NewsItem::where('source', 'eu-digital-strategy')->count());
        $this->assertGreaterThan(0, NewsItem::where('source', 'nist')->count());
    }

    public function test_the_page_lists_headlines_with_publisher_and_link_and_filters_by_topic(): void
    {
        $this->fakeFeeds();
        $this->artisan('news:sync');
        $html = $this->get(route('news.index'))->assertOk()->getContent();

        $this->assertStringContainsString('<meta name="robots" content="index', $html);
        $this->assertStringContainsString('href="https://www.nist.gov/news/a"', $html);
        $this->assertStringContainsString('US National Institute of Standards and Technology', $html);
        $this->assertStringNotContainsString('New guidance on AI safety.', $html, 'no feed text is shown');
        $this->assertStringContainsString('Where the news comes from', $html, 'the sources figure');

        $risk = $this->get(route('news.index', ['topic' => 'risk']))->assertOk()->getContent();
        $this->assertStringContainsString('<meta name="robots" content="noindex', $risk);
        $this->assertStringContainsString('NIST releases AI risk management profile', $risk);
        $this->assertStringNotContainsString('Institute publishes evaluation results', $risk);
        $this->get(route('news.index', ['page' => 99]))->assertNotFound();
    }

    public function test_topic_and_relevance_rules(): void
    {
        $this->assertTrue(NewsCollector::aboutAi('New AI safety institute', ''));
        $this->assertTrue(NewsCollector::aboutAi('Ministerial call', 'They discussed artificial intelligence and trade.'));
        $this->assertFalse(NewsCollector::aboutAi('Ministerial call', 'They discussed AI, briefly, and trade.'));
        $this->assertContains('incidents', NewsCollector::topics('Regulator fines company after AI incident'));
    }
}
