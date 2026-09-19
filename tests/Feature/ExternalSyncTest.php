<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\ExternalIncident;
use App\Models\ExternalIncidentReport;
use App\Services\ExternalData\AiidApiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Live sync from the AI Incident Database API: upsert, survival across re-import, pages and cron trigger. */
class ExternalSyncTest extends TestCase
{
    use RefreshDatabase;

    private function fakeApi(int $newId, int $existingId): void
    {
        $incident = fn (int $id, string $title, string $notes) => [
            'incident_id' => $id, 'date' => '2026-09-10', 'date_modified' => '2026-09-10T20:01:20.599Z', 'title' => $title, 'description' => 'Description synced from the API for incident '.$id.'.', 'editor_notes' => $notes,
            'AllegedDeployerOfAISystem' => [['entity_id' => 'acme-robotics', 'name' => 'Acme Robotics']], 'AllegedDeveloperOfAISystem' => [['entity_id' => 'acme-labs', 'name' => 'Acme Labs']],
            'AllegedHarmedOrNearlyHarmedParties' => [['entity_id' => 'warehouse-workers', 'name' => 'Warehouse workers']], 'implicated_systems' => [['entity_id' => 'acme-picker-9', 'name' => 'Acme Picker 9']],
            'editor_similar_incidents' => [$existingId === $id ? $newId : $existingId], 'nlp_similar_incidents' => [['incident_id' => 1, 'similarity' => 0.91]],
            'reports' => [['report_number' => 900000 + $id, 'title' => 'Robot arm injures worker', 'url' => 'https://example.org/news/'.$id, 'source_domain' => 'example.org', 'date_published' => '2026-09-10T00:00:00.000Z', 'authors' => ['Reporter'], 'language' => 'en', 'is_incident_report' => true]],
        ];
        Http::fake([AiidApiClient::ENDPOINT => Http::sequence()
            ->push(['data' => ['incidents' => [$incident($newId, 'Synced Warehouse Robot Incident', 'Editor note: dates are approximate.'), $incident($existingId, 'Existing Incident Updated Title', '')]]])
            ->push(['data' => ['classifications' => [
                ['namespace' => 'MIT', 'incidents' => [['incident_id' => $newId]], 'attributes' => [['short_name' => 'Risk Domain', 'value_json' => '"7. AI system safety, failures, and limitations"'], ['short_name' => 'Risk Subdomain', 'value_json' => '"7.3. Lack of capability or robustness"'], ['short_name' => 'Entity', 'value_json' => '"AI"'], ['short_name' => 'Intent', 'value_json' => '"Unintentional"'], ['short_name' => 'Timing', 'value_json' => '"Post-deployment"']]],
                ['namespace' => 'CSETv1', 'incidents' => [['incident_id' => $newId]], 'attributes' => [['short_name' => 'AI Harm Level', 'value_json' => '"AI tangible harm event"'], ['short_name' => 'Sector of Deployment', 'value_json' => '["transportation and storage"]'], ['short_name' => 'Location Country (two letters)', 'value_json' => '"DE"']]],
            ]]]),
        ]);
    }

