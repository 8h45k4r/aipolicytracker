<?php

namespace Tests\Feature;

use App\Models\ChangeEvent;
use App\Models\DigestIssue;
use App\Models\Jurisdiction;
use App\Models\Subscriber;
use App\Support\PageTitle;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Support\OpenApiContract;
use Tests\TestCase;

/**
 * P2: the updates hub, its archives and feeds, the news sitemap, the latest-
 * updates module, the newsletter archive, and the API field that drives "top
 * stories".
 */
class UpdatesHubTest extends TestCase
{
    use OpenApiContract;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('policy:import');
    }

    public function test_the_hub_answers_first_from_counts_and_carries_the_schema_a_searcher_needs(): void
    {
        $html = $this->get('/updates')->assertOk()->getContent();
        $head = substr($html, 0, strpos($html, '</head>'));

        $this->assertStringContainsString('<title>'.PageTitle::updatesHub(now()), $head);
        $this->assertSame(1, preg_match_all('#<h1\b#', $html));
        $this->assertMatchesRegularExpression('/\d+ AI policy changes? recorded across \d+ jurisdictions?/', $html, 'the answer box is computed from counts');
        $this->assertStringContainsString('Last updated <time datetime=', $html);
        $this->assertStringContainsString('Top stories', $html);
        $this->assertStringContainsString('"@type":"CollectionPage"', $head);
        $this->assertStringContainsString('"@type":"ItemList"', $head);
        $this->assertStringContainsString('"@type":"FAQPage"', $head);
        $this->assertStringContainsString('"dateModified"', $head);
        $this->assertStringContainsString('<link rel="canonical" href="'.url('/updates').'">', $head);
        $this->assertGreaterThanOrEqual(5, preg_match_all('#href="'.preg_quote(url('/'), '#').'[^"]+"#', $html));

        // Filters keep the page useful and keep it out of the index.
        $this->get('/updates?jurisdiction=eu&impact=high')->assertOk()->assertSee('noindex', false);
    }

    public function test_month_day_and_jurisdiction_archives_exist_only_with_items_and_index_only_above_the_threshold(): void
    {
        $months = ChangeEvent::publishedMonths();
        $rich = $months->filter(fn ($m) => $m['count'] >= 3)->keys()->first();
        $thin = $months->filter(fn ($m) => $m['count'] < 3)->keys()->first();
        $this->assertNotNull($rich);
        $this->assertNotNull($thin);

        $this->get('/updates/'.$rich)->assertOk()->assertSee('index,follow', false)->assertSee(PageTitle::updatesMonth(Carbon::parse($rich.'-01')));
        $this->get('/updates/'.$thin)->assertOk()->assertSee('noindex,follow', false);
        $this->get('/updates/1999-01')->assertNotFound();
        $this->get('/updates/2026-13')->assertNotFound();

        $day = ChangeEvent::published()->orderByDesc('occurred_on')->value('occurred_on')->toDateString();
        $this->get('/updates/'.$day)->assertOk()->assertSee('AI policy updates, ');
        $this->get('/updates/1999-01-01')->assertNotFound();

        $eu = Jurisdiction::where('slug', 'eu')->firstOrFail();
        $this->assertGreaterThanOrEqual(3, ChangeEvent::published()->where('jurisdiction_id', $eu->id)->count());
        $this->get('/updates/eu')->assertOk()->assertSee('index,follow', false)->assertSee('RSS for EU')->assertSee(route('updates.jurisdiction.feed', 'eu'));
        $this->get('/updates/no-such-place')->assertNotFound();

        $quiet = Jurisdiction::published()->whereDoesntHave('changeEvents')->first() ?? Jurisdiction::published()->first();
        if (! ChangeEvent::published()->where('jurisdiction_id', $quiet->id)->exists()) {
            $this->get('/updates/'.$quiet->slug)->assertNotFound();
        }
    }

    public function test_every_updates_title_meets_the_rules(): void
    {
        foreach (ChangeEvent::publishedMonths()->keys()->take(6) as $month) {
            $this->assertTitleRules($this->get('/updates/'.$month)->getContent());
        }
        foreach (Jurisdiction::published()->whereHas('changeEvents')->limit(8)->get() as $j) {
            $this->assertTitleRules($this->get('/updates/'.$j->slug)->getContent());
        }
        $this->assertTitleRules($this->get('/updates')->getContent());
    }

    public function test_per_jurisdiction_rss_carries_only_that_jurisdiction(): void
    {
        $xml = $this->get('/updates/eu/feed')->assertOk()->assertHeader('Content-Type', 'application/rss+xml; charset=UTF-8')->getContent();
        $this->assertStringContainsString('<title>AIPolicyTracker: AI policy updates, European Union</title>', $xml);
        $this->assertStringContainsString('rel="self"', $xml);
        preg_match_all('#<item>.*?<title>(.*?)</title>#s', $xml, $m);
        $this->assertNotEmpty($m[1]);
        foreach ($m[1] as $title) {
            $this->assertStringStartsWith('European Union:', html_entity_decode($title));
        }
    }

    public function test_the_news_sitemap_lists_only_what_was_first_published_in_the_last_two_days_about_recent_events(): void
    {
        // Every entry on a fresh database was "first published" now; none of
        // them happened this fortnight, so nothing is news and the index must
        // not point at an empty news sitemap.
        $this->assertSame(0, substr_count($this->get('/sitemap-news.xml')->assertOk()->getContent(), '<url>'));
        $this->assertStringNotContainsString('sitemap-news', $this->get('/sitemap.xml')->getContent());

        $eu = Jurisdiction::where('slug', 'eu')->firstOrFail();
        $fresh = ChangeEvent::create(['slug' => 'test-fresh', 'jurisdiction_id' => $eu->id, 'occurred_on' => now()->subDay()->toDateString(), 'title' => 'A guidance note was published this week', 'what_changed' => 'A regulator published a note.', 'impact_level' => 'routine', 'official_source_url' => 'https://example.org/note', 'published_at' => now()]);
        $stale = ChangeEvent::create(['slug' => 'test-stale', 'jurisdiction_id' => $eu->id, 'occurred_on' => now()->subDays(40)->toDateString(), 'title' => 'An old event logged late', 'what_changed' => 'Logged today, happened long ago.', 'impact_level' => 'routine', 'official_source_url' => 'https://example.org/old', 'published_at' => now()]);
        $this->assertNotNull($fresh->fresh()->first_published_at, 'set once on creation');

        $news = $this->get('/sitemap-news.xml')->assertOk()->getContent();
        $this->assertStringContainsString($fresh->url(), $news);
        $this->assertStringNotContainsString($stale->url(), $news, 'an old event is not news however recently it was logged');
        $this->assertStringContainsString('<news:publication_date>', $news);
        $this->assertStringContainsString('<news:name>AIPolicyTracker</news:name>', $news);
        $this->assertStringContainsString('sitemap-news.xml', $this->get('/sitemap.xml')->getContent());

        // Re-importing does not move the first-published time.
        $before = $fresh->fresh()->first_published_at;
        ChangeEvent::updateOrCreate(['slug' => 'test-fresh'], ['published_at' => now()->addHour()]);
        $this->assertEquals($before, $fresh->fresh()->first_published_at);
    }

    public function test_change_pages_are_news_articles_and_the_updates_sitemap_lists_the_indexable_archives(): void
    {
        $change = ChangeEvent::published()->whereNotNull('official_source_url')->orderByDesc('occurred_on')->firstOrFail();
        $head = substr($this->get($change->url())->assertOk()->getContent(), 0, 20000);
        $this->assertStringContainsString('"@type":"NewsArticle"', $head);
        $this->assertStringContainsString('"dateline"', $head);

        $map = $this->get('/sitemap-updates.xml')->assertOk()->getContent();
        $this->assertStringContainsString(url('/updates'), $map);
        foreach (ChangeEvent::publishedMonths() as $month => $m) {
            $m['count'] >= 3 ? $this->assertStringContainsString(url('/updates/'.$month), $map) : $this->assertStringNotContainsString(url('/updates/'.$month).'<', $map);
        }
        $this->assertStringContainsString(url('/updates/eu'), $map);
        $this->assertStringContainsString('sitemap-updates.xml', $this->get('/sitemap.xml')->getContent());
    }

    public function test_the_latest_updates_module_appears_on_home_policy_and_jurisdiction_pages(): void
    {
        $this->get('/')->assertOk()->assertSee('Latest AI policy updates')->assertSee(route('updates.index'));
        $this->get('/jurisdictions/eu')->assertOk()->assertSee(route('updates.jurisdiction', 'eu'))->assertSee(route('updates.jurisdiction.feed', 'eu'));
        $this->get('/policies/eu-ai-act')->assertOk()->assertSee(route('updates.jurisdiction', 'eu'));
        $this->get('/llms.txt')->assertOk()->assertSee(route('updates.index'))->assertSee(route('sitemap.news'));
    }

    public function test_the_digest_records_an_issue_that_the_newsletter_archive_publishes(): void
    {
        Mail::fake();
        $this->get('/newsletter')->assertOk()->assertSee('No issue has been archived yet')->assertSee('noindex', false);

        $eu = Jurisdiction::where('slug', 'eu')->firstOrFail();
        $ids = [];
        foreach (range(1, 3) as $n) {
            $ids[] = ChangeEvent::create(['slug' => "test-week-{$n}", 'jurisdiction_id' => $eu->id, 'occurred_on' => now()->subDays($n)->toDateString(), 'title' => "Change number {$n} this week", 'what_changed' => 'Something changed.', 'impact_level' => 'high', 'official_source_url' => 'https://example.org/'.$n, 'published_at' => now()])->id;
        }
        Subscriber::create(['email' => 'reader@example.org', 'token' => str_repeat('a', 48), 'confirmed_at' => now(), 'topics' => ['all']]);
        $this->artisan('digest:send')->assertExitCode(0);

        $issue = DigestIssue::firstOrFail();
        $this->assertEqualsCanonicalizing($ids, $issue->change_ids);
        $this->assertSame(1, $issue->recipients);
        $this->assertTrue($issue->isIndexable());

        $html = $this->get('/newsletter/'.$issue->sent_on->toDateString())->assertOk()->getContent();
        $this->assertStringContainsString('<title>'.PageTitle::newsletterIssue($issue->sent_on), $html);
        $this->assertStringContainsString('Change number 1 this week', $html);
        $this->assertStringContainsString('3 changes in AI policy', $html);
        $this->assertStringContainsString('"@type":"Article"', $html);
        $this->get('/newsletter')->assertOk()->assertSee($issue->url())->assertSee('index,follow', false);
        $this->get('/newsletter/1999-01-01')->assertNotFound();
        $this->assertStringContainsString($issue->url(), $this->get('/sitemap-updates.xml')->getContent());

        // Sending again the same day updates the issue rather than adding a second.
        $this->artisan('digest:send')->assertExitCode(0);
        $this->assertSame(1, DigestIssue::count());
    }

    public function test_the_api_carries_significance_and_matches_its_published_contract(): void
    {
        $payload = $this->getJson('/api/v1/changes?per_page=5')->assertOk()->json();
        $this->assertMatchesOpenApi('/changes', $payload);
        foreach ($payload['data'] as $row) {
            $this->assertIsInt($row['significance']);
            $this->assertGreaterThanOrEqual(0, $row['significance']);
            $this->assertLessThanOrEqual(100, $row['significance']);
        }
        $since = now()->subYear()->toDateString();
        foreach ($this->getJson('/api/v1/changes?since='.$since)->assertOk()->json('data') as $row) {
            $this->assertGreaterThanOrEqual($since, $row['occurred_on']);
        }
    }

    private function assertTitleRules(string $html): void
    {
        preg_match('#<title>(.*?)</title>#s', $html, $m);
        $title = html_entity_decode($m[1] ?? '', ENT_QUOTES | ENT_HTML5);
        $this->assertNotSame('', $title);
        $this->assertLessThanOrEqual(PageTitle::MAX, mb_strlen($title), $title);
        $this->assertFalse(PageTitle::leaksIdentifier($title), $title);
        $this->assertSame(1, preg_match_all('#<h1\b#', $html), 'one H1');
    }
}
