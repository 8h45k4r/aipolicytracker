<?php

namespace Tests\Feature;

use App\Enums\AdminRole;
use App\Models\ChangeEvent;
use App\Models\Control;
use App\Models\Obligation;
use App\Models\PolicyInstrument;
use App\Models\RecordRevision;
use App\Models\SourceCheck;
use App\Models\User;
use App\Services\Alerts\WebhookDispatcher;
use App\Services\ChangeDesk\Freshness;
use App\Services\PolicyData\PolicyDataRepository;
use App\Services\PolicyData\PolicyDataValidator;
use App\Services\PolicyData\RecordStanding;
use App\Services\PolicyData\SchemaValidator;
use App\Support\Reliance;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * The record as something to rely on: a permanent id and citation, a visible history,
 * a one-line standing, a change desk with a published freshness target, provenance in
 * every feed, and reliance measured by accounts acting on alerts.
 */
class RecordProvenanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('policy:import');
    }

    public function test_every_record_has_a_unique_permanent_id_that_resolves(): void
    {
        foreach ([PolicyInstrument::class => 'POL', Obligation::class => 'OBL', ChangeEvent::class => 'CHG', Control::class => 'CTL'] as $model => $prefix) {
            $this->assertSame(0, $model::whereNull('aipt_id')->count(), "{$prefix} records without an id");
            $this->assertSame($model::count(), $model::distinct()->count('aipt_id'));
            $this->assertSame(0, $model::where('aipt_id', 'not like', "AIPT-{$prefix}-%")->count());
        }

        $eu = PolicyInstrument::where('slug', 'eu-ai-act')->firstOrFail();
        $this->get('/id/'.$eu->aipt_id)->assertRedirect($eu->url());
        $this->getJson('/id/'.$eu->aipt_id)->assertOk()->assertJson(['id' => $eu->aipt_id, 'kind' => 'policy', 'slug' => 'eu-ai-act', 'url' => $eu->url()]);
        $this->get('/id/AIPT-POL-9999')->assertNotFound();
        $this->get('/id/not-an-id')->assertNotFound();

        $this->getJson('/api/v1/policies/eu-ai-act')->assertOk()->assertJsonPath('data.id', $eu->aipt_id)->assertJsonPath('data.permanent_url', route('record.id', $eu->aipt_id));
        $duty = Obligation::where('slug', 'eu-ai-act-human-oversight')->firstOrFail();
        $this->getJson('/api/v1/obligations/'.$duty->slug)->assertOk()->assertJsonPath('data.id', $duty->aipt_id);
    }

    public function test_an_id_survives_a_slug_rename_and_is_never_reused(): void
    {
        $eu = PolicyInstrument::where('slug', 'eu-ai-act')->firstOrFail();
        $id = $eu->aipt_id;
        // A row left behind under an old slug gives the id up to the renamed record.
        PolicyInstrument::whereKey($eu->id)->update(['slug' => 'eu-ai-act-old-slug']);
        $this->artisan('policy:import');
        $this->assertSame('eu-ai-act', PolicyInstrument::where('aipt_id', $id)->value('slug'));
        $this->assertNull(PolicyInstrument::where('slug', 'eu-ai-act-old-slug')->value('aipt_id'));

        // The validator refuses a reused id or a wrong prefix.
        $dir = sys_get_temp_dir().'/aip-ids-'.uniqid();
        File::copyDirectory(base_path('data'), $dir);
        try {
            $file = $dir.'/policies/eu/eu-ai-act.yaml';
            File::put($file, preg_replace('/^aipt_id: AIPT-POL-\d+/m', 'aipt_id: AIPT-OBL-0004', File::get($file), 1));
            $errors = implode("\n", array_merge(...array_values((new PolicyDataValidator(new PolicyDataRepository($dir), new SchemaValidator($dir.'/schema')))->run())));
            $this->assertStringContainsString('must start with AIPT-POL-', $errors);
            $this->assertStringContainsString('is already used', $errors);
        } finally {
            File::deleteDirectory($dir);
        }
    }

    public function test_the_citation_names_the_id_version_and_reviewer(): void
    {
        $policy = PolicyInstrument::where('review_status', 'verified')->whereNotNull('reviewed_by')->firstOrFail();
        $html = $this->get($policy->url())->assertOk()->getContent();
        $this->assertStringContainsString('['.$policy->aipt_id.', record version '.$policy->content_version.', verified '.$policy->last_verified_at->format('j F Y').' by '.$policy->reviewed_by.']', $html);
        $this->assertStringContainsString(route('record.id', $policy->aipt_id), $html);
        $this->assertStringContainsString('data-aipt-id', $html);

        $this->get(route('policies.context', $policy->slug))->assertOk()->assertSee('**Permanent id**: '.$policy->aipt_id, false)->assertSee('**Cite as**', false);
    }

    public function test_an_import_that_changes_a_tracked_field_writes_a_revision_shown_on_the_page(): void
    {
        $this->assertSame(0, RecordRevision::count(), 'an unchanged import writes nothing');
        $policy = PolicyInstrument::where('slug', 'eu-ai-act')->firstOrFail();
        PolicyInstrument::whereKey($policy->id)->update(['short_title' => 'Old short title', 'summary_plain' => 'Old summary text.']);

        $this->artisan('policy:import');
        $revision = RecordRevision::sole();
        $this->assertSame(['policy', 'eu-ai-act', $policy->aipt_id], [$revision->record_type, $revision->record_slug, $revision->aipt_id]);
        $fields = collect($revision->fields)->keyBy('field');
        $this->assertSame(['Old short title', 'EU AI Act'], [$fields['short_title']['from'], $fields['short_title']['to']]);
        $this->assertSame([null, 'revised'], [$fields['summary_plain']['from'], $fields['summary_plain']['to']], 'prose is marked revised, not quoted');

        $this->get($policy->url())->assertOk()->assertSee('data-record-history', false)->assertSee('Old short title → EU AI Act')
            ->assertSee('Record updated on', false);
    }

    public function test_standing_takes_the_strongest_signal(): void
    {
        $policy = PolicyInstrument::where('slug', 'eu-ai-act')->firstOrFail();
        PolicyInstrument::whereKey($policy->id)->update(['status' => 'superseded']);
        $this->assertSame('bad', RecordStanding::forPolicy($policy->fresh())['tone']);

        PolicyInstrument::whereKey($policy->id)->update(['status' => 'in_force']);
        $quiet = PolicyInstrument::where('review_status', 'verified')->whereDoesntHave('changeEvents')->firstOrFail();
        SourceCheck::create(['record_slug' => $quiet->slug, 'outcome' => 'no_change', 'checked_at' => now()->subDay()]);
        $standing = RecordStanding::forPolicy($quiet);
        $this->assertSame('good', $standing['tone']);
        $this->assertTrue($standing['checked_at']->isYesterday());
        $this->get($quiet->url())->assertOk()->assertSee('data-standing="good"', false)->assertSee('official source last checked');
    }

    public function test_the_change_desk_logs_checks_and_the_freshness_page_measures_lag(): void
    {
        $reviewer = User::factory()->create();
        $reviewer->forceFill(['admin_role' => AdminRole::Reviewer])->save();
        $this->actingAs($reviewer)->get('/backend/admin/change-desk')->assertOk()->assertSee('Change desk')->assertSee('Sources due a look')->assertSee('Deepen next');

        $this->actingAs($reviewer)->post('/backend/admin/change-desk/checks', ['policy' => 'eu-ai-act', 'outcome' => 'no_change', 'note' => 'OJ checked'])->assertSessionHas('success');
        $this->assertSame('no_change', SourceCheck::where('record_slug', 'eu-ai-act')->value('outcome'));
        $this->actingAs($reviewer)->post('/backend/admin/change-desk/checks', ['policy' => 'eu-ai-act', 'outcome' => 'made-up'])->assertSessionHasErrors('outcome');
        $this->actingAs(User::factory()->create())->get('/backend/admin/change-desk')->assertRedirect();

        config(['changedesk.measured_since' => now()->subDays(10)->toDateString()]);
        ChangeEvent::query()->update(['first_published_at' => now()->subDays(60)]); // the import, before measurement began
        $change = ChangeEvent::published()->firstOrFail();
        ChangeEvent::whereKey($change->id)->update(['source_document_date' => now()->subDays(5)->toDateString(), 'first_published_at' => now()->subDay()]);
        $rows = Freshness::measured();
        $this->assertSame(4, $rows->firstWhere('change.id', $change->id)['lag']);
        $this->assertSame(['count' => 1, 'median' => 4.0, 'within_target' => 0, 'p90' => 4], Freshness::summary($rows->where('change.id', $change->id)));
        $this->get('/freshness')->assertOk()->assertSee('How fast changes reach the record')->assertSee($change->title);
    }

    public function test_alert_payloads_carry_ids_and_provenance_and_alert_clicks_measure_reliance(): void
    {
        $change = ChangeEvent::published()->with(['jurisdiction', 'policyInstrument'])->whereNotNull('policy_instrument_id')->firstOrFail();
        $payload = WebhookDispatcher::payload(['changes' => collect([$change]), 'deadlines' => collect()], 'Daily alert', 'https://example.test/manage');
        $row = $payload['changes'][0];
        $this->assertSame($change->aipt_id, $row['id']);
        $this->assertSame($change->policyInstrument->aipt_id, $row['policy_id']);
        $this->assertArrayHasKey('reviewed_by', $row);
        $this->assertArrayHasKey('first_published_at', $row);

        $this->assertSame('https://x.test/a?src=alert', Reliance::tag('https://x.test/a'));
        $user = User::factory()->create();
        $this->actingAs($user)->get($change->url().'?src=alert')->assertOk();
        $this->actingAs($user)->get($change->url().'?src=alert')->assertOk();
        $this->assertSame(2, (int) \DB::table('account_weeks')->where('user_id', $user->id)->value('alert_clicks'));
        $this->assertSame(1, Reliance::activeThisWeek());
        $this->assertSame(0, Reliance::reliedOnEveryWeek(), 'one week is not four');

        foreach ([1, 2, 3] as $weeksAgo) {
            \DB::table('account_weeks')->insert(['user_id' => $user->id, 'week_start' => now()->startOfWeek(CarbonInterface::MONDAY)->subWeeks($weeksAgo)->toDateString(), 'alert_clicks' => 1, 'created_at' => now(), 'updated_at' => now()]);
        }
        $this->assertSame(1, Reliance::reliedOnEveryWeek());

        // Anonymous opens are not stored.
        auth()->logout();
        $this->get($change->url().'?src=alert')->assertOk();
        $this->assertSame(4, \DB::table('account_weeks')->count());
    }

    public function test_the_independence_page_states_the_relationship_and_what_is_not_yet_independent(): void
    {
        $this->get('/independence')->assertOk()->assertSee('Certifyi')->assertSee('What is not yet independent')->assertSee('AIPT-POL-0073');
        $this->artisan('policy:next-id', ['kind' => 'obl'])->expectsOutput(sprintf('AIPT-OBL-%04d', Obligation::count() + 1))->assertSuccessful();
    }
}