    public function test_api_sync_upserts_incidents_with_details_and_survives_reimport(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        $this->artisan('policy:import');
        $this->artisan('external:import')->assertExitCode(0);
        $existing = ExternalIncident::orderByDesc('incident_id')->first();
        $newId = 990001;
        $this->fakeApi($newId, $existing->incident_id);

        $this->artisan('external:sync-aiid-api', ['--since' => '2026-09-01'])->assertExitCode(0);

        $i = ExternalIncident::with('reports')->findOrFail($newId);
        $this->assertSame('AI system safety, failures, and limitations', $i->mit_domain);
        $this->assertSame('Lack of capability or robustness', $i->mit_subdomain);
        $this->assertSame('AI tangible harm event', $i->harm_level);
        $this->assertSame(['DE'], $i->countries);
        $this->assertSame(['Acme Robotics'], $i->deployers);
        $this->assertSame('acme-robotics', $i->entities['deployers'][0]['id']);
        $this->assertSame('Acme Picker 9', $i->implicated_systems[0]['name']);
        $this->assertSame([$existing->incident_id, 1], $i->similarIds());
        $this->assertNotNull($i->synced_at);
        $this->assertCount(1, $i->reports);
        $this->assertSame('example.org', $i->reports->first()->source_domain);

        $updated = ExternalIncident::findOrFail($existing->incident_id);
        $this->assertSame('Existing Incident Updated Title', $updated->title);
        $this->assertSame($existing->mit_domain, $updated->mit_domain, 'snapshot classification kept when the API has none');

        $this->get('/ai-risk/incidents')->assertOk()->assertSee('Synced Warehouse Robot Incident')->assertSee('Synced from the AI Incident Database API')->assertSee(route('risk.incidents.show', $newId))->assertSee('https://incidentdatabase.ai/cite/');
        $this->get('/ai-risk/incidents/'.$newId)->assertOk()->assertSee("Editor's notes", false)->assertSee('dates are approximate')->assertSee('AI systems implicated')->assertSee('Related incidents')->assertSee('Synced from source');
        // The source is credited once, at the foot of the page, instead of on every
        // entity and every catalogued report. These two assertions are the contract:
        // the per-entity link-out is gone, and the licence credit is not.
        $this->get('/ai-risk/incidents/'.$newId)->assertOk()->assertDontSee('entities/acme-robotics')->assertSee('CC BY-SA 4.0');
        $this->get('/')->assertOk()->assertSee('Synced Warehouse Robot Incident');

        // Re-importing the weekly snapshot must not delete or downgrade rows the live sync added or refreshed.
        $this->artisan('external:import')->assertExitCode(0);
        $this->assertSame('Existing Incident Updated Title', ExternalIncident::findOrFail($existing->incident_id)->title);
        $this->assertTrue(ExternalIncident::whereKey($newId)->exists());
        $this->assertTrue(ExternalIncidentReport::whereKey(900000 + $newId)->exists());

        // The local snapshot on the private disk restores live-synced rows into a rebuilt database without the API.
        \Illuminate\Support\Facades\Storage::disk('local')->assertExists(\App\Console\Commands\SyncAiidApiCommand::LOCAL_INCIDENTS);
        ExternalIncidentReport::query()->delete();
        ExternalIncident::query()->delete();
        $this->artisan('external:import')->assertExitCode(0);
        $restored = ExternalIncident::with('reports')->findOrFail($newId);
        $this->assertSame('acme-robotics', $restored->entities['deployers'][0]['id']);
        $this->assertSame('Existing Incident Updated Title', ExternalIncident::findOrFail($existing->incident_id)->title);
        $this->assertCount(1, $restored->reports);
        $this->get('/ai-risk/incidents/'.$newId)->assertOk()->assertSee('AI systems implicated');
    }

    public function test_api_failure_is_reported_and_cron_trigger_requires_token(): void
    {
        $this->artisan('external:import')->assertExitCode(0);
        // Two 403s cover the client's retry; afterwards the API answers with an empty page.
        Http::fake([AiidApiClient::ENDPOINT => Http::sequence()->push(['error' => 'Forbidden - Invalid client'], 403)->push(['error' => 'Forbidden - Invalid client'], 403)->whenEmpty(Http::response(['data' => ['incidents' => []]]))]);
        $this->artisan('external:sync-aiid-api', ['--since' => '2026-09-01'])->assertExitCode(1);
        // API down: the stored rows keep serving the pages.
        $this->assertGreaterThan(1000, ExternalIncident::count());
        $this->get('/ai-risk/incidents')->assertOk()->assertSee('Latest recorded incidents')->assertSee(route('risk.incidents.show', ExternalIncident::max('incident_id')));
        $this->get('/ai-risk/incidents/browse')->assertOk();

        $this->postJson('/cron/external-sync')->assertStatus(401);
        AppSetting::put('cron_token', str_repeat('s', 32));
        $this->postJson('/cron/external-sync', [], ['Authorization' => 'Bearer '.str_repeat('s', 32)])->assertOk();
        $run = \Illuminate\Support\Facades\Cache::get(\App\Console\Commands\SyncAiidApiCommand::LAST_RUN_KEY);
        $this->assertSame(0, $run['incidents']);
        $this->assertNull($run['error']);
    }

