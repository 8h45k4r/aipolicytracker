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

    public function test_every_entry_and_submenu_view_in_the_config_is_a_real_route(): void
    {
        $seen = [];
        foreach (config('admin-navigation.sections') as $section => $entries) {
            $this->assertNotEmpty($entries, "{$section} has entries");
            foreach ($entries as $entry) {
                $this->assertTrue(Route::has($entry['route']), "{$entry['route']} exists");
                $this->assertNotContains($entry['route'], $seen, "{$entry['route']} is listed once");
                $this->assertNotEmpty($entry['can'], "{$entry['label']} names the capability its page needs");
                $this->assertNotEmpty($entry['icon'], "{$entry['label']} has an icon");
                $seen[] = $entry['route'];
                foreach ($entry['children'] ?? [] as $child) {
                    $this->assertTrue(Route::has($child['route'] ?? $entry['route']), "{$entry['label']} › {$child['label']}");
                }
            }
        }
    }

    public function test_a_submenu_opens_on_the_view_being_shown_and_the_top_bar_names_it(): void
    {
        $owner = $this->owner();
        $html = $this->actingAs($owner)->get(route('backend.review.index', ['type' => 'jurisdiction']))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('#<details class="adm-nav-parent"\s+open\s+data-admin-nav-parent>\s*<summary class="adm-nav-link is-active"[^>]*>.*?Review queue#s', $html);
        $this->assertMatchesRegularExpression('#href="'.preg_quote(route('backend.review.index', ['type' => 'jurisdiction']), '#').'" class="adm-nav-sublink" aria-current="page"#', $html);
        $this->assertStringNotContainsString('href="'.route('backend.review.index', ['type' => 'policy']).'" class="adm-nav-sublink" aria-current="page"', $html, 'one view is current');
        $this->assertMatchesRegularExpression('#<span>Records</span> <span aria-hidden="true">/</span> <span>Review queue</span> <span aria-hidden="true">/</span> <span class="font-medium text-brand-navy">Jurisdictions</span>#', $html);

        // With no filter in the address, the page's default view is the current one.
        $html = $this->actingAs($owner)->get(route('backend.review.index'))->assertOk()->getContent();
        $this->assertStringContainsString('href="'.route('backend.review.index', ['type' => 'policy']).'" class="adm-nav-sublink" aria-current="page"', $html);
    }

    public function test_the_sidebar_offers_collapse_to_icons(): void
    {
        $html = $this->actingAs($this->owner())->get(route('backend.admin.dashboard'))->assertOk()->getContent();
        $this->assertStringContainsString('data-admin-collapse', $html);
        $this->assertStringContainsString("localStorage.getItem('adm-nav-rail')", $html, 'a saved choice applies before the first paint');
        $this->assertMatchesRegularExpression('#<script nonce="[^"]+">try \{ if \(localStorage#', $html, 'the inline script carries the CSP nonce');
        $this->assertStringContainsString('class="adm-nav-icon"', $html);
    }

    public function test_waiting_work_shows_as_a_count_beside_its_entry(): void
    {
        ContributorSubmission::create(['type' => 'correction', 'summary' => 'Wrong date', 'status' => 'pending_review']);
        ContributorSubmission::create(['type' => 'correction', 'summary' => 'Wrong source', 'status' => 'pending_review']);
        NavCounts::forget();

        $html = $this->actingAs($this->owner())->get(route('backend.admin.dashboard'))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('#<span class="adm-nav-text">Submissions</span><span class="adm-nav-count adm-nav-count-warn"[^>]*><span class="sr-only">, </span>2<span class="sr-only"> submissions waiting for review</span>#', $html);
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
        $this->assertMatchesRegularExpression('#<span>Administration</span> <span aria-hidden="true">/</span> <span>Users and roles</span> <span aria-hidden="true">/</span> <span class="font-medium text-brand-navy">All accounts</span>#', $html);
        $this->assertSame(1, substr_count($html, 'class="adm-search-button"'), 'one search on wide screens');
        $this->assertStringNotContainsString('Go to…', $html, 'the second, page-only search box is gone');
    }
}
