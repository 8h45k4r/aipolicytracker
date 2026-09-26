<?php

namespace Tests\Feature;

use App\Http\Controllers\Site\ContributeController;
use App\Models\ContributorSubmission;
use App\Models\PolicyInstrument;
use App\Models\ReviewerDecision;
use App\Services\Completeness\CompletenessReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompletenessPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->artisan('policy:import');
    }

    public function test_a_missing_required_field_becomes_a_gap_and_filling_it_clears_it(): void
    {
        $completeness = app(CompletenessReport::class);
        $instrument = PolicyInstrument::published()->firstOrFail();
        $before = $completeness->report()['required_gaps'];

        $instrument->forceFill(['summary_plain' => null])->save();
        $report = app(CompletenessReport::class)->report();
        $this->assertSame($before + 1, $report['required_gaps'], 'clearing a required field adds exactly one gap');
        $this->assertContains(
            $instrument->slug,
            app(CompletenessReport::class)->queue(null, 'policy-summary')->pluck('record.slug')->all(),
            'the record appears in the queue for the check it failed'
        );

        $instrument->forceFill(['summary_plain' => 'A plain-language summary.'])->save();
        $this->assertSame($before, app(CompletenessReport::class)->report()['required_gaps']);
    }

    public function test_a_check_is_not_counted_against_records_it_does_not_apply_to(): void
    {
        $report = app(CompletenessReport::class)->report();
        $checks = collect($report['checks'])->keyBy('id');

        // Duties are expected only of binding instruments that are actually in force, so the
        // denominator must be smaller than the published instrument count, not equal to it.
        $duties = $checks['policy-obligations'];
        $sourceLink = $checks['policy-source-url'];
        $this->assertLessThan(
            $sourceLink['applicable'],
            $duties['applicable'],
            'a narrowed check reports a smaller denominator than an unconditional one'
        );
        $this->assertLessThanOrEqual($duties['applicable'], $duties['missing']);

        foreach ($report['checks'] as $check) {
            $this->assertLessThanOrEqual($check['applicable'], $check['missing'], $check['id'].' cannot miss more records than it applies to');
        }
    }

    public function test_every_check_points_at_a_field_the_correction_form_accepts(): void
    {
        // The "Fill this in" link carries the field to the contribute form, which silently
        // ignores a field it does not know. Without this the reader would land on a form
        // with nothing selected and no explanation.
        foreach (app(CompletenessReport::class)->checks() as $check) {
            $correctable = ContributeController::CORRECTABLE_FIELDS[$check['kind']] ?? [];
            $this->assertContains(
                $check['field'],
                $correctable,
                "check {$check['id']} points at '{$check['field']}', which is not correctable for a {$check['kind']} record"
            );
        }
    }

    public function test_the_check_fails_when_required_gaps_exceed_the_budget(): void
    {
        // Measured from the shipped data, so the test holds whatever gaps the corpus carries today.
        $baseline = app(CompletenessReport::class)->report()['required_gaps'];
        config(['completeness.required_budget' => $baseline]);
        PolicyInstrument::published()->firstOrFail()->forceFill(['official_source_url' => null])->save();
        $this->artisan('policy:coverage')->assertExitCode(1);

        config(['completeness.required_budget' => $baseline + 1]);
        $this->artisan('policy:coverage')->assertExitCode(0);
    }

    public function test_the_json_summary_is_machine_readable_and_carries_the_verdict(): void
    {
        config(['completeness.required_budget' => 0]);
        PolicyInstrument::published()->firstOrFail()->forceFill(['official_source_url' => null])->save();
        $report = app(CompletenessReport::class)->report();

        $expected = <<<JSON
        {
            "records": {$report['records']},
            "complete": {$report['complete']},
            "incomplete": {$report['incomplete']},
            "required_gaps": {$report['required_gaps']},
            "expected_gaps": {$report['expected_gaps']},
            "budget": 0,
            "pass": false,
        JSON;
        $this->artisan('policy:coverage', ['--json' => true])->expectsOutputToContain($expected);
    }

    public function test_the_coverage_page_publishes_the_same_numbers_as_the_check(): void
    {
        $report = app(CompletenessReport::class)->report();
        $response = $this->get('/coverage');
        $response->assertOk();
        $html = $response->getContent();

        $this->assertStringContainsString(number_format($report['records']), $html);
        $this->assertStringContainsString(number_format($report['complete']), $html);
        // The page must not imply it measures coverage of the world.
        $this->assertStringContainsString('completeness, not coverage of the world', $html);
        $this->assertStringContainsString('Open queue', $html);
    }

    public function test_the_queue_lists_required_gaps_before_expected_ones_and_links_to_the_form(): void
    {
        PolicyInstrument::published()->firstOrFail()->forceFill(['official_source_url' => null, 'who_it_applies_to' => null])->save();
        $queue = app(CompletenessReport::class)->queue();

        $severities = $queue->pluck('check.severity')->unique()->values()->all();
        $this->assertSame('required', $severities[0], 'required gaps come first');

        $response = $this->get('/gaps');
        $response->assertOk();
        $response->assertSee('subject_type=policy', false);
        $response->assertSee('field=official_source_url', false);
    }

    public function test_a_filtered_queue_shows_only_that_check_and_is_not_indexed(): void
    {
        $response = $this->get('/gaps?check=policy-summary&kind=jurisdiction');
        $response->assertOk();
        // The check wins over a contradicting kind, so the page cannot show a filter it did not apply.
        $response->assertSee('A title alone does not say what the instrument does.');
        $response->assertSee('noindex', false);

        $this->get('/gaps?check=not-a-check')->assertOk();
    }

    public function test_the_corrections_log_publishes_decided_reports_and_hides_pending_ones(): void
    {
        $instrument = PolicyInstrument::published()->firstOrFail();

        $decided = ContributorSubmission::create([
            'type' => 'correction',
            'subject_type' => 'policy',
            'subject_slug' => $instrument->slug,
            'summary' => 'The application date is wrong in this record.',
            'submitter_name' => 'Ada Reporter',
            'submitter_email' => 'ada@example.com',
            'status' => 'approved',
            'payload' => ['field' => 'applies_from'],
        ]);
        ReviewerDecision::create([
            'contributor_submission_id' => $decided->id,
            'decision' => 'approved',
            'notes' => 'Internal: checked against the official gazette, reporter was right.',
            'public_note' => 'Confirmed against the official text and corrected.',
            'decided_at' => now(),
        ]);

        ContributorSubmission::create([
            'type' => 'correction',
            'subject_type' => 'policy',
            'subject_slug' => $instrument->slug,
            'summary' => 'An unchecked claim that must not be published.',
            'status' => 'pending_review',
        ]);

        $response = $this->get('/corrections');
        $response->assertOk();

        $response->assertSee($instrument->short_title ?: $instrument->title);
        $response->assertSee('Applies from');
        $response->assertSee('Confirmed against the official text and corrected.');
        $response->assertSee('Approved');

        // Never published: the submitter, their words, or the reviewer's internal note.
        $response->assertDontSee('Ada Reporter');
        $response->assertDontSee('ada@example.com');
        $response->assertDontSee('The application date is wrong in this record.');
        $response->assertDontSee('Internal: checked against the official gazette');
        $response->assertDontSee('An unchecked claim that must not be published.');
    }

    public function test_a_declined_report_is_published_too(): void
    {
        $instrument = PolicyInstrument::published()->firstOrFail();
        $submission = ContributorSubmission::create([
            'type' => 'correction',
            'subject_type' => 'policy',
            'subject_slug' => $instrument->slug,
            'summary' => 'A claim the source did not support.',
            'status' => 'rejected',
        ]);
        ReviewerDecision::create([
            'contributor_submission_id' => $submission->id,
            'decision' => 'rejected',
            'public_note' => 'The official text does not say this.',
            'decided_at' => now(),
        ]);

        $response = $this->get('/corrections');
        $response->assertOk();
        $response->assertSee('Rejected');
        $response->assertSee('The official text does not say this.');
        $response->assertSee('including the ones that were turned down', false);
    }

    public function test_the_log_and_the_pages_survive_an_empty_corpus(): void
    {
        $this->get('/corrections')->assertOk()->assertSee('No decided reports yet');

        foreach (['/coverage', '/gaps'] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_the_new_pages_are_in_the_sitemap(): void
    {
        $sitemap = $this->get('/sitemap-static.xml');
        $sitemap->assertOk();
        foreach (['/coverage', '/gaps', '/corrections'] as $path) {
            $sitemap->assertSee(url($path), false);
        }
    }
}
