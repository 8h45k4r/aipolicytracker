<?php

namespace Tests\Feature;

use App\Models\Deadline;
use App\Models\DeadlineRevision;
use App\Models\PolicyInstrument;
use App\Services\Deadlines\DeadlineEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\OpenApiContract;
use Tests\TestCase;

/**
 * P7: the deadline engine. Five plain-form steps that work without JavaScript,
 * a timeline computed only from recorded deadlines with the reason each is
 * shown, "originally X, now Y" from import history, .ics and PDF exports, the
 * API and its contract.
 */
class DeadlineEngineTest extends TestCase
{
    use OpenApiContract;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('policy:import');
    }

    public function test_the_form_walks_five_steps_without_javascript_and_keeps_answers_in_the_url(): void
    {
        $html = $this->get('/deadlines/which-date-applies')->assertOk()->getContent();
        $this->assertSame(1, preg_match_all('#<h1\b#', $html));
        $this->assertStringContainsString('name="robots" content="index', $html, 'the empty form is the indexable page');
        $this->assertStringContainsString('aria-current="step">1.', $html);
        $this->assertStringContainsString('name="jurisdictions[]"', $html);
        $this->assertStringContainsString('"@type":"FAQPage"', $html);
        $this->assertStringNotContainsString('<script', substr($html, strpos($html, '<form'), 4000), 'the form itself needs no script');

        $step2 = $this->get('/deadlines/which-date-applies?jurisdictions[]=eu&step=2')->assertOk()->getContent();
        $this->assertStringContainsString('aria-current="step">2.', $step2);
        $this->assertStringContainsString('<input type="hidden" name="jurisdictions[]" value="eu">', $step2, 'earlier answers ride along as hidden fields');
        $this->assertStringContainsString('name="robots" content="noindex', $step2, 'answered states are personal, not indexed');

        $step3 = $this->get('/deadlines/which-date-applies?jurisdictions[]=eu&role=deployer&step=3')->assertOk()->getContent();
        $this->assertStringContainsString('name="system_types[]"', $step3);
        $this->assertStringContainsString('<input type="hidden" name="role" value="deployer">', $step3);
        $this->get('/deadlines/which-date-applies?step=3')->assertOk()->assertSee('aria-current="step">1.', false);
    }

    public function test_the_timeline_is_computed_only_from_recorded_deadlines_with_a_reason_for_each(): void
    {
        $engine = app(DeadlineEngine::class);
        $all = $engine->applicable($engine->normalise(['jurisdictions' => ['eu']]));
        $recorded = Deadline::whereHas('policyInstrument', fn ($p) => $p->published()->whereHas('jurisdiction', fn ($j) => $j->where('slug', 'eu')))->whereIn('deadline_status', ['scheduled', 'tbd', 'passed'])->count();
        $this->assertSame($recorded, $all->count(), 'with no narrowing answer every recorded EU date applies');
        foreach ($all as $row) {
            $this->assertNotEmpty($row['why']);
            $this->assertContains($row['level'], ['instrument', 'obligation']);
        }

        // A role the instrument does not name excludes it; a role it names is a reason.
        $eu = PolicyInstrument::where('slug', 'eu-ai-act')->firstOrFail();
        $euActors = $eu->termsOf('actor')->pluck('slug');
        $this->assertTrue($euActors->contains('deployer'), 'fixture: the Act names deployers');
        $deployer = $engine->applicable($engine->normalise(['jurisdictions' => ['eu'], 'role' => 'deployer']));
        $this->assertTrue($deployer->contains(fn ($r) => $r['deadline']->policy_instrument_id === $eu->id && in_array('names your role', $r['why'], true)));
        $none = $engine->applicable($engine->normalise(['jurisdictions' => ['eu'], 'role' => 'not_a_role']));
        $this->assertFalse($none->contains(fn ($r) => $r['deadline']->policy_instrument_id === $eu->id), 'an instrument that names actors, none of them the answer, drops out');

        $html = $this->get('/deadlines/which-date-applies?jurisdictions[]=eu&role=deployer&step=5')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/\d+ recorded dates? appl(y|ies) for European Union in the role of deployer/', $html);
        $this->assertStringContainsString('Shown because it', $html);
        $this->assertStringContainsString('Add to calendar (.ics)', $html);
        $this->assertStringContainsString('Download PDF', $html);
        $this->assertGreaterThanOrEqual(5, preg_match_all('#href="'.preg_quote(url('/'), '#').'#', $html));
    }

    public function test_a_date_that_moved_shows_originally_and_now_from_import_history(): void
    {
        $d = Deadline::whereNotNull('due_on')->where('deadline_status', 'scheduled')->whereHas('policyInstrument', fn ($p) => $p->published()->whereHas('jurisdiction', fn ($j) => $j->where('slug', 'eu')))->firstOrFail();
        $slug = $d->policyInstrument->jurisdiction->slug;
        $recordedDate = $d->due_on->toDateString();
        $earlier = $d->due_on->copy()->subMonths(4);
        // Pretend the stored date was earlier; a re-import finds the record says otherwise and writes the revision.
        $d->update(['due_on' => $earlier]);
        $this->artisan('policy:import');
        $rev = DeadlineRevision::where('policy_instrument_id', $d->policy_instrument_id)->where('deadline_key', DeadlineRevision::keyFor($d->title))->first();
        $this->assertNotNull($rev, 'a changed date is recorded as a revision');
        $this->assertSame($recordedDate, $rev->to_due_on->toDateString());
        $this->assertSame($earlier->toDateString(), $rev->from_due_on->toDateString());

        $html = $this->get('/deadlines/which-date-applies?jurisdictions[]='.$slug.'&step=5')->assertOk()->getContent();
        $this->assertStringContainsString('originally '.$rev->from_due_on->format('j F Y').' → now', $html);
        // An unchanged re-import writes nothing more.
        $this->artisan('policy:import');
        $this->assertSame(1, DeadlineRevision::count());
    }

    public function test_ics_and_pdf_exports_carry_the_same_rows(): void
    {
        $ics = $this->get('/deadlines/which-date-applies.ics?jurisdictions[]=eu')->assertOk()->assertHeader('Content-Type', 'text/calendar; charset=UTF-8')->getContent();
        $this->assertStringContainsString('BEGIN:VCALENDAR', $ics);
        $this->assertStringContainsString('X-WR-CALNAME:AI regulation dates that apply to you', $ics);
        $expected = app(DeadlineEngine::class)->applicable(app(DeadlineEngine::class)->normalise(['jurisdictions' => ['eu']]))->filter(fn ($r) => $r['deadline']->due_on && $r['deadline']->date_precision === 'exact' && $r['deadline']->deadline_status === 'scheduled')->count();
        $this->assertSame($expected, substr_count($ics, 'BEGIN:VEVENT'));
        $this->get('/deadlines/which-date-applies.ics')->assertNotFound();

        $pdf = $this->get('/deadlines/which-date-applies.pdf?jurisdictions[]=eu&role=deployer')->assertOk()->assertHeader('Content-Type', 'application/pdf')->getContent();
        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertGreaterThan(3000, strlen($pdf));
    }

    public function test_the_api_answers_the_same_question_and_matches_its_contract(): void
    {
        $body = ['jurisdictions' => ['eu'], 'role' => 'deployer'];
        $json = $this->postJson('/api/v1/deadlines/applicable', $body)->assertOk()->json();
        $this->assertMatchesOpenApi('/deadlines/applicable', $json, 'post');
        $expected = app(DeadlineEngine::class)->applicable(app(DeadlineEngine::class)->normalise($body))->count();
        $this->assertCount($expected, $json['data']);
        $this->assertSame('eu', $json['data'][0]['jurisdiction']);
        $this->assertNotEmpty($json['data'][0]['why']);
        $this->assertStringContainsString('/deadlines/which-date-applies.ics', $json['meta']['ics_url']);
        $this->assertStringContainsString('BEGIN:VCALENDAR', $this->get('/api/v1/deadlines/applicable.ics?jurisdictions[]=eu')->assertOk()->getContent());
        $this->postJson('/api/v1/deadlines/applicable', [])->assertStatus(422);
    }
}
