<?php

namespace Tests\Feature;

use App\Models\ExternalIncident;
use App\Models\ExternalRisk;
use App\Services\ExternalData\AttackLandscape;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/** /ai-attacks: recorded incidents as charts, and the figures the charts are drawn from. */
class AttackLandscapeTest extends TestCase
{
    use RefreshDatabase;

    private int $id = 70000;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-18 09:00:00');
        ExternalIncident::query()->delete();
        ExternalRisk::query()->delete();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** One incident; without $code it is recorded but not yet classified. */
    private function incident(string $date, ?array $code = null): void
    {
        [$entity, $intent, $domain, $sub] = $code ?? [null, null, null, null];
        ExternalIncident::create([
            'incident_id' => ++$this->id, 'title' => 'Incident '.$this->id, 'description' => 'A description.',
            'occurred_on' => $date, 'year' => (int) substr($date, 0, 4), 'report_count' => 1, 'snapshot_date' => '2026-09-07',
            'developers' => ['A developer'], 'deployers' => ['A deployer'], 'harmed' => ['General public, Bank customers'],
            'entity' => $entity, 'intent' => $intent, 'timing' => $entity ? 'Post-deployment' : null,
            'mit_domain' => $domain, 'mit_subdomain' => $sub,
        ]);
    }

    private const FRAUD = ['Human', 'Intentional', 'Malicious Actors & Misuse', 'Fraud, scams, and targeted manipulation'];

    private const FAILURE = ['AI', 'Unintentional', 'AI system safety, failures, and limitations', 'Lack of capability or robustness'];

    /**
     * Twelve fraud incidents in the year to March 2026 against six the year before, four
     * failures in each, and five incidents after March that nobody has classified yet.
     */
    private function record(): void
    {
        foreach (['2025-04', '2025-05', '2025-06', '2025-07', '2025-08', '2025-09', '2025-10', '2025-11', '2025-12', '2026-01', '2026-02', '2026-03'] as $month) {
            $this->incident($month.'-10', self::FRAUD);
        }
        foreach (['2026-01-05', '2026-02-05', '2026-03-05', '2026-03-20'] as $date) {
            $this->incident($date, self::FAILURE);
        }
        foreach (range(1, 6) as $n) {
            $this->incident(sprintf('2024-%02d-10', $n + 4), self::FRAUD);
        }
        foreach (['2024-06-01', '2024-09-01', '2024-12-01', '2025-02-01'] as $date) {
            $this->incident($date, self::FAILURE);
        }
        foreach (['2026-05-02', '2026-06-02', '2026-07-02', '2026-08-02', '2026-08-20'] as $date) {
            $this->incident($date);
        }
        foreach ([['01.01.00', 'Intentional', 4, '4.3'], ['01.02.00', 'Unintentional', 7, '7.3'], ['01.03.00', 'Unintentional', 7, '7.3'], ['01.04.00', 'Unintentional', 7, '7.1']] as [$ev, $intent, $domain, $sub]) {
            ExternalRisk::create(['ev_id' => $ev, 'quick_ref' => 'Paper', 'paper_title' => 'A paper', 'level' => 'Risk Category', 'risk_category' => 'A risk', 'entity' => 'AI', 'intent' => $intent, 'timing' => 'Post-deployment', 'domain' => $domain, 'subdomain' => $sub]);
        }
    }

    public function test_a_year_is_compared_only_up_to_the_last_month_that_is_classified(): void
    {
        $this->record();

        $d = app(AttackLandscape::class)->build('all');

        // Counting to today, the unclassified months would make every kind of incident
        // look as if it had stopped. The windows end at March 2026 instead.
        $this->assertSame('2026-03', $d['window']['through']);
        $this->assertSame('2025-04-01', $d['window']['last_from']);
        $this->assertSame(5, $d['window']['unclassified_since']);
        $this->assertSame(16, $d['totals']['last12']);
        $this->assertSame(10, $d['totals']['prev12']);
        $this->assertSame(60, $d['totals']['growth']);

        $fraud = collect($d['vectors'])->firstWhere('id', '4.3');
        $this->assertSame([12, 6, 100, 'rising'], [$fraud['last12'], $fraud['prev12'], $fraud['growth'], $fraud['signal']]);
        $this->assertSame(100, $fraud['intentional_share']);
        // The months after the cut-off are drawn, and marked as unsettled.
        $this->assertFalse($d['months']['2026-08']['settled']);
        $this->assertSame(['all' => 2, 'lens' => 0, 'settled' => false], $d['months']['2026-08']);
        $this->assertTrue($d['months']['2026-03']['settled']);
    }