    public function test_admin_external_page_shows_live_sync_status(): void
    {
        config(['aipolicytracker.admin_emails' => ['editor@example.test']]);
        $admin = \App\Models\User::factory()->create(['email' => 'editor@example.test']);
        $this->artisan('external:import');
        $this->actingAs($admin)->get('/backend/admin/external')->assertOk()->assertSee('Live API sync')->assertSee('Sync now from the AIID API');
        Http::fake([AiidApiClient::ENDPOINT => Http::response(['data' => ['incidents' => []]])]);
        $this->actingAs($admin)->post('/backend/admin/external/sync')->assertRedirect()->assertSessionHas('success');
    }

    /**
     * The incident page is the site's own page about a record it did not author.
     * Two things therefore have to be true at once, and this asserts both:
     * it has to say something of its own, and it has to keep the credit.
     *
     * The credit is the part worth a test. The AIID data is CC BY-SA 4.0, where
     * attribution is a condition of the licence, not a courtesy -- remove it and
     * the licence to redistribute 1,663 incidents terminates. A future tidy-up
     * that deletes the attribution block should fail here rather than ship.
     */
    public function test_an_incident_page_carries_its_own_summary_and_keeps_the_source_credit(): void
    {
        $incident = ExternalIncident::create([
            'incident_id' => 90001,
            'title' => 'Screening model down-ranked applicants by postcode',
            'description' => 'A recruitment screening model rejected applicants from particular postcodes at a markedly higher rate.',
            'occurred_on' => '2026-02-11', 'year' => 2026, 'report_count' => 2,
            'snapshot_date' => '2026-09-07', 'mit_domain' => '1', 'mit_subdomain' => '1.1',
            'developers' => ['Northwind Analytics'],
            'deployers' => ['Acme Retail'],
            'harmed' => ['Job applicants, Residents of affected postcodes'],
        ]);

        $html = $this->get($incident->url())->assertOk()->getContent();

        // Its own summary, built from the record rather than copied from the source.
        $this->assertStringContainsString('An AI system built by Northwind Analytics and deployed by Acme Retail', $html);
        $this->assertStringContainsString('allegedly harmed', $html);

        // The credit, and the licence it is a condition of.
        $this->assertStringContainsString('CC BY-SA 4.0', $html);
        $this->assertStringContainsString('incidentdatabase.ai', $html);

        // The line that advertised the page as a thin copy is gone.
        $this->assertStringNotContainsString('Only the incident metadata is stored here', $html);
    }

    /**
     * Outbound links to the source must not scale with report count. They used to:
     * every catalogued report carried its own link out, so a heavily reported
     * incident sent a reader away more than a dozen times from one page.
     */
    public function test_source_links_do_not_multiply_with_the_number_of_reports(): void
    {
        foreach ([[90002, 1], [90003, 12]] as [$id, $reports]) {
            $incident = ExternalIncident::create([
                'incident_id' => $id, 'title' => 'Incident '.$id,
                'description' => 'A description long enough to make this page indexable on its own terms.',
                'occurred_on' => '2026-02-11', 'year' => 2026, 'report_count' => $reports,
                'snapshot_date' => '2026-09-07', 'developers' => ['D'], 'deployers' => ['P'], 'harmed' => ['H'],
            ]);
            for ($n = 1; $n <= $reports; $n++) {
                \App\Models\ExternalIncidentReport::create([
                    'incident_id' => $id, 'report_number' => ($id * 100) + $n, 'title' => 'Report '.$n,
                    'url' => 'https://news.example.com/'.$n, 'source_domain' => 'news.example.com',
                    'date_published' => '2026-03-0'.min($n, 9),
                ]);
            }
            $counts[$id] = substr_count($this->get($incident->url())->assertOk()->getContent(), 'https://incidentdatabase.ai');
        }

        $this->assertSame($counts[90002], $counts[90003], 'outbound source links scale with report count');
    }
}
