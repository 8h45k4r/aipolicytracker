<?php

namespace Tests\Feature;

use App\Enums\AdminRole;
use App\Models\ContributorSubmission;
use App\Models\JobRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        $user = User::factory()->create(['email' => 'owner@example.org']);
        config(['aipolicytracker.admin_emails' => ['owner@example.org']]);

        return $user;
    }

    public function test_the_dashboard_leads_with_what_needs_attention_most_serious_first(): void
    {
        config(['mail.default' => 'log', 'services.turnstile.site_key' => null]);
        ContributorSubmission::forceCreate(['type' => 'correction', 'summary' => 'Wrong date', 'status' => 'pending_review', 'submitter_email' => 'a@example.org']);
        JobRun::forceCreate(['job' => 'policy_import', 'trigger' => 'schedule', 'started_at' => now()->subHour(), 'finished_at' => now()->subHour(), 'exit_code' => 1, 'output' => 'Schema error']);

        $html = $this->actingAs($this->owner())->get(route('backend.admin.dashboard'))->assertOk()->getContent();

        preg_match_all('/data-severity="(\w+)"/', $html, $m);
        $this->assertNotEmpty($m[1]);
        $order = array_map(fn ($s) => ['critical' => 0, 'warning' => 1, 'info' => 2][$s], $m[1]);
        $this->assertSame($order, collect($order)->sort()->values()->all(), 'most serious first');
        $this->assertStringContainsString('A scheduled job failed', $html);
        $this->assertStringContainsString('Email is not being sent', $html);
        $this->assertStringContainsString('1 submission is waiting for review', $html);
        $this->assertStringContainsString('Bot protection is off', $html);
    }

    public function test_an_unscheduled_jobs_old_failure_is_not_raised(): void
    {
        config(['email.overlay_source' => null]);
        JobRun::forceCreate(['job' => 'email_domains', 'trigger' => 'schedule', 'started_at' => now()->subDays(2), 'finished_at' => now()->subDays(2), 'exit_code' => 1, 'output' => 'No source.']);

        $html = $this->actingAs($this->owner())->get(route('backend.admin.dashboard'))->assertOk()->getContent();
        $this->assertStringNotContainsString('scheduled job failed', $html);
        $this->assertStringContainsString('off: no list source set', $html);
        $this->assertFalse(JobRun::isScheduled('email_domains'));
        config(['email.overlay_source' => 'https://example.org/list.txt']);
        $this->assertTrue(JobRun::isScheduled('email_domains'));
    }

    public function test_attention_items_are_offered_only_to_accounts_that_can_act_on_them(): void
    {
        config(['mail.default' => 'log']);
        $analyst = User::factory()->create(['admin_role' => AdminRole::Analyst, 'two_factor_secret' => 'JBSWY3DPEHPK3PXP', 'two_factor_confirmed_at' => now()]);

        $html = $this->actingAs($analyst)->get(route('backend.admin.dashboard'))->assertOk()->getContent();
        $this->assertStringNotContainsString('Email is not being sent', $html, 'only someone who can change settings is told to');
        $this->assertStringNotContainsString(route('backend.admin.settings'), $html);
    }

    public function test_the_sidebar_is_grouped_fixed_and_pins_the_light_scheme(): void
    {
        $html = $this->actingAs($this->owner())->get(route('backend.admin.users.index'))->assertOk()->getContent();

        $this->assertStringContainsString('<html lang="en" data-scheme="light" data-admin>', $html, 'the admin stays light when the system is dark');
        $this->assertStringContainsString('lg:sticky lg:top-0 lg:h-screen', $html);
        foreach (['Overview', 'Content', 'Audience', 'Operations', 'Administration'] as $group) {
            $this->assertStringContainsString('>'.$group.'</p>', $html);
        }
        $this->assertMatchesRegularExpression('#href="'.preg_quote(route('backend.admin.users.index'), '#').'"[^>]*aria-current="page"#', $html, 'a section page marks its entry');
        $this->assertStringContainsString('data-admin-nav-filter', $html);
    }
}
