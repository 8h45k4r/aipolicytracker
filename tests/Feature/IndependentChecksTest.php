<?php

namespace Tests\Feature;

use App\Console\Commands\VerificationSampleCommand;
use App\Models\PolicyInstrument;
use App\Services\PolicyData\PolicyDataRepository;
use App\Services\PolicyData\PolicyDataValidator;
use App\Services\PolicyData\SchemaValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

/**
 * The independent second check: who may record one, how agreement is published, and
 * the reproducible sample reviewers re-check each quarter.
 */
class IndependentChecksTest extends TestCase
{
    use RefreshDatabase;

    private string $dataDir;

    private string $policyFile;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->artisan('policy:import');

        // A throwaway copy of data/ so a test can add a reviewer and a second review
        // without touching the repository's own files.
        $this->dataDir = storage_path('framework/testing/data-'.uniqid());
        File::copyDirectory(base_path('data'), $this->dataDir);
        File::put($this->dataDir.'/reviewers/jane-doe.yaml', Yaml::dump([
            'slug' => 'jane-doe', 'name' => 'Jane Doe', 'role' => 'reviewer', 'published' => true, 'joined_on' => '2026-09-17',
            'interests' => [['declaration' => 'None declared.', 'declared_on' => '2026-09-17']],
        ], 6, 2));
        $this->policyFile = collect(File::allFiles($this->dataDir.'/policies'))
            ->map(fn ($f) => $f->getPathname())->sort()
            ->first(fn ($path) => (Yaml::parseFile($path)['reviewed_by'] ?? null) === 'Bhaskar Bhatt' && (Yaml::parseFile($path)['review_status'] ?? null) === 'verified');
        $this->assertNotNull($this->policyFile, 'the corpus has a verified record to second-check');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dataDir);
        parent::tearDown();
    }

    /** Writes a second review onto the test record and returns that file's errors. @return list<string> */
    private function errorsWith(array $secondReview): array
    {
        $record = Yaml::parseFile($this->policyFile);
        $record['second_review'] = $secondReview;
        File::put($this->policyFile, Yaml::dump($record, 8, 2));
        $repository = new PolicyDataRepository($this->dataDir);
        $errors = (new PolicyDataValidator($repository, new SchemaValidator($repository->schemaDir())))->run();

        return $errors[ltrim(str_replace($this->dataDir, '', $this->policyFile), '/')] ?? [];
    }

    private function review(array $overrides = []): array
    {
        $record = Yaml::parseFile($this->policyFile);

        return array_replace([
            'reviewed_by' => 'Jane Doe',
            'reviewed_on' => '2026-10-02',
            'sample' => '2026-Q4',
            'coded' => ['status' => $record['status'], 'is_binding' => (bool) $record['is_binding'], 'review_status' => 'verified'],
            'agreed' => true,
            'fields_disputed' => [],
        ], $overrides);
    }

    public function test_a_second_review_by_a_different_published_reviewer_validates(): void
    {
        $this->assertSame([], $this->errorsWith($this->review()));

        $record = Yaml::parseFile($this->policyFile);
        $other = $record['status'] === 'in_force' ? 'adopted' : 'in_force';
        $this->assertSame([], $this->errorsWith($this->review([
            'agreed' => false,
            'coded' => ['status' => $other, 'is_binding' => (bool) $record['is_binding'], 'review_status' => 'verified'],
            'fields_disputed' => [['field' => 'status', 'first' => $record['status'], 'resolution' => 'Resolved against the official journal.'], ['field' => 'dates', 'note' => 'Application date']],
        ])));
    }

    public function test_the_second_reviewer_must_be_a_different_person_on_the_published_roster(): void
    {
        $errors = $this->errorsWith($this->review(['reviewed_by' => 'Bhaskar Bhatt']));
        $this->assertNotEmpty(preg_grep('/must be a different person/', $errors), implode("\n", $errors));

        // Case or spacing variants of the first reviewer's name are the same person.
        $errors = $this->errorsWith($this->review(['reviewed_by' => 'bhaskar bhatt']));
        $this->assertNotEmpty(preg_grep('/must be a different person/', $errors), implode("\n", $errors));

        $errors = $this->errorsWith($this->review(['reviewed_by' => 'Somebody Unlisted']));
        $this->assertNotEmpty(preg_grep('/is not a published reviewer/', $errors), implode("\n", $errors));

        // The editorial desk is unpublished, so it cannot be anyone's second check.
        $errors = $this->errorsWith($this->review(['reviewed_by' => 'AI Policy Tracker']));
        $this->assertNotEmpty(preg_grep('/is not a published reviewer/', $errors), implode("\n", $errors));
    }

    public function test_disputes_must_be_consistent_and_carry_the_first_reviewers_value(): void
    {
        $errors = $this->errorsWith($this->review(['agreed' => true, 'fields_disputed' => [['field' => 'dates']]]));
        $this->assertNotEmpty(preg_grep('/agreed: must be true exactly when/', $errors), implode("\n", $errors));

        $errors = $this->errorsWith($this->review(['agreed' => false, 'fields_disputed' => [['field' => 'status']]]));
        $this->assertNotEmpty(preg_grep('/first reviewer\'s status is required/', $errors), implode("\n", $errors));

        $coded = $this->review()['coded'];
        $errors = $this->errorsWith($this->review(['agreed' => false, 'fields_disputed' => [['field' => 'status', 'first' => $coded['status']]]]));
        $this->assertNotEmpty(preg_grep('/both reviewers recorded the same value/', $errors), implode("\n", $errors));

        $errors = $this->errorsWith($this->review(['agreed' => false, 'fields_disputed' => [['field' => 'colour']]]));
        $this->assertNotEmpty(preg_grep('/is not one of/', $errors), implode("\n", $errors));

        $errors = $this->errorsWith($this->review(['reviewed_on' => now()->addDay()->toDateString()]));
        $this->assertNotEmpty(preg_grep('/is in the future/', $errors), implode("\n", $errors));

        $errors = $this->errorsWith($this->review(['agreeed' => true]));
        $this->assertNotEmpty(preg_grep('/unknown key "agreeed"/', $errors), implode("\n", $errors));
    }

    public function test_the_repository_data_passes_with_the_new_field_absent(): void
    {
        $this->artisan('policy:validate')->assertSuccessful();
    }

    /** Gives `$n` published verified records an agreed second check in the database. */
    private function doubleCheck(int $n): void
    {
        PolicyInstrument::published()->where('review_status', 'verified')->orderBy('slug')->limit($n)->get()
            ->each(fn (PolicyInstrument $p) => $p->forceFill(['second_review' => [
                'reviewed_by' => 'Jane Doe', 'reviewed_on' => '2026-10-02', 'agreed' => true, 'fields_disputed' => [],
                'coded' => ['status' => $p->status, 'is_binding' => (bool) $p->is_binding, 'review_status' => 'verified'],
            ]])->save());
    }

    public function test_methodology_says_plainly_when_no_second_checks_exist(): void
    {
        $this->get(route('methodology'))->assertOk()
            ->assertSee('Independent checks')
            ->assertSee('No independent second checks have been published yet.')
            ->assertSee('data-independent-checks="0"', false)
            ->assertSee(route('corrections'), false)
            ->assertDontSee('data-agreement-table', false);
    }

    public function test_methodology_withholds_agreement_figures_below_the_threshold(): void
    {
        $this->doubleCheck(3);
        $this->get(route('methodology'))->assertOk()
            ->assertSee('data-independent-checks="3"', false)
            ->assertSee('Agreement figures are published once at least 20 records')
            ->assertDontSee('data-agreement-table', false)
            ->assertDontSee('kappa</th>', false);
    }

    public function test_methodology_publishes_the_agreement_table_at_the_threshold(): void
    {
        $this->doubleCheck(20);
        $html = $this->get(route('methodology'))->assertOk()
            ->assertSee('data-independent-checks="20"', false)
            ->assertSee('data-agreement-table', false)
            ->assertSee("Cohen's kappa</th>", false)
            ->getContent();
        // Everyone agreed and all coded "verified": 100% and an undefined review-status kappa.
        $this->assertStringContainsString('100.0%', $html);
        $this->assertStringContainsString('undefined', $html);
    }

    public function test_a_second_check_by_the_first_reviewer_is_not_counted(): void
    {
        $policy = PolicyInstrument::published()->where('review_status', 'verified')->whereNotNull('reviewed_by')->firstOrFail();
        $policy->forceFill(['second_review' => ['reviewed_by' => $policy->reviewed_by, 'reviewed_on' => '2026-10-02', 'agreed' => true, 'fields_disputed' => [], 'coded' => []]])->save();

        $this->assertFalse($policy->fresh()->isDoubleChecked());
        $this->get(route('methodology'))->assertSee('data-independent-checks="0"', false);
    }

    public function test_the_state_of_report_links_the_corrections_log(): void
    {
        $this->get(route('state-of.show'))->assertOk()->assertSee(route('corrections'), false);
    }

    /** @return list<string> */
    private function sample(array $options): array
    {
        Artisan::call('verification:sample', $options);

        return array_values(array_filter(explode("\n", trim(Artisan::output())), fn ($l) => $l !== '' && ! str_starts_with($l, '#')));
    }

    public function test_the_sample_is_reproducible_for_a_seed_and_sized_to_the_percentage(): void
    {
        $population = PolicyInstrument::published()->where('review_status', 'verified')->whereNotNull('reviewed_by')->count();
        $this->assertGreaterThan(10, $population);

        $first = $this->sample(['--percent' => 20, '--seed' => '2026-Q4']);
        $again = $this->sample(['--percent' => 20, '--seed' => '2026-Q4']);
        $other = $this->sample(['--percent' => 20, '--seed' => 'another-seed']);

        $this->assertSame($first, $again);
        $this->assertCount((int) ceil($population * 0.2), $first);
        $this->assertNotSame($first, $other);
        $this->assertSame($first, array_values(array_unique($first)));
        $this->assertSame($first, $this->sample(['--percent' => 20, '--quarter' => '2026-Q4']), 'the seed defaults to the quarter label');

        // Every sampled slug is a published, verified record.
        $this->assertSame(count($first), PolicyInstrument::published()->where('review_status', 'verified')->whereIn('slug', $first)->count());
    }

    public function test_the_draw_is_independent_of_input_order_and_skips_double_checked_records(): void
    {
        $population = array_map(fn ($i) => 'record-'.str_pad((string) $i, 3, '0', STR_PAD_LEFT), range(1, 50));
        $shuffled = array_reverse($population);
        $this->assertSame(VerificationSampleCommand::draw($population, 20, '7'), VerificationSampleCommand::draw($shuffled, 20, '7'));
        $this->assertCount(10, VerificationSampleCommand::draw($population, 20, '7'));
        $this->assertSame([], VerificationSampleCommand::draw([], 20, '7'));

        $this->doubleCheck(3);
        $checked = PolicyInstrument::published()->whereNotNull('second_review')->pluck('slug')->all();
        $this->assertSame([], array_intersect($checked, $this->sample(['--percent' => 100])));
        $this->assertSame($checked, array_values(array_intersect($checked, $this->sample(['--percent' => 100, '--include-checked' => true]))));
    }

    public function test_the_sample_rejects_a_bad_percentage_or_quarter(): void
    {
        $this->assertSame(1, Artisan::call('verification:sample', ['--percent' => 0]));
        $this->assertSame(1, Artisan::call('verification:sample', ['--percent' => 150]));
        $this->assertSame(1, Artisan::call('verification:sample', ['--quarter' => '2026-Q9']));
    }
}
