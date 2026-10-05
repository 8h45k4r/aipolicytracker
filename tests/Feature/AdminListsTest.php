<?php

namespace Tests\Feature;

use App\Models\AdminAuditLog;
use App\Models\JobRun;
use App\Models\Obligation;
use App\Models\PolicyInstrument;
use App\Models\Subscriber;
use App\Models\TemplateDownloadRequest;
use App\Models\User;
use App\Support\ContentCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** The admin lists: each filters, sorts and exports what it shows, and its figures open the rows behind them. */
class AdminListsTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        config(['aipolicytracker.admin_emails' => ['owner@example.org']]);

        return User::factory()->create(['email' => 'owner@example.org']);
    }

    /** @return list<array<int,string>> rows of a streamed CSV, header first, byte-order mark removed */
    private function csv(string $url, User $as): array
    {
        $body = $this->actingAs($as)->get($url)->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8')->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $body);

        return array_map('str_getcsv', array_values(array_filter(explode("\n", trim(substr($body, 3))))));
    }

    public function test_the_admin_reads_in_poppins(): void
    {
        $html = $this->actingAs($this->owner())->get(route('backend.admin.dashboard'))->assertOk()->getContent();
        $this->assertStringContainsString('family=poppins', $html);
        $this->assertStringContainsString('<html lang="en" data-scheme="light" data-admin>', $html, 'admin.css scopes its rules to this attribute');
        $this->assertStringContainsString('font-family: "Poppins"', file_get_contents(resource_path('css/admin.css')));
    }

    public function test_an_export_keeps_every_row_in_the_lists_own_order(): void
    {
        // More than one chunk, sorted by a column other than id: the case that lost rows
        // when the export paged by id.
        $now = now();
        DB::table('contributor_submissions')->insert(collect(range(1, 1100))->map(fn ($i) => [
            'type' => 'correction', 'summary' => 'Submission '.$i, 'status' => 'pending_review',
            'created_at' => $now->copy()->subMinutes($i * 7 % 1000), 'updated_at' => $now,
        ])->all());

        $rows = $this->csv(route('backend.admin.submissions.export'), $this->owner());
        $this->assertCount(1101, $rows);
        $this->assertCount(1100, array_unique(array_column(array_slice($rows, 1), 3)), 'no row twice, none missing');
        $dates = array_column(array_slice($rows, 1), 0);
        $sorted = $dates;
        rsort($sorted);
        $this->assertSame($sorted, $dates, 'newest first, as on screen');
    }

    public function test_the_subscriber_export_contains_what_the_view_shows(): void
    {
        Subscriber::forceCreate(['email' => 'active@example.org', 'token' => str_repeat('a', 40), 'confirmed_at' => now()]);
        Subscriber::forceCreate(['email' => 'waiting@example.org', 'token' => str_repeat('b', 40)]);
        Subscriber::forceCreate(['email' => 'gone@example.org', 'token' => str_repeat('c', 40), 'confirmed_at' => now(), 'unsubscribed_at' => now()]);
        $owner = $this->owner();

        $this->assertSame(['active@example.org'], array_column(array_slice($this->csv(route('backend.admin.subscribers.export', ['state' => 'active']), $owner), 1), 0));
        $this->assertSame(['waiting@example.org'], array_column(array_slice($this->csv(route('backend.admin.subscribers.export', ['state' => 'unconfirmed']), $owner), 1), 0));
        $this->assertSame(['gone@example.org'], array_column(array_slice($this->csv(route('backend.admin.subscribers.export', ['state' => 'all', 'q' => 'gone']), $owner), 1), 0));

        $html = $this->actingAs($owner)->get(route('backend.admin.subscribers', ['state' => 'all', 'q' => 'wait']))->assertOk()->getContent();
        $this->assertStringContainsString('waiting@example.org', $html);
        $this->assertStringNotContainsString('active@example.org', $html);
        $this->assertStringContainsString(e(route('backend.admin.subscribers.export').'?state=all&q=wait'), $html, 'the export link carries the filters');
    }

    public function test_the_audit_log_filters_and_records_exports_with_their_filters(): void
    {
        $owner = $this->owner();
        AdminAuditLog::forceCreate(['user_email' => 'a@example.org', 'method' => 'POST', 'route_name' => 'backend.review.publish', 'path' => '/x', 'status' => 302, 'created_at' => now()]);
        AdminAuditLog::forceCreate(['user_email' => 'b@example.org', 'method' => 'POST', 'route_name' => 'backend.admin.settings.save', 'path' => '/y', 'status' => 422, 'created_at' => now()]);

        $html = $this->actingAs($owner)->get(route('backend.admin.audit', ['result' => 'failed']))->assertOk()->getContent();
        $this->assertStringContainsString('settings.save', $html);
        $this->assertStringNotContainsString('review.publish</a>', $html);

        $rows = $this->csv(route('backend.admin.audit.export', ['user' => 'a@example.org']), $owner);
        $this->assertSame([['at', 'user', 'method', 'action', 'path', 'record', 'status']], array_slice($rows, 0, 1));
        $this->assertSame('a@example.org', $rows[1][1]);

        $logged = AdminAuditLog::where('route_name', 'backend.admin.audit.export')->sole();
        $this->assertSame('user=a@example.org', $logged->route_params['filters'], 'who took which list, and which slice of it');
    }

    public function test_the_downloads_page_has_a_view_per_list_with_drill_down(): void
    {
        foreach (['ai-risk-register' => 'Acme', 'ai-system-inventory' => 'Globex'] as $slug => $company) {
            TemplateDownloadRequest::forceCreate(['template_slug' => $slug, 'name' => 'N', 'email' => strtolower($company).'@example.org', 'company' => $company, 'terms_accepted_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        }
        $owner = $this->owner();

        $html = $this->actingAs($owner)->get(route('backend.admin.downloads'))->assertOk()->getContent();
        $this->assertStringContainsString(e(route('backend.admin.downloads', ['view' => 'requests', 'template' => 'ai-risk-register'])), $html, 'a most-requested row opens its requests');

        $html = $this->actingAs($owner)->get(route('backend.admin.downloads', ['view' => 'requests', 'template' => 'ai-risk-register']))->assertOk()->getContent();
        $this->assertStringContainsString('Acme', $html);
        $this->assertStringNotContainsString('Globex', $html);

        $rows = $this->csv(route('backend.admin.downloads.export', ['view' => 'requests', 'q' => 'globex']), $owner);
        $this->assertCount(2, $rows);
        $this->assertSame('Globex', $rows[1][4]);
    }

    public function test_a_skipped_run_neither_counts_as_the_latest_nor_as_a_failure(): void
    {
        JobRun::forceCreate(['job' => 'policy_import', 'trigger' => 'schedule', 'started_at' => now()->subMinutes(5), 'finished_at' => now(), 'exit_code' => 0, 'output' => 'ok']);
        JobRun::forceCreate(['job' => 'policy_import', 'trigger' => 'admin', 'started_at' => now()->subMinute(), 'finished_at' => now()->subMinute(), 'exit_code' => JobRun::SKIPPED, 'output' => 'Skipped: this job is already running.']);

        $this->assertTrue(JobRun::latest()['policy_import']->succeeded());
        $html = $this->actingAs($this->owner())->get(route('backend.admin.dashboard'))->assertOk()->getContent();
        $this->assertStringNotContainsString('scheduled job failed', $html);

        $html = $this->get(route('backend.admin.jobs', ['result' => 'skipped']))->assertOk()->getContent();
        $this->assertStringContainsString('>skipped<', $html);
    }

    public function test_flushing_content_leaves_rate_limits_and_the_scheduler_heartbeat_alone(): void
    {
        Cache::put('scheduler.last_tick', 'now', 600);
        Cache::put('login-throttle-key', 3, 600);
        $first = ContentCache::remember('catalog.stats', 600, fn () => 'old');

        ContentCache::flush();

        $this->assertSame('old', $first);
        $this->assertSame('new', ContentCache::remember('catalog.stats', 600, fn () => 'new'), 'content is rebuilt');
        $this->assertSame('now', Cache::get('scheduler.last_tick'));
        $this->assertSame(3, Cache::get('login-throttle-key'));
    }

    public function test_publishing_keeps_the_first_published_date_and_hidden_obligations_stay_hidden(): void
    {
        $this->artisan('policy:import');
        $policy = PolicyInstrument::published()->whereHas('obligations')->firstOrFail();
        $first = $policy->published_at;
        $hidden = $policy->obligations()->firstOrFail();
        $hidden->forceFill(['published_at' => null])->saveQuietly();
        $owner = $this->owner();

        $this->travel(2)->days();
        $this->actingAs($owner)->post(route('backend.review.publish', ['policy', $policy->slug]), ['publish' => 1])->assertRedirect();

        $this->assertEquals($first->toDateTimeString(), $policy->fresh()->published_at->toDateTimeString(), 'not re-dated');
        $this->assertNull($hidden->fresh()->published_at, 'an obligation unpublished on purpose stays so');

        $this->actingAs($owner)->post(route('backend.review.publish', ['policy', $policy->slug]), ['publish' => 0]);
        $this->assertSame(0, Obligation::where('policy_instrument_id', $policy->id)->whereNotNull('published_at')->count(), 'unpublishing hides every duty');
    }

    public function test_the_review_queue_lists_stale_records_by_the_configured_threshold(): void
    {
        $this->artisan('policy:import');
        config(['aipolicytracker.stale_after_days' => 30]);
        $policy = PolicyInstrument::firstOrFail();
        $policy->forceFill(['last_verified_at' => now()->subDays(45), 'review_status' => 'verified'])->saveQuietly();

        $owner = $this->owner();
        $html = $this->actingAs($owner)->get(route('backend.review.index', ['type' => 'policy', 'review' => 'stale']))->assertOk()->getContent();
        $this->assertStringContainsString(e($policy->title), $html);
        $this->assertStringContainsString(e(route('backend.review.export', ['type' => 'policy', 'review' => 'stale'])), $html);

        $rows = $this->csv(route('backend.review.export', ['type' => 'policy', 'review' => 'stale']), $owner);
        $this->assertSame(['slug', 'title', 'review_status', 'confidence', 'last_verified_at', 'reviewed_by', 'stale', 'published', 'source'], $rows[0]);
        $this->assertContains($policy->slug, array_column($rows, 0));
        $this->assertSame(['yes'], array_values(array_unique(array_column(array_slice($rows, 1), 6))), 'only stale records');
    }

    public function test_deleting_a_user_returns_to_the_list(): void
    {
        $owner = $this->owner();
        $member = User::factory()->create();

        $this->actingAs($owner)->delete(route('backend.admin.users.destroy', $member))->assertRedirect(route('backend.admin.users.index'));
    }

    public function test_a_new_tool_cannot_be_created_published(): void
    {
        $this->actingAs($this->owner())->post(route('backend.admin.tools.store'), [
            'title' => 'A tool', 'slug' => 'a-tool', 'type' => 'template', 'short' => 'Short.', 'status' => 'published', 'version' => '1.0',
        ])->assertSessionHasErrors('status');
        $this->assertDatabaseMissing('tools', ['slug' => 'a-tool']);
    }
}
