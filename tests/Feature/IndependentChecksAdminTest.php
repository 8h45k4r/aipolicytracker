<?php

namespace Tests\Feature;

use App\Enums\AdminRole;
use App\Models\JobRun;
use App\Models\PolicyInstrument;
use App\Models\User;
use App\Services\Verification\VerificationSample;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * The admin's Independent checks page: the quarter's sample (the same draw as
 * `verification:sample`), progress, agreement under the public threshold, disputes,
 * and the CSV reviewers work from.
 */
class IndependentChecksAdminTest extends TestCase
{
    use RefreshDatabase;

    private const QUARTER = '2026-Q4';

    protected function setUp(): void
    {
        parent::setUp();
        config(['aipolicytracker.admin_emails' => ['reviewer@example.test']]);
        $this->seed();
        $this->artisan('policy:import');
    }

    private function owner(): User
    {
        return User::factory()->create(['email' => 'reviewer@example.test', 'name' => 'Bhaskar Bhatt']);
    }

    private function role(AdminRole $role): static
    {
        $user = User::factory()->create(['email' => $role->value.'-'.uniqid().'@example.test', 'admin_role' => $role->value, 'two_factor_confirmed_at' => now()]);

        return $this->actingAs($user)->withSession(['admin.two_factor_passed_at' => now()->timestamp]);
    }

    /** @return list<string> */
    private function commandSample(string $quarter): array
    {
        Artisan::call('verification:sample', ['--quarter' => $quarter]);

        return array_values(array_filter(explode("\n", trim(Artisan::output())), fn ($l) => $l !== '' && ! str_starts_with($l, '#')));
    }

    private function secondCheck(PolicyInstrument $p, array $overrides = []): void
    {
        $p->forceFill(['second_review' => array_replace([
            'reviewed_by' => 'Jane Doe', 'reviewed_on' => '2026-10-02', 'sample' => self::QUARTER, 'agreed' => true, 'fields_disputed' => [],
            'coded' => ['status' => $p->status, 'is_binding' => (bool) $p->is_binding, 'review_status' => 'verified'],
        ], $overrides)])->save();
    }

    public function test_only_a_role_that_may_verify_records_can_open_the_page(): void
    {
        $this->get(route('backend.checks.index'))->assertRedirect('/login');
        $this->role(AdminRole::Analyst)->get(route('backend.checks.index'))->assertForbidden();
        $this->role(AdminRole::Analyst)->get(route('backend.checks.export'))->assertForbidden();
        $this->role(AdminRole::Reviewer)->get(route('backend.checks.index'))->assertOk()->assertSee('Independent checks');
    }

    public function test_the_page_lists_the_same_sample_as_the_command_with_first_reviewers(): void
    {
        $slugs = $this->commandSample(self::QUARTER);
        $this->assertNotEmpty($slugs);
        $this->assertSame($slugs, app(VerificationSample::class)->draw(self::QUARTER)['slugs'], 'the command and the service agree');

        $html = $this->actingAs($this->owner())->get(route('backend.checks.index', ['quarter' => self::QUARTER]))->assertOk()
            ->assertSee('Sample for '.self::QUARTER)
            ->assertSee('Seed <code>'.self::QUARTER.'</code>', false)
            ->assertSee('data-checks-progress="0/'.count($slugs).'"', false)
            ->assertSee('Second checks are entered by pull request')
            ->getContent();
        foreach ($slugs as $slug) {
            $this->assertStringContainsString('data-sample-row="'.$slug.'"', $html);
        }
        $first = PolicyInstrument::where('slug', $slugs[0])->firstOrFail();
        $this->assertStringContainsString(e($first->reviewed_by), $html);
        // A malformed quarter falls back to the current one rather than failing.
        $this->get(route('backend.checks.index', ['quarter' => '2026-Q9']))->assertOk();
    }

    public function test_a_check_recorded_for_this_quarter_keeps_the_sample_stable_and_counts_as_progress(): void
    {
        $before = app(VerificationSample::class)->draw(self::QUARTER);
        $checked = PolicyInstrument::where('slug', $before['slugs'][0])->firstOrFail();
        $this->secondCheck($checked);

        $after = app(VerificationSample::class)->draw(self::QUARTER);
        $this->assertSame($before['slugs'], $after['slugs'], 'recording a check for this sample does not change the draw');
        $this->assertSame($before['population'], $after['population']);

        $this->actingAs($this->owner())->get(route('backend.checks.index', ['quarter' => self::QUARTER]))->assertOk()
            ->assertSee('data-checks-progress="1/'.count($before['slugs']).'"', false)
            ->assertSee('Jane Doe');

        // A record checked in an earlier quarter's sample leaves the population.
        $this->secondCheck($checked, ['sample' => '2026-Q3']);
        $this->assertNotContains($checked->slug, app(VerificationSample::class)->draw(self::QUARTER, 100)['slugs']);
    }

    public function test_the_sample_exports_as_csv(): void
    {
        $slugs = app(VerificationSample::class)->draw(self::QUARTER)['slugs'];
        $response = $this->actingAs($this->owner())->get(route('backend.checks.export', ['quarter' => self::QUARTER]))->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('Content-Type'));
        $csv = $response->streamedContent();
        $lines = array_values(array_filter(explode("\n", trim(ltrim($csv, "\xEF\xBB\xBF")))));
        $this->assertSame('quarter,seed,slug,title,jurisdiction,record_url,official_source_url,first_reviewer,last_verified_at,second_reviewer,second_reviewed_on,agreed,fields_disputed', $lines[0]);
        $this->assertCount(count($slugs) + 1, $lines);
        foreach ($slugs as $slug) {
            $this->assertStringContainsString(','.$slug.',', $csv);
        }
    }

    public function test_agreement_figures_follow_the_public_threshold_and_disputes_are_listed(): void
    {
        $records = PolicyInstrument::published()->where('review_status', 'verified')->whereNotNull('reviewed_by')->orderBy('slug')->limit(20)->get();
        $this->assertCount(20, $records);
        $disputed = $records->first();
        $first = $disputed->status === 'in_force' ? 'adopted' : 'in_force';
        $this->secondCheck($disputed, ['agreed' => false, 'fields_disputed' => [['field' => 'status', 'first' => $first, 'note' => 'Application date differs']]]);
        $records->slice(1, 2)->each(fn ($p) => $this->secondCheck($p));

        $this->actingAs($this->owner());
        $this->get(route('backend.checks.index'))->assertOk()
            ->assertSee('data-admin-agreement="3"', false)
            ->assertSee('3 of 20 double-checked records needed')
            ->assertDontSee('data-admin-agreement-table', false)
            ->assertSee('data-dispute="'.$disputed->slug.':status"', false)
            ->assertSee('Application date differs')
            ->assertSee('unresolved');

        $records->slice(3)->each(fn ($p) => $this->secondCheck($p));
        $this->get(route('backend.checks.index'))->assertOk()
            ->assertSee('data-admin-agreement="20"', false)
            ->assertSee('data-admin-agreement-table', false)
            ->assertSee('95.0%');
    }

    public function test_the_draw_is_a_registered_job_that_changes_nothing(): void
    {
        $this->assertArrayHasKey('verification_sample', JobRun::JOBS);
        $this->assertFalse(JobRun::isScheduled('verification_sample'));
        $run = JobRun::run('verification_sample', 'admin');
        $this->assertSame(0, $run->exit_code);
        $this->assertStringContainsString('# Independent-check sample for', (string) $run->output);
    }
}