    public function test_a_lens_selects_incidents_and_the_risk_entries_coded_the_same_way(): void
    {
        $this->record();
        $landscape = app(AttackLandscape::class);

        $deliberate = $landscape->build('deliberate');
        $this->assertSame(18, $deliberate['totals']['lens']);
        $this->assertSame(69, $deliberate['totals']['share']); // 18 of the 26 classified
        $this->assertSame(1, $deliberate['totals']['risks']);
        $this->assertSame(['Human' => ['Intentional' => 18]], $deliberate['flow']['entity_intent']);

        $security = $landscape->build('security');
        $this->assertSame(18, $security['totals']['lens']);
        $this->assertSame(['4.3'], array_column($security['points'], 'id'));

        // Anything that is not a lens is the whole record, never an error.
        $this->assertSame('all', AttackLandscape::lens('nonsense'));
        $all = $landscape->build('all');
        $this->assertSame(31, $all['totals']['lens']);
        $this->assertSame(26, $all['totals']['classified_corpus']);
        $this->assertSame(['General public' => 31, 'Bank customers' => 31], $all['harmed']);

        // Shares set the record against the frameworks: fraud is 18 of 26 incidents and 1 of 4 entries.
        $fraud = collect($all['points'])->firstWhere('id', '4.3');
        $this->assertSame([69.2, 25.0], [$fraud['incident_share'], $fraud['risk_share']]);
        $unrecorded = collect($all['points'])->firstWhere('id', '7.1');
        $this->assertSame([0, 0.0, 25.0], [$unrecorded['incidents'], $unrecorded['incident_share'], $unrecorded['risk_share']]);
    }

    public function test_the_direction_of_a_vector_follows_the_printed_rules(): void
    {
        $this->assertSame('rising', AttackLandscape::signal(14, 5, 180));
        $this->assertSame('steady', AttackLandscape::signal(153, 147, 4));
        $this->assertSame('easing', AttackLandscape::signal(16, 23, -30));
        $this->assertSame('easing', AttackLandscape::signal(2, 12, -83));
        $this->assertSame('emerging', AttackLandscape::signal(9, 4, 125));
        $this->assertSame('sparse', AttackLandscape::signal(2, 4, -50));
        $this->assertSame('sparse', AttackLandscape::signal(2, 0, null));
        $this->assertSame('rising', AttackLandscape::signal(10, 0, null));
    }

    public function test_rank_correlation_handles_ties(): void
    {
        $this->assertSame(1.0, AttackLandscape::spearman([1, 2, 3, 4], [10, 20, 30, 40]));
        $this->assertSame(-1.0, AttackLandscape::spearman([1, 2, 3, 4], [40, 30, 20, 10]));
        $this->assertSame(0.95, AttackLandscape::spearman([1, 2, 2, 4], [10, 20, 30, 40]));
        $this->assertNull(AttackLandscape::spearman([1, 1, 1], [1, 2, 3]));
        $this->assertNull(AttackLandscape::spearman([1, 2], [1, 2]));
    }

    public function test_the_page_states_its_figures_in_text_and_lists_no_records(): void
    {
        $this->record();

        $response = $this->get('/ai-attacks')->assertOk();

        $response->assertSee('AI attacks and incidents: the record in charts')
            ->assertSee('The AI Incident Database records 31 incidents, 26 of them classified with the MIT risk taxonomy.')
            ->assertSee('16 are dated in the 12 months to March 2026, up 60% on the 10 of the 12 months before.')
            ->assertSee('5 newer incidents are recorded but not yet classified, so comparisons stop at March 2026.')
            ->assertSee('1. Rising: up 60% in the 12 months to March 2026')
            ->assertSee('Which kind of incident is recorded most?')
            ->assertSee('4.3 Fraud, scams, and targeted manipulation: 12 of the 16 recorded AI incidents dated in the 12 months to March 2026 (75%).')
            ->assertSee('data-signal="rising"', false)
            ->assertSee('data-chart="from-cause-to-harm-responsible-entity-intent-and-risk-domain"', false)
            ->assertSee('"@type":"FAQPage"', false)
            ->assertSee('<meta name="robots" content="index,follow', false);
        // Charts only: no incident is named or linked from this page.
        $response->assertDontSee('Incident 70001')->assertDontSee('/ai-risk/incidents/incident-');
        $this->assertSame(1, substr_count($response->getContent(), '<h1'));
    }

    public function test_a_lens_is_the_same_page_and_is_not_offered_to_search_engines(): void
    {
        $this->record();

        $this->get('/ai-attacks?lens=deliberate')->assertOk()
            ->assertSee('18 of the 26 classified incidents in the AI Incident Database (69%) are coded as deliberate')
            ->assertSee('<meta name="robots" content="noindex,follow">', false)
            ->assertDontSee('"@type":"FAQPage"', false)
            ->assertSee('aria-current="true" >Deliberate misuse', false);
        $this->get('/ai-attacks?lens=security')->assertOk()->assertSee('fall under the two MIT risk domains for security and malicious use');
        // An unknown lens shows the whole record, and is still not a second indexable address.
        $this->get('/ai-attacks?lens=nonsense')->assertOk()
            ->assertSee('The AI Incident Database records 31 incidents')
            ->assertSee('<meta name="robots" content="noindex,follow">', false);
    }

