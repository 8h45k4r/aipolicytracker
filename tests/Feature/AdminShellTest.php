<?php

namespace Tests\Feature;

use App\Enums\AdminRole;
use App\Http\Middleware\EnsureAdminSecondFactor;
use App\Models\AdminAuditLog;
use App\Models\ContributorSubmission;
use App\Models\JobRun;
use App\Models\Tool;
use App\Models\User;
use App\Providers\AppServiceProvider;
use App\Support\Admin\AuditActions;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * The admin shell: errors explained inside the admin, the admin's own rate limit, the
 * palette's record search and the sidebar.
 */
class AdminShellTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        config(['aipolicytracker.admin_emails' => ['owner@example.org']]);

        return User::factory()->create(['name' => 'Olivia Owner', 'email' => 'owner@example.org', 'email_verified_at' => now()]);
    }

    private function withRole(AdminRole $role, array $attributes = []): User
    {
        return User::factory()->create(['admin_role' => $role] + $attributes);
    }

    // ---- Errors inside /backend ------------------------------------------------------

    public function test_a_refused_page_explains_the_role_inside_the_admin_layout(): void
    {
        $editor = $this->withRole(AdminRole::Editor);

        $response = $this->actingAs($editor)->get(route('backend.admin.audit'))->assertForbidden();
        $html = $response->getContent();

        $this->assertStringContainsString('data-admin-error="403"', $html);
        $this->assertStringContainsString('Your role (Editor) can’t open this page', $html);
        $this->assertStringContainsString('“View the audit log”', $html, 'names the permission the role lacks');
        $this->assertStringContainsString('ask an owner', strtolower($html));
        $this->assertStringContainsString('aria-label="Admin navigation"', $html, 'the sidebar stays');
        $this->assertStringContainsString('Back to the dashboard', $html);
        $this->assertStringNotContainsString('Every policy record on this site is public', $html, 'not the public page');
    }

    public function test_an_unknown_admin_address_is_the_admins_own_not_found(): void
    {
        $owner = $this->owner();
        $html = $this->actingAs($owner)->get('/backend/admin/no-such-page')->assertNotFound()->getContent();

        $this->assertStringContainsString('data-admin-error="404"', $html);
        $this->assertStringContainsString('aria-label="Admin navigation"', $html);
        $this->assertStringContainsString('Nothing at this address', $html);

        // A missing record behind a real route reads the same.
        $this->actingAs($owner)->get('/backend/admin/tools/999999/edit')->assertNotFound()->assertSee('data-admin-error="404"', false);
    }

    public function test_the_admin_error_page_is_never_shown_to_a_session_that_has_not_passed_the_gates(): void
    {
        // Signed out: the fallback route sends a guest to sign in, as every admin page does.
        $this->get('/backend/admin/no-such-page')->assertRedirect(route('login'));

        // Signed in but not past the second factor: the gate's redirect, no admin page.
        $owner = $this->owner();
        $owner->forceFill(['two_factor_secret' => 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', 'two_factor_confirmed_at' => now()])->save();
        $this->be($owner);
        $this->get('/backend/admin/tools/999999/edit')->assertNotFound()->assertDontSee('aria-label="Admin navigation"', false);
    }

    public function test_an_expired_form_says_so_and_offers_a_way_back(): void
    {
        $owner = $this->owner();
        Route::middleware('web')->post('/backend/__test/expired', fn () => abort(419));

        $html = $this->actingAs($owner)->from(route('backend.admin.jobs'))->post('/backend/__test/expired')->assertStatus(419)->getContent();
        $this->assertStringContainsString('This form expired before it was sent', $html);
        $this->assertStringContainsString('aria-label="Admin navigation"', $html);
        $this->assertStringContainsString(route('backend.admin.jobs'), $html, 'back to the page the form was on');

        // Signed out while the page was open: a sign-in link, and no admin navigation.
        $this->app['auth']->forgetGuards();
        $this->flushSession();
        $html = $this->post('/backend/__test/expired')->assertStatus(419)->getContent();
        $this->assertStringContainsString('Your session has ended', $html);
        $this->assertStringContainsString(route('login'), $html);
        $this->assertStringNotContainsString('aria-label="Admin navigation"', $html);
    }

    public function test_a_server_error_in_the_admin_keeps_the_layout_and_gives_a_reference(): void
    {
        config(['app.debug' => false]);
        Route::middleware('web')->get('/backend/__test/broken', fn () => throw new \RuntimeException('secret detail'));

        $response = $this->actingAs($this->owner())->get('/backend/__test/broken')->assertStatus(500);
        $html = $response->getContent();
        $this->assertStringContainsString('Something broke on our side', $html);
        $this->assertStringContainsString('aria-label="Admin navigation"', $html);
        $this->assertStringContainsString((string) $response->headers->get('X-Request-Id'), $html, 'the reference on the page is the one on the wire');
        $this->assertStringNotContainsString('secret detail', $html);
    }

    public function test_public_error_pages_are_unchanged_outside_the_admin(): void
    {
        $this->actingAs($this->owner())->get('/no-such-public-page')->assertNotFound()
            ->assertDontSee('data-admin-error', false)->assertSee('Page not found');
    }

    // ---- Rate limiting -----------------------------------------------------------------

    public function test_an_admin_is_not_held_to_the_public_rate_while_clicking_quickly(): void
    {
        $owner = $this->owner();
        $this->actingAs($owner);

        $first = $this->getJson(route('backend.admin.search', ['q' => 'x']))->assertOk();
        $this->assertSame((string) AppServiceProvider::ADMIN_REQUESTS_PER_MINUTE, $first->headers->get('X-RateLimit-Limit'));

        for ($i = 0; $i < 130; $i++) {
            $this->getJson(route('backend.admin.search', ['q' => 'x']))->assertOk();
        }
        $this->get(route('backend.admin.dashboard'))->assertOk();

        // The admin's requests do not count against the per-account bucket the public
        // `throttle:N,1` routes share.
        $this->get('/search/suggest?q=ai')->assertHeader('X-RateLimit-Limit', '120')->assertHeader('X-RateLimit-Remaining', '119');
    }

    public function test_the_public_api_limit_is_unchanged(): void
    {
        $this->getJson('/api/v1/jurisdictions')->assertHeader('X-RateLimit-Limit', '120');
    }

    public function test_an_admin_over_the_limit_is_told_how_long_to_wait_inside_the_admin(): void
    {
        RateLimiter::for('admin', fn ($request) => Limit::perMinute(2)->by('test:'.$request->user()?->id));
        $this->actingAs($this->owner());

        $this->get(route('backend.admin.jobs'))->assertOk();
        $this->get(route('backend.admin.jobs'))->assertOk();
        $response = $this->get(route('backend.admin.jobs'))->assertStatus(429);

        $this->assertNotNull($response->headers->get('Retry-After'));
        $html = $response->getContent();
        $this->assertStringContainsString('Slow down a moment', $html);
        $this->assertMatchesRegularExpression('/Try again in \d+ seconds?\./', $html);
        $this->assertStringContainsString('aria-label="Admin navigation"', $html);
    }

    // ---- Palette record search -----------------------------------------------------------

    public function test_the_palette_search_returns_records_for_an_owner_with_their_admin_pages(): void
    {
        $owner = $this->owner();
        User::factory()->create(['name' => 'Ivy Example', 'email' => 'ivy@example.org']);
        $s = ContributorSubmission::forceCreate(['type' => 'correction', 'summary' => 'Ivy says the date is wrong', 'status' => 'pending_review', 'submitter_email' => 'reader@example.org']);

        $json = $this->actingAs($owner)->getJson(route('backend.admin.search', ['q' => 'IVY']))->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonStructure(['query', 'results' => [['kind', 'label', 'detail', 'url']]])
            ->json();

        $kinds = collect($json['results'])->groupBy('kind');
        $this->assertSame('Ivy Example', $kinds['user'][0]['label']);
        $this->assertStringContainsString('/backend/admin/users/', $kinds['user'][0]['url']);
        $this->assertStringEndsWith('#submission-'.$s->id, $kinds['submission'][0]['url']);
    }

    public function test_the_palette_finds_a_policy_by_its_short_title_or_slug(): void
    {
        $owner = $this->owner();
        $this->artisan('policy:import');

        $json = $this->actingAs($owner)->getJson(route('backend.admin.search', ['q' => 'eu ai act']))->assertOk()->json();
        $policies = collect($json['results'])->where('kind', 'policy')->values();
        $this->assertNotEmpty($policies, 'the short title names the record');
        $this->assertStringStartsWith('eu-ai-act ', $policies[0]['detail'], 'the exact short title comes first');
    }

    public function test_the_palette_search_offers_only_what_the_role_may_open(): void
    {
        User::factory()->create(['name' => 'Ivy Example', 'email' => 'ivy@example.org']);
        ContributorSubmission::forceCreate(['type' => 'correction', 'summary' => 'Ivy says the date is wrong', 'status' => 'pending_review', 'submitter_email' => 'reader@example.org']);

        $reviewer = $this->withRole(AdminRole::Reviewer);
        $kinds = collect($this->actingAs($reviewer)->getJson(route('backend.admin.search', ['q' => 'ivy']))->assertOk()->json('results'))->pluck('kind')->unique()->values()->all();
        $this->assertSame(['submission'], $kinds, 'a reviewer cannot open users and roles, so is never offered a user');
    }

    public function test_the_palette_search_refuses_guests_ordinary_accounts_and_roles_with_nothing_to_search(): void
    {
        $this->getJson(route('backend.admin.search', ['q' => 'ivy']))->assertUnauthorized();

        $reader = User::factory()->create();
        $this->actingAs($reader)->getJson(route('backend.admin.search', ['q' => 'ivy']))->assertRedirect(route('home'));

        $analyst = $this->withRole(AdminRole::Analyst);
        $this->actingAs($analyst)->getJson(route('backend.admin.search', ['q' => 'ivy']))->assertForbidden();
        $this->actingAs($analyst)->get(route('backend.admin.dashboard'))->assertOk()->assertDontSee('data-search-url', false);
    }

    public function test_a_short_query_searches_nothing(): void
    {
        $this->actingAs($this->owner())->getJson(route('backend.admin.search', ['q' => 'O']))->assertOk()->assertExactJson(['query' => 'O', 'results' => []]);
    }

    // ---- Shell markup -------------------------------------------------------------------

    public function test_the_palette_is_an_accessible_combobox_with_record_search_for_an_owner(): void
    {
        $html = $this->actingAs($this->owner())->get(route('backend.admin.dashboard'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/<input[^>]+role="combobox"[^>]+aria-controls="adm-palette-list"/', $html);
        $this->assertStringContainsString('id="adm-palette-list" role="listbox"', $html);
        $this->assertStringContainsString('data-search-url="'.route('backend.admin.search').'"', $html);
        $this->assertStringContainsString('data-palette-status', $html, 'a live region says when records are searched');
    }

    public function test_the_sidebar_labels_pass_contrast_and_the_account_menu_shows_initials(): void
    {
        $html = $this->actingAs($this->owner())->get(route('backend.admin.dashboard'))->assertOk()->getContent();

        // White at 45% on the ink background measured 4.47:1; 65% is 8:1.
        $this->assertStringNotContainsString('text-white/45', $html);
        $this->assertMatchesRegularExpression('/<p class="[^"]*text-white\/65[^"]*">Overview<\/p>/', $html);
        $this->assertStringContainsString('min-h-0 flex-1 overflow-y-auto', $html, 'the menu scrolls inside the sidebar, the footer stays');
        $this->assertStringContainsString('data-admin-account', $html);
        $this->assertMatchesRegularExpression('/aria-hidden="true"[^>]*>OO<\/span>/', $html, 'initials avatar');
        $this->assertStringContainsString(route('admin.two-factor.recovery'), $html, 'the authenticator stays one click away');
    }

    // ---- Audit log in words ---------------------------------------------------------------

    public function test_audit_actions_read_as_words_with_a_fallback_for_unlabelled_routes(): void
    {
        $this->assertSame('Invited a user', AuditActions::label('backend.admin.users.invite'));
        $this->assertSame('Saved settings', AuditActions::label('backend.admin.settings.save'));
        $this->assertSame('Changed a role to Editor', AuditActions::label('backend.admin.users.role', 'POST', null, ['user' => 3, 'role' => 'editor']));
        $this->assertSame('Changed a role', AuditActions::label('backend.admin.users.role', 'POST', null, ['role' => 'emperor']), 'an unknown value is not repeated');
        $this->assertSame('Ran “Weekly digest”', AuditActions::label('backend.admin.jobs.run.confirmed', 'POST', null, ['job' => 'digest']));
        $this->assertSame('Rejected several submissions', AuditActions::label('backend.review.decide.many', 'POST', null, ['decision' => 'rejected']));
        $this->assertSame('Something new here', AuditActions::label('backend.admin.something.new-here'), 'a route nobody labelled still reads as words');
        $this->assertSame('DELETE /backend/x', AuditActions::label(null, 'DELETE', '/backend/x'));

        $this->assertSame(['Success', 'Success', 'Refused', 'Refused', 'Failed'], array_map([AuditActions::class, 'outcome'], [200, 302, 403, 422, 500]));
    }

    public function test_the_audit_log_shows_labels_badges_and_links_the_record(): void
    {
        $owner = $this->owner();
        $ivy = User::factory()->create(['name' => 'Ivy Example']);
        AdminAuditLog::forceCreate(['user_id' => $owner->id, 'user_email' => $owner->email, 'method' => 'POST', 'route_name' => 'backend.admin.users.role', 'path' => '/backend/admin/users/'.$ivy->id.'/role', 'route_params' => ['user' => $ivy->id, 'role' => 'editor'], 'status' => 302, 'created_at' => now()]);
        AdminAuditLog::forceCreate(['user_id' => $owner->id, 'user_email' => $owner->email, 'method' => 'POST', 'route_name' => 'backend.admin.users.invite', 'path' => '/backend/admin/users/invite', 'status' => 422, 'created_at' => now()]);

        $html = $this->actingAs($owner)->get(route('backend.admin.audit'))->assertOk()->getContent();

        $this->assertStringContainsString('Changed a role to Editor</a>', $html);
        $this->assertStringContainsString('Invited a user</a>', $html);
        $this->assertStringContainsString('href="'.route('backend.admin.users.show', $ivy).'"', $html, 'the record is linked');
        $this->assertStringContainsString('>Ivy Example</a>', $html);
        $this->assertStringContainsString('>Success</span>', $html);
        $this->assertStringContainsString('>Refused</span>', $html);
        $this->assertStringContainsString('(HTTP 422)', $html, 'the code is still there for whoever needs it');
        $this->assertStringContainsString('<option value="backend.admin.users.invite"', $html, 'the filter still filters by route');

        // An analyst may read the log but not open users: the name is shown, not linked.
        $analyst = $this->withRole(AdminRole::Analyst);
        $html = $this->actingAs($analyst)->get(route('backend.admin.audit'))->assertOk()->getContent();
        $this->assertStringContainsString('Ivy Example', $html);
        $this->assertStringNotContainsString('href="'.route('backend.admin.users.show', $ivy).'"', $html);
    }

    // ---- Pages ------------------------------------------------------------------------------

    public function test_the_dashboard_leads_with_attention_and_offers_quick_actions_as_commands(): void
    {
        config(['mail.default' => 'log']);
        $html = $this->actingAs($this->owner())->get(route('backend.admin.dashboard'))->assertOk()->getContent();

        $this->assertLessThan(strpos($html, 'id="overview-h"'), strpos($html, 'data-attention'), 'attention comes before the figures');
        $this->assertStringContainsString('adm-sev adm-sev-critical', $html, 'a severity icon');
        foreach (['Invite a user', 'Open the review queue', 'Run the weekly digest as a dry run', 'Add a funder'] as $command) {
            $this->assertStringContainsString('data-command="'.$command.'"', $html);
        }
        $this->assertStringContainsString(route('backend.admin.jobs').'#job-digest_dry_run', $html);

        $html = $this->actingAs($this->withRole(AdminRole::Analyst))->get(route('backend.admin.dashboard'))->assertOk()->getContent();
        $this->assertStringNotContainsString('data-command="Invite a user"', $html, 'only the actions this role can take');
        $this->assertStringNotContainsString('data-command="Add a funder"', $html);
    }

    public function test_the_digest_dry_run_counts_without_sending(): void
    {
        Mail::fake();
        $run = JobRun::run('digest_dry_run', 'admin');

        $this->assertTrue($run->succeeded());
        $this->assertStringContainsString('nothing sent', (string) $run->output);
        Mail::assertNothingSent();
        $this->assertFalse(JobRun::JOBS['digest_dry_run']['confirm'], 'it sends nothing, so it asks for no password');
    }

    public function test_page_headers_match_the_sidebar_and_the_tool_form_has_a_breadcrumb(): void
    {
        $owner = $this->owner();
        $this->actingAs($owner);
        foreach (['backend.admin.audit' => 'Audit log', 'backend.admin.jobs' => 'Jobs and schedule', 'backend.admin.external' => 'External data', 'backend.admin.tools.index' => 'Tool library', 'backend.admin.downloads' => 'Guides and downloads', 'backend.admin.dashboard' => 'Dashboard'] as $route => $label) {
            $this->get(route($route))->assertOk()->assertSee('<h1 class="text-2xl font-semibold tracking-tight text-brand-navy">'.$label.'</h1>', false);
        }

        $tool = Tool::create(['title' => 'Ivy register', 'slug' => 'ivy-register', 'type' => array_key_first(Tool::TYPES), 'status' => 'draft', 'short' => 'Short.', 'version' => '1.0', 'updated_on' => now()->toDateString(), 'fields' => [], 'instructions' => [], 'frameworks' => [], 'topics' => [], 'related_guides' => [], 'related_policies' => []]);
        $html = $this->get(route('backend.admin.tools.edit', $tool))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('#<nav class="adm-crumbs[^"]*" aria-label="Breadcrumb"><ol>\s*<li><a href="'.preg_quote(route('backend.admin.tools.index'), '#').'">Tool library</a></li>\s*<li><span aria-current="page">Ivy register</span></li>#', $html);
    }

    public function test_pages_this_shell_owns_use_the_button_scale_not_ad_hoc_overrides(): void
    {
        $views = ['backend/admin/dashboard', 'backend/admin/audit', 'backend/admin/jobs', 'backend/admin/external', 'backend/admin/downloads', 'backend/admin/tools/index', 'backend/admin/tools/form', 'components/backend/filters', 'components/backend/drawer'];
        foreach ($views as $view) {
            $source = file_get_contents(resource_path('views/'.$view.'.blade.php'));
            $this->assertDoesNotMatchRegularExpression('/btn-(primary|secondary)[^"]*!min-h/', $source, $view.' sizes a button by hand');
        }
        $this->assertStringContainsString('.btn-sm', file_get_contents(resource_path('css/admin.css')));
    }

    public function test_the_second_factor_check_is_exposed_for_the_error_pages(): void
    {
        $owner = $this->owner();
        $this->actingAs($owner);
        $this->get(route('backend.admin.dashboard'))->assertOk();
        $this->assertSame((int) $owner->id, (int) session(EnsureAdminSecondFactor::SESSION_KEY));
    }
}
