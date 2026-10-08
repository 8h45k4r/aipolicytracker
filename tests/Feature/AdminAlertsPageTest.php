<?php

namespace Tests\Feature;

use App\Enums\AdminRole;
use App\Models\AdminAuditLog;
use App\Models\AlertChannel;
use App\Models\AlertDelivery;
use App\Models\ApplicabilityProfile;
use App\Models\ChannelDelivery;
use App\Models\ConsentEvent;
use App\Models\Follow;
use App\Models\JobRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Admin → Alerts: watches, channels, deliveries with retry, consent. */
class AdminAlertsPageTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        config(['aipolicytracker.admin_emails' => ['owner@example.org']]);

        return User::factory()->create(['email' => 'owner@example.org']);
    }

    private function channel(string $kind = 'webhook', array $attrs = []): AlertChannel
    {
        return AlertChannel::forceCreate($attrs + [
            'user_id' => User::factory()->create()->id, 'kind' => $kind, 'enabled' => true, 'secret' => 'channel-secret',
            'endpoint' => $kind === 'slack' ? 'https://hooks.slack.com/services/T0/B0/very-secret-token' : 'https://example.org/aip/hook',
        ]);
    }

    private function delivery(AlertChannel $channel, array $attrs = []): ChannelDelivery
    {
        return ChannelDelivery::forceCreate($attrs + ['alert_channel_id' => $channel->id, 'payload' => ['event' => 'alert.daily', 'title' => 'Test', 'changes' => [], 'deadlines' => []], 'status' => 'pending', 'attempts' => 0]);
    }

    /** @return list<array<int,string>> */
    private function csv(string $url, User $as): array
    {
        $body = $this->actingAs($as)->get($url)->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8')->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $body);

        return array_map('str_getcsv', array_values(array_filter(explode("\n", trim(substr($body, 3))))));
    }

    public function test_reading_needs_audience_view_and_retrying_needs_subscribers_manage(): void
    {
        $owner = $this->owner();
        $member = User::factory()->create();
        $reviewer = User::factory()->create(['admin_role' => AdminRole::Reviewer]);
        $analyst = User::factory()->create(['admin_role' => AdminRole::Analyst]);
        $delivery = $this->delivery($this->channel(), ['status' => 'failed', 'attempts' => 5, 'last_error' => 'HTTP 500']);

        $this->actingAs($member)->get(route('backend.admin.alerts.index'))->assertRedirect('/');
        $this->actingAs($reviewer)->get(route('backend.admin.alerts.index'))->assertForbidden();
        $this->actingAs($reviewer)->get(route('backend.admin.alerts.deliveries.export'))->assertForbidden();

        $html = $this->actingAs($analyst)->get(route('backend.admin.alerts.index'))->assertOk()->getContent();
        $this->assertStringContainsString('HTTP 500', $html);
        $this->assertStringNotContainsString(route('backend.admin.alerts.deliveries.retry', $delivery), $html, 'a reader is not offered the action');
        $this->actingAs($analyst)->post(route('backend.admin.alerts.deliveries.retry', $delivery))->assertForbidden();
        $this->actingAs($analyst)->post(route('backend.admin.alerts.deliveries.retry.many'), ['ids' => [$delivery->id]])->assertForbidden();

        $html = $this->actingAs($owner)->get(route('backend.admin.alerts.index'))->assertOk()->getContent();
        $this->assertStringContainsString(route('backend.admin.alerts.deliveries.retry', $delivery), $html);
        $this->assertStringNotContainsString('onsubmit=', $html);
        $this->assertStringContainsString('<caption class="sr-only">Slack and webhook deliveries</caption>', $html);
    }

    public function test_the_cards_count_watches_profiles_channels_runs_and_todays_deliveries(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        foreach ([[$a, 'policy', 'eu-ai-act'], [$a, 'jurisdiction', 'eu'], [$b, 'policy', 'colorado-ai-act'], [$b, 'framework', 'iso-42001']] as [$u, $type, $slug]) {
            Follow::create(['user_id' => $u->id, 'subject_type' => $type, 'subject_slug' => $slug]);
        }
        ApplicabilityProfile::forceCreate(['user_id' => $a->id, 'name' => 'Hiring tool', 'answers' => ['jurisdictions' => ['eu']]]);
        $hook = $this->channel('webhook');
        $this->channel('slack', ['enabled' => false]);
        $this->channel('rss', ['endpoint' => null]);
        $this->delivery($hook, ['status' => 'sent', 'sent_at' => now(), 'attempts' => 1]);
        $this->delivery($hook, ['status' => 'sent', 'sent_at' => now()->subDays(2), 'attempts' => 1]);
        $this->delivery($hook, ['status' => 'failed', 'attempts' => 5, 'last_error' => 'HTTP 410']);
        AlertDelivery::forceCreate(['user_id' => $a->id, 'sent_on' => today(), 'window_start' => now()->subDay(), 'window_end' => now()]);
        AlertDelivery::forceCreate(['user_id' => $b->id, 'sent_on' => today()->subDay(), 'window_start' => now()->subDays(2), 'window_end' => now()->subDay()]);
        JobRun::forceCreate(['job' => 'alerts', 'trigger' => 'schedule', 'started_at' => now()->subHours(3), 'finished_at' => now()->subHours(3), 'exit_code' => 0, 'output' => 'ok']);

        $response = $this->actingAs($this->owner())->get(route('backend.admin.alerts.index'))->assertOk();
        $cards = $response->viewData('cards');
        $this->assertSame(2, $cards['accounts']);
        $this->assertSame(['policy' => 2, 'framework' => 1, 'jurisdiction' => 1], collect($cards['watches'])->map(fn ($n) => (int) $n)->sortKeys()->sortDesc()->all());
        $this->assertSame(1, $cards['profiles']);
        $this->assertSame(['enabled' => 1, 'total' => 1], $cards['channels']['webhook']);
        $this->assertSame(['enabled' => 0, 'total' => 1], $cards['channels']['slack']);
        $this->assertSame(1, $cards['emails_today']);
        $this->assertSame(1, $cards['channel_sent_today']);
        $this->assertSame(1, $cards['failed_week']);
        $this->assertSame('schedule', $cards['last_send']->trigger);
        $response->assertSee('data-channel-kind="slack">0 / 1', false)->assertSee('succeeded · schedule');
    }

    public function test_deliveries_filter_by_status_and_export_without_formulas_or_full_endpoints(): void
    {
        $owner = $this->owner();
        $slack = $this->channel('slack');
        $this->delivery($slack, ['status' => 'sent', 'sent_at' => now(), 'attempts' => 1]);
        $this->delivery($slack, ['status' => 'failed', 'attempts' => 5, 'last_error' => '=cmd|/c calc']);

        $html = $this->actingAs($owner)->get(route('backend.admin.alerts.index', ['status' => 'failed']))->assertOk()->getContent();
        $this->assertStringContainsString('=cmd|/c calc', $html);
        $this->assertSame(1, substr_count($html, 'name="ids[]"'), 'one row, the failed one');
        $this->assertStringNotContainsString('very-secret-token', $html, 'a Slack webhook URL is a credential');

        $rows = $this->csv(route('backend.admin.alerts.deliveries.export', ['status' => 'failed']), $owner);
        $this->assertSame(['id', 'created_at', 'email', 'channel', 'endpoint', 'event', 'status', 'attempts', 'response_code', 'last_error', 'next_attempt_at', 'sent_at'], $rows[0]);
        $this->assertCount(2, $rows);
        $this->assertSame("'=cmd|/c calc", $rows[1][9], 'a formula-looking cell is neutralised');
        $this->assertSame($slack->user->email, $rows[1][2]);
        $this->assertStringNotContainsString('very-secret-token', implode(',', $rows[1]));
        $this->assertTrue(AdminAuditLog::where('route_name', 'backend.admin.alerts.deliveries.export')->exists(), 'an export is audited');
    }

    public function test_retry_now_runs_the_dispatcher_attempt_for_one_or_many(): void
    {
        Http::fake(['example.org/aip/*' => Http::response('ok', 200), 'example.org/down/*' => Http::response('nope', 503)]);
        $owner = $this->owner();
        $good = $this->channel('webhook');
        $bad = $this->channel('webhook', ['endpoint' => 'https://example.org/down/hook']);
        $failed = $this->delivery($good, ['status' => 'failed', 'attempts' => 5, 'last_error' => 'HTTP 500']);

        $this->actingAs($owner)->from(route('backend.admin.alerts.index'))->post(route('backend.admin.alerts.deliveries.retry', $failed))
            ->assertRedirect(route('backend.admin.alerts.index'))->assertSessionHas('success');
        $failed->refresh();
        $this->assertSame('sent', $failed->status);
        $this->assertSame(6, $failed->attempts);
        $this->assertSame(200, $failed->response_code);
        Http::assertSent(fn ($r) => $r->url() === 'https://example.org/aip/hook' && $r->hasHeader('X-AIP-Signature', 'sha256='.hash_hmac('sha256', $r->body(), 'channel-secret')) && $r->hasHeader('X-AIP-Delivery', (string) $failed->id));
        $this->assertTrue(AdminAuditLog::where('route_name', 'backend.admin.alerts.deliveries.retry')->exists());

        // Sending again is refused, not repeated.
        $this->actingAs($owner)->post(route('backend.admin.alerts.deliveries.retry', $failed))->assertSessionHas('error');

        $pending = $this->delivery($good, ['next_attempt_at' => now()->addHour(), 'attempts' => 1]);
        $down = $this->delivery($bad, ['attempts' => 1]);
        $this->actingAs($owner)->post(route('backend.admin.alerts.deliveries.retry.many'), ['ids' => [$pending->id, $down->id, $failed->id]])
            ->assertSessionHas('error', '1 delivery sent, 1 failed again. 1 already sent or missing, not retried.');
        $this->assertSame('sent', $pending->fresh()->status);
        $down->refresh();
        $this->assertSame('pending', $down->status, 'a failure still follows the backoff');
        $this->assertSame(2, $down->attempts);
        $this->assertSame(503, $down->response_code);
        $this->assertNotNull($down->next_attempt_at);
        Http::assertSentCount(3);

        $this->actingAs($owner)->post(route('backend.admin.alerts.deliveries.retry.many'), ['ids' => range(1, 26)])->assertSessionHasErrors('ids');
    }

    public function test_consent_events_filter_by_kind(): void
    {
        $user = User::factory()->create(['email' => 'consent@example.org']);
        ConsentEvent::record($user, 'alerts.email', false, 'unsubscribe-link');
        ConsentEvent::record($user, 'alerts.channel', true, 'account', 'slack');

        $owner = $this->owner();
        $html = $this->actingAs($owner)->get(route('backend.admin.alerts.index', ['consent' => 'alerts.email']))->assertOk()->getContent();
        $this->assertStringContainsString('unsubscribe-link', $html);
        $this->assertStringContainsString('withdrawn', $html);
        $this->assertStringNotContainsString('>alerts.channel</td>', $html);

        $html = $this->actingAs($owner)->get(route('backend.admin.alerts.index', ['consent' => 'bogus']))->assertOk()->getContent();
        $this->assertStringContainsString('>alerts.channel</td>', $html, 'an unknown kind shows everything');
    }

    public function test_the_dashboard_raises_failed_deliveries_to_those_who_can_retry_them(): void
    {
        $this->delivery($this->channel(), ['status' => 'failed', 'attempts' => 5, 'last_error' => 'HTTP 500']);
        $this->delivery($this->channel(), ['status' => 'failed', 'attempts' => 5, 'last_error' => 'HTTP 500', 'updated_at' => now()->subDays(9)]);

        $html = $this->actingAs($this->owner())->get(route('backend.admin.dashboard'))->assertOk()->getContent();
        $this->assertStringContainsString('1 Slack or webhook alert failed in the last 7 days', $html);
        $this->assertStringContainsString(e(route('backend.admin.alerts.index', ['status' => 'failed'])), $html);

        $analyst = User::factory()->create(['admin_role' => AdminRole::Analyst]);
        $this->assertStringNotContainsString('webhook alert failed', $this->actingAs($analyst)->get(route('backend.admin.dashboard'))->assertOk()->getContent());
    }
}