    public function test_every_component_on_the_page_is_rendered(): void
    {
        $this->record();

        // A malformed attribute leaves a component tag in the page as raw source; the
        // first stat tile shipped that way and pulled the others into one column.
        foreach (['', '?lens=deliberate', '?lens=security'] as $query) {
            $html = $this->get('/ai-attacks'.$query)->assertOk()->getContent();
            $this->assertStringNotContainsString('<x-site', $html, $query ?: 'all');
            $this->assertStringNotContainsString('{{', $html, $query ?: 'all');
        }
        $this->get('/ai-attacks')->assertSee('<dt class="stat-label">Recorded incidents</dt>', false);
    }

    public function test_the_year_by_intent_chart_and_the_harm_matrix_count_the_same_incidents(): void
    {
        $this->record();

        $d = app(AttackLandscape::class)->build('all');

        $this->assertSame(['Intentional' => 6, 'Unintentional' => 3, 'Other' => 0], $d['intent_years'][2024]);
        $this->assertSame(['Intentional' => 9, 'Unintentional' => 1, 'Other' => 0], $d['intent_years'][2025]);
        // Every party named on an incident is counted under that incident's domain.
        $this->assertSame(['total' => 26, 'cells' => ['4' => 18, '7' => 8]], $d['harm_matrix']['General public']);

        $this->get('/ai-attacks')->assertOk()
            ->assertSee('From accident to attack, year by year')
            // No year here has the 20 classified incidents a stated share needs.
            ->assertDontSee('of the classified incidents in view were coded deliberate')
            ->assertSee('Who is harmed, by kind of risk')
            ->assertSee('data-signal-filter="rising"', false);
        // In the deliberate view every incident is deliberate, so the chart would say nothing.
        $this->get('/ai-attacks?lens=deliberate')->assertOk()->assertDontSee('From accident to attack');
    }

    public function test_the_page_says_so_when_the_daily_sync_has_stopped_delivering(): void
    {
        $this->record();
        ExternalIncident::query()->update(['synced_at' => now()->subDays(9)]);

        $this->get('/ai-attacks')->assertOk()
            ->assertSee('No new record has arrived from the daily sync for 9 days; the figures stop there')
            ->assertDontSee('synced from the source once a day');

        ExternalIncident::query()->update(['synced_at' => now()->subHours(5)]);
        app(Repository::class)->flush();

        $this->get('/ai-attacks')->assertOk()
            ->assertSee('synced from the source once a day')
            ->assertDontSee('No new record has arrived');
    }

    public function test_chart_labels_keep_their_contrast(): void
    {
        // White text only where it reaches 4.5:1 on the shaded cell: a crimson cell at about
        // 60% strength measured 2.9:1 with white text on the live page.
        $html = Blade::render('<x-site.harm-matrix :rows="$rows" :domains="$domains" :colours="$colours" title="t" />', [
            'rows' => ['Strong' => ['total' => 100, 'cells' => ['4' => 100]], 'Medium' => ['total' => 100, 'cells' => ['4' => 65]]],
            'domains' => ['4' => 'Malicious actors'], 'colours' => AttackLandscape::DOMAIN_COLOURS,
        ]);
        $this->assertSame(1, substr_count($html, 'harm-hi'));
        $this->assertMatchesRegularExpression('/class="[^"]*harm-hi[^"]*"[^>]*data-tip="Strong/', $html);

        // The bubble chart holds links, so it is a group of them, not a single image.
        $this->record();
        $this->get('/ai-attacks')->assertOk()->assertSee('role="group" aria-label="Risk subdomains: share of risk entries against share of incidents"', false);

        // Treemap cells alternate two solid shades; a faded cell faded its white label to 3.8:1.
        $tree = Blade::render('<x-site.treemap :rows="$rows" title="t" />', ['rows' => [['label' => 'D', 'url' => '#', 'total' => 2, 'cells' => [['label' => 'a', 'url' => '#', 'value' => 1], ['label' => 'b', 'url' => '#', 'value' => 1]]]]]);
        $this->assertStringNotContainsString('opacity:', $tree);
        $this->assertStringContainsString('bg-brand-blue', $tree);
    }

    public function test_the_page_renders_before_any_data_is_imported(): void
    {
        $this->get('/ai-attacks')->assertOk()
            ->assertSee('The AI Incident Database records 0 incidents')
            ->assertSee('No classified incidents in view');
    }

    public function test_the_open_page_can_tell_when_the_record_has_moved(): void
    {
        $this->record();
        $before = $this->getJson('/ai-attacks/pulse.json')->assertOk()->assertHeader('X-Robots-Tag', 'noindex')->json('version');
        $this->assertSame($before, $this->getJson('/ai-attacks/pulse.json')->json('version'));

        $this->incident('2026-09-01');

        $this->assertNotSame($before, $this->getJson('/ai-attacks/pulse.json')->json('version'));
    }

    public function test_the_page_is_linked_from_the_site_and_the_machine_readable_surfaces(): void
    {
        $this->get('/ai-risk')->assertOk()->assertSee(route('attacks.index'), false);
        $this->get('/sitemap-static.xml')->assertOk()->assertSee('<loc>'.route('attacks.index').'</loc>', false);
        $this->get('/llms.txt')->assertOk()->assertSee(route('attacks.index'), false);
    }
}
