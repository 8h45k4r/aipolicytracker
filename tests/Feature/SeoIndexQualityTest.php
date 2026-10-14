<?php

namespace Tests\Feature;

use App\Models\ChangeEvent;
use App\Models\ExternalIncident;
use App\Models\JobRun;
use App\Models\Jurisdiction;
use App\Models\Subscriber;
use App\Services\Seo\IndexNow;
use App\Support\RiskTaxonomy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Index quality and measurement rules from the SEO/AEO audit of October 2026:
 * listings that name nothing, single-country listings, site updates presented
 * as news, per-record Dataset markup, thin incident pages, analytics on token
 * URLs and IndexNow after admin edits.
 */
class SeoIndexQualityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('policy:import');
    }

    /** A change entry recording a new template version, as the templates build writes one. */
    private function siteUpdate(): ChangeEvent
    {
        $eu = Jurisdiction::where('slug', 'eu')->firstOrFail();

        return ChangeEvent::create([
            'slug' => ChangeEvent::SITE_UPDATE_PREFIX.'ai-risk-register-v9', 'jurisdiction_id' => $eu->id, 'occurred_on' => now()->toDateString(),
            'title' => 'AI risk register template: version 9', 'what_changed' => 'A new version of the template was published.',
            'impact_level' => 'low', 'official_source_url' => url('/templates/ai-risk-register'), 'source_title' => 'AI risk register template',
            'review_status' => 'verified', 'published_at' => now()->subMinute(), 'first_published_at' => now()->subMinute(),
        ]);
    }

    private function robots(string $html): string
    {
        preg_match('#<meta name="robots" content="([^"]*)"#', $html, $m);

        return $m[1] ?? '';
    }

    private function canonical(string $html): string
    {
        preg_match('#<link rel="canonical" href="([^"]*)"#', $html, $m);

        return $m[1] ?? '';
    }

    /** @return list<array<string,mixed>> every JSON-LD node on the page */
    private function nodes(string $html): array
    {
        preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $html, $m);

        return collect($m[1])->map(fn ($j) => json_decode($j, true))->flatMap(fn ($d) => $d['@graph'] ?? [$d])->all();
    }

    public function test_a_listing_filtered_by_nothing_that_exists_is_not_indexed(): void
    {
        foreach (['/policies?jurisdiction=zz', '/policies?status=zz', '/obligations?category=zz'] as $url) {
            $html = $this->get($url)->assertOk()->getContent();
            $this->assertStringStartsWith('noindex', $this->robots($html), $url);
            $this->assertSame(url(strtok($url, '?')), $this->canonical($html), $url);
        }
        $html = $this->get('/obligations?category=transparency')->assertOk()->getContent();
        $this->assertStringStartsWith('index', $this->robots($html));
        $this->assertSame(url('/obligations?category=transparency'), $this->canonical($html));
    }

    public function test_a_page_past_the_last_is_a_404(): void
    {
        $this->get('/policies?page=999')->assertNotFound();
        $this->get('/obligations?page=999')->assertNotFound();
        $this->get('/changes?page=999')->assertNotFound();
        $this->get('/policies?page=2')->assertOk();
    }

    public function test_a_single_country_listing_defers_to_the_country_page(): void
    {
        $html = $this->get('/policies?jurisdiction=japan')->assertOk()->getContent();
        $this->assertStringStartsWith('noindex,follow', $this->robots($html));
        $this->assertStringNotContainsString('policies?jurisdiction=', $this->get(route('sitemap.section', 'static'))->getContent());

        // The country page lists every instrument the listing would.
        preg_match_all('#href="'.preg_quote(url('/policies'), '#').'/([a-z0-9-]+)"#', $html, $listing);
        $hub = $this->followingRedirects()->get('/jurisdictions/japan')->getContent();
        foreach (array_unique($listing[1]) as $slug) {
            $this->assertStringContainsString(route('policies.show', $slug), $hub, $slug);
        }
    }

    public function test_a_site_update_is_not_offered_as_news(): void
    {
        $update = $this->siteUpdate();
        $this->assertTrue($update->isSiteUpdate());

        $html = $this->get($update->url())->assertOk()->getContent();
        $this->assertStringStartsWith('noindex', $this->robots($html));
        $types = array_column($this->nodes($html), '@type');
        $this->assertContains('WebPage', $types);
        $this->assertNotContains('NewsArticle', $types);
        $this->assertStringNotContainsString($update->url(), $this->get(route('sitemap.section', 'changes'))->getContent());
        $this->assertStringNotContainsString($update->url(), $this->get(route('sitemap.news'))->getContent());

        $news = ChangeEvent::published()->regulatory()->whereNotNull('official_source_url')->firstOrFail();
        $node = collect($this->nodes($this->get($news->url())->getContent()))->firstWhere('@type', 'NewsArticle');
        $this->assertNotNull($node);
        $this->assertArrayNotHasKey('reviewedBy', $node, 'reviewedBy is a WebPage property');
        $this->assertArrayNotHasKey('lastReviewed', $node);
    }

    public function test_record_pages_are_item_pages_without_a_dataset_of_one(): void
    {
        $policy = collect($this->nodes($this->get(route('policies.show', 'eu-ai-act'))->getContent()));
        $page = $policy->firstWhere('@type', 'ItemPage');
        $this->assertNotNull($page);
        $this->assertSame(route('policies.show', 'eu-ai-act').'#legislation', $page['mainEntity']['@id']);
        $this->assertContains('https://eur-lex.europa.eu/eli/reg/2024/1689/oj', $page['isBasedOn']);
        $this->assertNull($policy->firstWhere('@type', 'Dataset'));
        $this->assertNull($policy->firstWhere('@type', 'Article'));

        $obligation = collect($this->nodes($this->get(route('obligations.show', 'eu-ai-act-ai-literacy'))->getContent()));
        $this->assertNotNull($obligation->firstWhere('@type', 'ItemPage'));
        $this->assertNull($obligation->firstWhere('@type', 'Dataset'));
        $this->assertNull(collect($this->nodes($this->followingRedirects()->get('/jurisdictions/japan')->getContent()))->firstWhere('@type', 'Dataset'));

        // Collections keep theirs.
        $this->assertNotNull(collect($this->nodes($this->get('/')->getContent()))->firstWhere('@type', 'Dataset'));
    }

    public function test_an_incident_page_is_indexed_only_with_something_of_its_own(): void
    {
        $long = 'A screening model trained on historic hiring data rejected applicants from particular postcodes at a markedly higher rate, and the employer used its scores to decide who reached an interview for two rounds.';
        $base = ['occurred_on' => '2026-03-04', 'year' => 2026, 'report_count' => 3, 'snapshot_date' => '2026-09-07'];
        $classified = ExternalIncident::create($base + ['incident_id' => 9101, 'title' => 'Classified', 'description' => $long, 'mit_domain' => '1', 'mit_subdomain' => '1.1']);
        $short = ExternalIncident::create($base + ['incident_id' => 9102, 'title' => 'Short', 'description' => 'A model misbehaved once.', 'mit_domain' => '1', 'mit_subdomain' => '1.1']);
        $bare = ExternalIncident::create($base + ['incident_id' => 9103, 'title' => 'Unclassified', 'description' => $long]);

        $this->assertTrue($classified->isIndexable());
        $this->assertFalse($short->isIndexable(), 'under 25 words');
        $this->assertFalse($bare->isIndexable(), 'no classification and no law on record');
        $bare->forceFill(['related_policy_slugs' => ['eu-ai-act']])->save();
        $this->assertTrue($bare->fresh()->isIndexable(), 'a law on record is something of its own');
    }

    public function test_analytics_never_loads_on_a_page_whose_address_carries_a_token(): void
    {
        config(['aipolicytracker.google_analytics_id' => 'G-TEST123', 'aipolicytracker.analytics_require_consent' => true]);
        $subscriber = Subscriber::create(['email' => 'reader@example.org', 'token' => Subscriber::newToken(), 'topics' => ['all'], 'confirmed_at' => now()]);

        $this->get('/')->assertOk()->assertSee('APT_ANALYTICS', false);
        $this->get(route('subscribe.unsubscribe', $subscriber->token))->assertOk()->assertDontSee('APT_ANALYTICS', false)->assertDontSee('G-TEST123', false);
        $this->get(route('subscribe.confirm', $subscriber->token))->assertOk()->assertDontSee('G-TEST123', false);

        // The account pages carry no analytics, and the old account shell, should a page use
        // it again, loads it only where consent is not required and never on a token route.
        $this->get(route('password.reset', str_repeat('t', 64)))->assertOk()->assertDontSee('G-TEST123', false);
        $this->assertStringContainsString("! config('aipolicytracker.analytics_require_consent') && \\App\\Support\\Analytics::allowedHere()", file_get_contents(resource_path('views/app.blade.php')));
    }

    public function test_ai_referral_waits_for_consent_and_records_no_query_string(): void
    {
        $js = file_get_contents(resource_path('js/public.js'));
        $this->assertStringContainsString('flushAiReferral();', $js);
        $this->assertMatchesRegularExpression('/function loadAnalytics\(\)\s*\{.*flushAiReferral\(\);\s*\}/s', $js);
        $this->assertStringContainsString('landing_path: location.pathname', $js);
        $this->assertStringNotContainsString('location.search }', $js);
    }

    public function test_indexnow_runs_hourly_and_skips_what_the_pages_decline(): void
    {
        $this->assertSame('seo:indexnow', JobRun::JOBS['indexnow']['command']);

        $update = $this->siteUpdate();
        $urls = app(IndexNow::class)->changedSince(now()->subMinute());
        $this->assertNotContains($update->url(), $urls);
    }

    public function test_risk_domain_pages_answer_first_and_keep_coverage_apart_from_applicability(): void
    {
        $html = $this->get(RiskTaxonomy::domainUrl(1))->assertOk()->getContent();
        $this->assertStringContainsString('is domain 1 of the MIT AI Risk Repository', html_entity_decode($html, ENT_QUOTES));
        $this->assertStringContainsString('Which laws on record cover this risk area?', $html);
        $this->assertStringContainsString('it is not a finding that it applies', $html);
    }

    public function test_obligation_pages_link_the_terms_they_use(): void
    {
        $this->get(route('obligations.show', 'eu-ai-act-ai-literacy'))->assertOk()
            ->assertSee('Terms explained')
            ->assertSee(route('glossary.show', 'ai-literacy'), false);
    }
}
