<?php

namespace Tests\Feature;

use App\Enums\AdminRole;
use App\Models\ContributorSubmission;
use App\Models\User;
use App\Services\Admin\NavCounts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/** The admin sidebar of November 2026: grouped by the work, folding, with what is waiting. */
class AdminNavigationTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        $user = User::factory()->create(['email' => 'owner@example.org']);
        config(['aipolicytracker.admin_emails' => ['owner@example.org']]);

        return $user;
    }

    public function test_every_entry_in_the_navigation_config_is_a_route_with_a_capability(): void
    {
        $seen = [];
        foreach (config('admin-navigation.groups') as $group => $entries) {
            $this->assertNotEmpty($entries, "{$group} has entries");
            foreach ($entries as [$route, $label, $capability]) {
                $this->assertTrue(Route::has($route), "{$route} exists");
                $this->assertNotContains($route, $seen, "{$route} is listed once");
                $this->assertNotEmpty($capability, "{$label} names the capability its page needs");
                $seen[] = $route;
            }
        }
    }

    public function test_waiting_work_shows_as_a_count_beside_its_entry(): void
    {
        ContributorSubmission::create(['type' => 'correction', 'summary' => 'Wrong date', 'status' => 'pending_review']);
        ContributorSubmission::create(['type' => 'correction', 'summary' => 'Wrong source', 'status' => 'pending_review']);
        NavCounts::forget();

        $html = $this->actingAs($this->owner())->get(route('backend.admin.dashboard'))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('#href="'.preg_quote(route('backend.admin.submissions'), '#').'"[^>]*data-admin-nav-link><span data-nav-label>Submissions and feedback</span><span class="adm-nav-count adm-nav-count-warn"[^>]*><span class="sr-only">, </span>2<span class="sr-only"> submissions waiting for review</span>#', $html);
        // A folded group with waiting work keeps a marker, so folding hides nothing.
        $this->assertMatchesRegularExpression('#<span data-admin-nav-heading>Records</span><span class="adm-nav-dot"#', $html);
    }

    public function test_an_account_sees_only_the_groups_and_entries_it_can_open(): void
    {
        $analyst = User::factory()->create(['admin_role' => AdminRole::Analyst, 'two_factor_secret' => 'JBSWY3DPEHPK3PXP', 'two_factor_confirmed_at' => now()]);
        $html = $this->actingAs($analyst)->get(route('backend.admin.dashboard'))->assertOk()->getContent();

        $this->assertStringNotContainsString('<span data-admin-nav-heading>Administration</span>', $html, 'a group with nothing it may open is skipped');
        $this->assertStringNotContainsString('href="'.route('backend.admin.users.index').'"', $html);
        $this->assertStringContainsString('<span data-admin-nav-heading>Overview</span>', $html);
    }

    public function test_the_top_bar_names_the_section_and_offers_one_search(): void
    {
        $html = $this->actingAs($this->owner())->get(route('backend.admin.users.index'))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('#<span>Administration</span> <span aria-hidden="true">/</span> <span class="font-medium text-brand-navy">Users and roles</span>#', $html);
        $this->assertSame(1, substr_count($html, 'class="adm-search-button"'), 'one search on wide screens');
        $this->assertStringNotContainsString('Go to…', $html, 'the second, page-only search box is gone');
    }
}
