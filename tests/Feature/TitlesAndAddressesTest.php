<?php

namespace Tests\Feature;

use App\Models\ChangeEvent;
use App\Models\Control;
use App\Models\ExternalIncident;
use App\Models\ExternalRisk;
use App\Models\Jurisdiction;
use App\Models\Obligation;
use App\Models\PolicyInstrument;
use App\Services\ExternalData\IncidentSensitivity;
use App\Support\PageTitle;
use App\Support\RiskTaxonomy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\OpenApiContract;
use Tests\TestCase;

/**
 * P1: every title on the site is ≤60 characters with no identifier in it, and
 * every record has a readable address, with the old key-based addresses
 * redirecting permanently.
 */
class TitlesAndAddressesTest extends TestCase
{
    use OpenApiContract;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('policy:import');
    }

    public function test_every_record_title_in_the_corpus_meets_the_rules(): void
    {
        $sets = [
            'policy' => PolicyInstrument::published()->with('jurisdiction')->get()->map(fn ($r) => PageTitle::policy($r)),
            'jurisdiction' => Jurisdiction::published()->get()->map(fn ($r) => PageTitle::jurisdiction($r)),
            'obligation' => Obligation::published()->with('policyInstrument')->get()->map(fn ($r) => PageTitle::obligation($r)),
            'change' => ChangeEvent::published()->with('jurisdiction')->get()->map(fn ($r) => PageTitle::change($r)),
            'control' => Control::query()->get()->map(fn ($r) => PageTitle::control($r)),
        ];
        foreach ($sets as $type => $titles) {
            $this->assertNotEmpty($titles, $type);
            foreach ($titles as $title) {
                $rendered = PageTitle::withBrand($title);
                $this->assertLessThanOrEqual(PageTitle::MAX, mb_strlen($rendered), "{$type}: {$rendered}");
                $this->assertDoesNotMatchRegularExpression('/\b[a-z]+\d{4,}\b/', mb_strtolower($rendered), "{$type}: {$rendered}");
                $this->assertFalse(PageTitle::leaksIdentifier($rendered), "{$type}: {$rendered}");
            }
            $this->assertSame([], $titles->countBy()->filter(fn ($n) => $n > 1)->keys()->all(), "duplicate {$type} titles");
        }
    }

    public function test_the_requested_patterns_are_what_the_records_get(): void
    {
        $eu = PolicyInstrument::with('jurisdiction')->where('slug', 'eu-ai-act')->firstOrFail();
        $this->assertSame('EU AI Act (2024): Status, Duties & Dates', PageTitle::policy($eu));
        $brazil = Jurisdiction::where('slug', 'brazil')->firstOrFail();
        $this->assertSame('Brazil AI Regulation 2026: Laws, Strategy & Deadlines', PageTitle::jurisdiction($brazil, 2026));
        // The country is the last thing a policy title gives up.
        foreach (PolicyInstrument::published()->with('jurisdiction')->get() as $p) {
            $place = $p->jurisdiction?->short_name ?: $p->jurisdiction?->name;
            $this->assertTrue(str_contains(PageTitle::policy($p), (string) $place) || str_contains($p->short_title ?: $p->title, (string) $place) || ! $place, $p->slug.': '.PageTitle::policy($p));
        }
    }

    public function test_incident_and_risk_titles_and_addresses_meet_the_rules_and_survive_a_reimport(): void
    {
        $this->artisan('external:import')->assertExitCode(0);

        $incidents = ExternalIncident::all();
        $risks = ExternalRisk::all();
        $this->assertSame(0, $incidents->whereNull('slug')->count(), 'every incident has an address');
        $this->assertSame(0, $risks->whereNull('slug')->count(), 'every risk has an address');
        $this->assertSame($incidents->count(), $incidents->pluck('slug')->unique()->count());
        $this->assertSame($risks->count(), $risks->pluck('slug')->unique()->count());

        foreach ([$incidents->filter->isIndexable()->map(fn ($i) => [PageTitle::incident($i), $i->slug]), $risks->filter->isIndexable()->map(fn ($r) => [PageTitle::risk($r), $r->slug])] as $set) {
            foreach ($set as [$title, $slug]) {
                $this->assertLessThanOrEqual(PageTitle::MAX, mb_strlen(PageTitle::withBrand($title)), $title);
                $this->assertFalse(PageTitle::leaksIdentifier($title), $title);
                $this->assertFalse(PageTitle::pathLeaksIdentifier($slug), $slug);
            }
            $this->assertSame([], $set->map(fn ($x) => $x[0])->countBy()->filter(fn ($n) => $n > 1)->keys()->all(), 'duplicate titles');
        }

        // An address, once published, does not move when the data is imported again.
        $before = ExternalIncident::pluck('slug', 'incident_id')->all() + ExternalRisk::pluck('slug', 'ev_id')->all();
        $this->artisan('external:import')->assertExitCode(0);
        $after = ExternalIncident::pluck('slug', 'incident_id')->all() + ExternalRisk::pluck('slug', 'ev_id')->all();
        $this->assertSame($before, $after);
    }

    public function test_old_key_addresses_redirect_permanently_to_named_ones(): void
    {
        $incident = $this->incident(['title' => 'Chatbot Reportedly Gave Harmful Advice to Users']);
        $this->assertSame('chatbot-reportedly-gave-harmful-advice-to-users', $incident->slug);
        $this->get('/ai-risk/incidents/'.$incident->incident_id)->assertStatus(301)->assertRedirect($incident->url());
        $html = $this->get($incident->url())->assertOk()->getContent();
        $this->assertStringContainsString('<title>Chatbot Reportedly Gave Harmful Advice to Users (2024)', $html);
        $this->assertStringNotContainsString('#'.$incident->incident_id, $this->headOf($html));
        $this->get('/ai-risk/incidents/424242')->assertNotFound();
        $this->get('/ai-risk/incidents/no-such-incident')->assertNotFound();

        $risk = ExternalRisk::create(['ev_id' => '99.01.00', 'quick_ref' => 'Hendrycks2023', 'paper_title' => 'An Overview of Catastrophic AI Risks', 'level' => 'Risk Category', 'risk_category' => 'Rogue AIs', 'description' => 'AI systems pursuing goals against human interests.', 'domain' => '7', 'subdomain' => '7.1']);
        $this->assertSame('rogue-ais', $risk->slug);
        $this->get('/ai-risk/risks/99.01.00')->assertStatus(301)->assertRedirect($risk->url());
        $html = $this->get($risk->url())->assertOk()->getContent();
        $this->assertStringContainsString('<title>Rogue AIs: AI Risk Category (Hendrycks, 2023)', $html);
        $this->assertStringNotContainsString('Hendrycks2023', $this->headOf($html));

        $this->get('/ai-risk/3')->assertStatus(301)->assertRedirect(url('/ai-risk/misinformation'));
        $this->get('/ai-risk/misinformation')->assertOk();
        $this->get('/ai-risk/1/1.1')->assertStatus(301)->assertRedirect(RiskTaxonomy::subdomainUrl(1, '1.1'));
        $this->get('/ai-risk/misinformation/no-such-subdomain')->assertNotFound();
        $this->get('/ai-risk/9')->assertNotFound();
    }

    public function test_sexual_imagery_incidents_get_a_neutral_name_and_are_kept_out_of_search(): void
    {
        $incident = $this->incident([
            'title' => 'Deepfake Nudes of Pop Star Circulate on Social Media',
            'developers' => ['Stability AI'],
        ]);
        $this->assertTrue(IncidentSensitivity::isSensitive($incident));
        $this->assertSame('ai-incident-sexual-content-stability-ai-mar-2024', $incident->slug);
        $this->assertFalse($incident->isIndexable());

        $html = $this->get($incident->url())->assertOk()->getContent();
        $head = $this->headOf($html);
        $this->assertStringContainsString('<title>AI incident: sexual content (Stability AI, Mar 2024)', $head);
        $this->assertStringContainsString('noindex', $head);
        $this->assertStringNotContainsStringIgnoringCase('nude', $head);
        $this->assertStringNotContainsStringIgnoringCase('pop star', $head);
        $this->get('/sitemap-incidents.xml')->assertOk()->assertDontSee($incident->url());

        // A moderation error that mentions the word is not a sexual-imagery incident.
        $moderation = $this->incident(['incident_id' => 90002, 'title' => 'Moderation Tool Misidentified Rockets as Pornography']);
        $this->assertFalse(IncidentSensitivity::isSensitive($moderation));
        // Nor is a medical record whose description mentions a patient's history.
        $medical = $this->incident(['incident_id' => 90003, 'title' => 'Risk Score Model Allegedly Lacked Validation', 'description' => 'The model used sexual abuse history as an input.']);
        $this->assertFalse(IncidentSensitivity::isSensitive($medical));
    }

    public function test_the_crawl_finds_no_violation_in_any_sitemap_section(): void
    {
        $this->artisan('external:import')->assertExitCode(0);
        $this->artisan('seo:audit', ['--limit' => 25, '--fail-on-violation' => true])->assertExitCode(0);
    }

    public function test_the_ctr_audit_lists_page_one_rows_that_are_not_clicked(): void
    {
        $csv = tempnam(sys_get_temp_dir(), 'gsc').'.csv';
        file_put_contents($csv, "\xEF\xBB\xBFTop queries,Clicks,Impressions,CTR,Position\n"
            ."eu ai act,1,1200,0.08%,4.2\n"
            ."infocomm2023,0,150,0%,6.2\n"
            ."rare query,0,40,0%,3.0\n"
            ."ranked too low,0,900,0%,21.0\n"
            ."clicked enough,50,500,10%,2.0\n");
        $out = tempnam(sys_get_temp_dir(), 'ctr').'.csv';

        $this->artisan('seo:ctr-audit', ['csv' => [$csv], '--out' => $out])
            ->expectsOutputToContain('2 of 5 rows')
            ->assertExitCode(0);
        $rows = array_map('str_getcsv', array_filter(explode("\n", file_get_contents($out))));
        $queries = array_column(array_slice($rows, 1), 1);
        $this->assertSame(['eu ai act', 'infocomm2023'], $queries);
        $this->assertStringContainsString('identifier query', $rows[2][8]);
        // Matched to a page that targets the query squarely (the landing page or
        // the record), not to a comparison that merely mentions it.
        $this->assertContains($rows[1][0], [url('/eu-ai-act'), url('/policies/eu-ai-act')]);
    }

    public function test_the_api_gives_each_record_its_address_and_matches_its_published_contract(): void
    {
        $incident = $this->incident(['title' => 'Chatbot Reportedly Gave Harmful Advice to Users']);
        $risk = ExternalRisk::create(['ev_id' => '99.01.00', 'quick_ref' => 'Hendrycks2023', 'paper_title' => 'An Overview of Catastrophic AI Risks', 'level' => 'Risk Category', 'risk_category' => 'Rogue AIs', 'description' => 'AI systems pursuing goals against human interests.', 'domain' => '7', 'subdomain' => '7.1']);

        $incidents = $this->getJson('/api/v1/incidents')->assertOk()->json();
        $this->assertMatchesOpenApi('/incidents', $incidents);
        $row = collect($incidents['data'])->firstWhere('incident_id', $incident->incident_id);
        $this->assertSame($incident->slug, $row['slug']);
        $this->assertSame($incident->url(), $row['url']);
        $this->assertFalse(PageTitle::pathLeaksIdentifier(parse_url($row['url'], PHP_URL_PATH)));

        $risks = $this->getJson('/api/v1/risks')->assertOk()->json();
        $this->assertMatchesOpenApi('/risks', $risks);
        $row = collect($risks['data'])->firstWhere('ev_id', '99.01.00');
        $this->assertSame('rogue-ais', $row['slug']);
        $this->assertSame('Hendrycks, 2023', $row['citation']);
        $this->assertSame($risk->url(), $row['url']);
    }

    private function incident(array $attributes): ExternalIncident
    {
        return ExternalIncident::create($attributes + [
            'incident_id' => 90001,
            'occurred_on' => '2024-03-01',
            'year' => 2024,
            'description' => 'A record for the test.',
            'report_count' => 1,
            'snapshot_date' => '2026-09-01',
        ]);
    }

    private function headOf(string $html): string
    {
        return substr($html, 0, (int) strpos($html, '</head>'));
    }
}
