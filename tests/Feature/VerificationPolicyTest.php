<?php

namespace Tests\Feature;

use App\Models\PolicyInstrument;
use App\Services\Verification\VerificationPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VerificationPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->artisan('policy:import');
    }

    public function test_a_record_is_governed_by_the_first_rule_it_matches(): void
    {
        $policy = app(VerificationPolicy::class);
        $binding = PolicyInstrument::published()->where('is_binding', true)->firstOrFail();
        $rule = $policy->ruleFor($binding);
        $this->assertNotNull($rule);
        $this->assertTrue($rule['critical'], 'binding law is on a critical clock');
        $this->assertSame('binding', $rule['track']);

        $guidance = PolicyInstrument::published()->where('is_binding', false)->first();
        if ($guidance) {
            $this->assertSame('instruments-other', $policy->ruleFor($guidance)['id']);
            $this->assertFalse($policy->ruleFor($guidance)['critical']);
        }
    }

    public function test_a_record_confirmed_today_is_current_and_one_past_its_age_is_overdue(): void
    {
        $policy = app(VerificationPolicy::class);
        $instrument = PolicyInstrument::published()->where('is_binding', true)->firstOrFail();
        $limit = (int) $policy->ruleFor($instrument)['days'];

        $instrument->forceFill(['last_verified_at' => now()])->save();
        $row = $policy->assess()->firstWhere('record.id', $instrument->id);
        $this->assertSame(0, $row['age']);
        $this->assertFalse($row['overdue']);
        $this->assertFalse($row['never']);

        $instrument->forceFill(['last_verified_at' => now()->subDays($limit + 1)])->save();
        $this->assertTrue($policy->assess()->firstWhere('record.id', $instrument->id)['overdue']);

        $instrument->forceFill(['last_verified_at' => null])->save();
        $row = $policy->assess()->firstWhere('record.id', $instrument->id);
        $this->assertTrue($row['never'], 'a record never confirmed is never fresh');
        $this->assertTrue($row['overdue']);
    }

    public function test_the_check_fails_when_critical_breaches_exceed_the_budget_and_passes_within_it(): void
    {
        config(['verification.critical_budget' => 0]);
        $this->artisan('policy:freshness --list=0')->assertFailed();

        // Confirm every critical record today: the corpus is then within the budget.
        foreach (app(VerificationPolicy::class)->assess() as $row) {
            if ($row['rule']['critical']) {
                $row['record']->forceFill(['last_verified_at' => now()])->save();
            }
        }
        $this->artisan('policy:freshness --list=0')->expectsOutputToContain('Verification policy passed.')->assertSuccessful();
    }

    public function test_the_public_page_publishes_the_same_numbers_as_the_check(): void
    {
        $report = app(VerificationPolicy::class)->report();
        $this->assertGreaterThan(0, $report['covered']);

        $html = $this->get('/verification')->assertOk()
            ->assertSee('How current every record is')
            ->assertSee('Maximum age by record type')
            ->assertSee('Longest overdue')
            ->getContent();

        $this->assertStringContainsString(number_format($report['covered']), $html);
        $this->assertStringContainsString(number_format($report['overdue']), $html);
        foreach ($report['rules'] as $rule) {
            $this->assertStringContainsString(e($rule['label']), $html, 'every rule is published');
            $this->assertStringContainsString($rule['days'].' days', $html);
        }
        $this->get('/sitemap-static.xml')->assertOk()->assertSee('/verification');
    }

    public function test_the_json_summary_is_machine_readable_and_carries_the_verdict(): void
    {
        config(['verification.critical_budget' => 0]);
        $report = app(VerificationPolicy::class)->report();

        // The whole document is written in one call, so it is matched in one expectation.
        $expected = <<<JSON
        {
            "covered": {$report['covered']},
            "overdue": {$report['overdue']},
            "critical_overdue": {$report['critical_overdue']},
            "never_verified": {$report['never']},
            "budget": 0,
            "pass": false,
        JSON;

        $this->artisan('policy:freshness', ['--json' => true])->expectsOutputToContain($expected);

        // The exit code, which is the contract continuous integration relies on, is
        // asserted in test_the_check_fails_when_critical_breaches_exceed_the_budget_and_passes_within_it.
    }
}
