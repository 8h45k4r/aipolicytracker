<?php

namespace Tests\Feature;

use App\Models\ChangeEvent;
use App\Models\Jurisdiction;
use App\Models\PolicyInstrument;
use App\Services\Records\AnswerBox;
use App\Support\PageTitle;
use App\Support\Seo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * How the home page and the record pages present what the records say: a feed of
 * changes to the law, one "last updated" date, a stats row that says what is new,
 * descriptions that end on a sentence, and an answer first on the regional hubs.
 */
class HomeAndRecordPresentationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('policy:import');
    }

    private function change(string $slug, array $attributes = []): ChangeEvent
    {
        $eu = Jurisdiction::where('slug', 'eu')->firstOrFail();

        return ChangeEvent::create($attributes + [
            'slug' => $slug, 'jurisdiction_id' => $eu->id, 'occurred_on' => now()->toDateString(),
            'title' => 'Entry '.$slug, 'what_changed' => 'Recorded for the test.', 'impact_level' => 'routine',
            'official_source_url' => 'https://eur-lex.europa.eu/eli/reg/2024/1689/oj', 'source_title' => 'Official Journal',
            'review_status' => 'unverified', 'published_at' => now()->subMinute(),
        ]);
    }

    private function feed(string $html): string
    {
        preg_match('#<section[^>]*aria-labelledby="latest-updates-heading">(.*?)</section>#s', $html, $m);

        return $m[1] ?? '';
    }

    public function test_the_home_feed_leaves_out_template_releases_and_folds_repeat_entries(): void
    {
        $this->change(ChangeEvent::SITE_UPDATE_PREFIX.'ai-risk-register-v9', ['title' => 'Template v9 released: AI risk register']);
        $act = PolicyInstrument::where('slug', 'eu-ai-act')->firstOrFail();
        foreach ([1, 2, 3] as $i) {
            $this->change('test-eu-ai-act-revision-'.$i, ['policy_instrument_id' => $act->id, 'title' => 'EU AI Act guidance revised, part '.$i]);
        }

        $feed = $this->feed($this->get('/')->assertOk()->getContent());

        $this->assertStringNotContainsString('Template v9 released', $feed);
        $this->assertStringContainsString('EU AI Act guidance revised, part 3', $feed, 'the newest entry stands for the week');
        $this->assertStringNotContainsString('part 2', $feed);
        $this->assertStringContainsString('(3 updates this week)', $feed);
    }

    public function test_a_feed_title_that_names_no_place_is_given_the_place_and_instrument(): void
    {
        $act = PolicyInstrument::where('slug', 'eu-ai-act')->firstOrFail();
        $change = $this->change('test-guidelines-published', ['policy_instrument_id' => $act->id, 'title' => 'Guidelines on prohibited practices published']);

        $this->assertSame('EU AI Act: Guidelines on prohibited practices published', $change->load('jurisdiction', 'policyInstrument')->headline());
        $this->assertStringContainsString('EU AI Act: Guidelines on prohibited practices published', $this->feed($this->get('/')->getContent()));
    }

    public function test_template_releases_are_behind_the_templates_filter_on_the_updates_hub(): void
    {
        $this->change(ChangeEvent::SITE_UPDATE_PREFIX.'ai-risk-register-v9', ['title' => 'Template v9 released: AI risk register']);

        $this->get('/updates')->assertOk()->assertDontSee('Template v9 released')->assertSee('value="templates"', false);
        $this->get('/updates?kind=templates')->assertOk()->assertSee('Template v9 released');
    }

    public function test_the_trust_panel_the_feed_and_the_json_ld_share_one_last_updated_date(): void
    {
        $html = $this->get('/')->assertOk()->getContent();
        $modified = Seo::corpusModified();
        $this->assertNotNull($modified);

        $this->assertMatchesRegularExpression('#Corpus last updated</dt><dd[^>]*>'.preg_quote($modified->format('j M Y'), '#').'</dd>#', $html);
        $this->assertStringContainsString('"@type":"CollectionPage"', $html);
        preg_match('#"@type":"CollectionPage".*?"dateModified":"([^"]+)"#s', $html, $m);
        $this->assertSame($modified->toDateString(), Carbon::parse($m[1])->toDateString());
        // No item in the feed is dated after the date the panel gives.
        preg_match_all('#<time datetime="(\d{4}-\d{2}-\d{2})" class="datestamp">#', $this->feed($html), $dates);
        foreach ($dates[1] as $date) {
            $this->assertLessThanOrEqual($modified->toDateString(), $date);
        }
    }

    public function test_the_stats_row_links_each_count_says_what_is_new_and_has_a_quotable_sentence(): void
    {
        $html = $this->get('/')->assertOk()->getContent();
        foreach (['jurisdictions.index', 'policies.index', 'obligations.index', 'controls.index'] as $route) {
            $this->assertStringContainsString('href="'.route($route).'" class="chain-link', $html);
        }
        // Every record was imported this month in a fresh database.
        $this->assertMatchesRegularExpression('#data-new-this-month>\+\d+ this month#', $html);
        $instruments = PolicyInstrument::published()->count();
        $jurisdictions = Jurisdiction::published()->count();
        $this->assertStringContainsString("AIPolicyTracker tracks {$instruments} AI policy instruments across {$jurisdictions} jurisdictions, mapped to", $html);

        // Next month nothing is new, and nothing is said about it.
        Carbon::setTestNow(now()->addMonthNoOverflow()->startOfMonth()->addDay());
        $this->assertStringNotContainsString('data-new-this-month', $this->get('/')->assertOk()->getContent());
        Carbon::setTestNow();
    }

    public function test_the_home_page_shows_the_map_its_description_promises(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('#<meta name="description" content="[^"]*on a map#', $html);
        $this->assertStringContainsString('data-world-map="world-map-data"', $html);
    }

    public function test_record_descriptions_are_built_from_the_data_and_end_on_a_sentence(): void
    {
        $act = PolicyInstrument::where('slug', 'eu-ai-act')->firstOrFail();
        foreach ([$act->url(), Jurisdiction::where('slug', 'japan')->firstOrFail()->url()] as $url) {
            preg_match('#<meta name="description" content="([^"]*)"#', $this->get($url)->assertOk()->getContent(), $m);
            $d = html_entity_decode($m[1], ENT_QUOTES);
            $this->assertLessThanOrEqual(PageTitle::MAX_DESCRIPTION, mb_strlen($d), $url);
            $this->assertMatchesRegularExpression('/[.!?]$/u', $d, $url);
        }
        preg_match('#<meta name="description" content="([^"]*)"#', $this->get($act->url())->getContent(), $m);
        $this->assertStringStartsWith('EU AI Act', html_entity_decode($m[1], ENT_QUOTES));
        $this->assertStringEndsWith('Official sources linked.', html_entity_decode($m[1], ENT_QUOTES));
        preg_match('#<meta name="description" content="([^"]*)"#', $this->get(Jurisdiction::where('slug', 'japan')->firstOrFail()->url())->getContent(), $m);
        $this->assertStringStartsWith('Japan AI regulation: ', html_entity_decode($m[1], ENT_QUOTES));
    }

    public function test_a_regional_hub_opens_with_an_in_brief_answer_of_forty_to_sixty_words(): void
    {
        foreach (['ai-regulation-asia', 'ai-regulation-africa', 'ai-regulation-americas'] as $hub) {
            $html = $this->get('/'.$hub)->assertOk()->getContent();
            $this->assertStringContainsString('<h2 id="answer-heading" class="eyebrow">In brief</h2>', $html, $hub);
            preg_match('#<p[^>]*data-answer>(.*?)</p>#s', $html, $m);
            $words = AnswerBox::wordCount(html_entity_decode(strip_tags($m[1]), ENT_QUOTES));
            $this->assertGreaterThanOrEqual(AnswerBox::MIN_WORDS, $words, $hub);
            $this->assertLessThanOrEqual(AnswerBox::MAX_WORDS, $words, $hub);
        }
    }
}
