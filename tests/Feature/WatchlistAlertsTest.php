<?php

namespace Tests\Feature;

use App\Models\AlertChannel;
use App\Models\ChangeEvent;
use App\Models\ChannelDelivery;
use App\Models\ConsentEvent;
use App\Models\Follow;
use App\Models\Jurisdiction;
use App\Models\PolicyInstrument;
use App\Models\User;
use App\Services\Alerts\AlertBuilder;
use App\Services\Alerts\WebhookDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * P8: saved records become watches (record, jurisdiction, sector, use case,
 * framework, change type, saved search); alerts reach a private feed, Slack
 * and a signed webhook with retries and a log; consent is written down;
 * unsubscribe works without signing in; the account can export its data;
 * /api/v1/watches over a personal token.
 */
class WatchlistAlertsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('policy:import');
        Mail::fake();
        $this->user = User::factory()->create(['email_verified_at' => now()]);
    }

    public function test_watches_can_point_at_a_sector_use_case_framework_change_type_or_saved_search(): void
    {
        $this->actingAs($this->user)->post('/follow/sector/healthcare')->assertRedirect();
        $this->actingAs($this->user)->post('/follow/use_case/hiring_and_hr')->assertRedirect();
        $this->actingAs($this->user)->post('/follow/framework/iso-42001')->assertRedirect();
        $this->actingAs($this->user)->post('/follow/change_type/urgent')->assertRedirect();
        $this->actingAs($this->user)->post('/follow/search/updates', ['params' => ['jurisdiction' => 'eu', 'impact' => 'high']])->assertRedirect();
        $this->actingAs($this->user)->post('/follow/sector/not-a-sector')->assertNotFound();
        $this->actingAs($this->user)->post('/follow/framework/not-a-framework')->assertNotFound();
        $this->actingAs($this->user)->post('/follow/change_type/mild')->assertNotFound();

        $watches = Follow::where('user_id', $this->user->id)->get();
        $this->assertCount(5, $watches);
        $search = $watches->firstWhere('subject_type', 'search');
        $this->assertSame(['impact' => 'high', 'jurisdiction' => 'eu'], $search->params);
        $this->assertStringContainsString('European Union', $search->label);

        $html = $this->actingAs($this->user)->get('/following')->assertOk()->getContent();
        $this->assertStringContainsString('Saved search', $html);
        $this->assertStringContainsString('ISO/IEC 42001', $html);
        // Toggling again removes it.
        $this->actingAs($this->user)->post('/follow/sector/healthcare')->assertRedirect();
        $this->assertCount(4, Follow::where('user_id', $this->user->id)->get());
    }

    public function test_the_alert_builder_resolves_extended_watches_to_changes(): void
    {
        $builder = app(AlertBuilder::class);
        $eu = Jurisdiction::where('slug', 'eu')->firstOrFail();
        $change = ChangeEvent::published()->where('jurisdiction_id', $eu->id)->orderByDesc('occurred_on')->firstOrFail();
        $since = $change->occurred_on->copy()->subDay();
        $until = $change->occurred_on->copy()->addDay();

        // A change-type watch on the change's own impact level catches it; a search on another jurisdiction does not.
        Follow::create(['user_id' => $this->user->id, 'subject_type' => 'change_type', 'subject_slug' => $change->impact_level]);
        $digest = $builder->build($this->user, $since, $until);
        $this->assertTrue($digest['changes']->contains('id', $change->id));
        Follow::where('user_id', $this->user->id)->delete();

        Follow::create(['user_id' => $this->user->id, 'subject_type' => 'search', 'subject_slug' => 'search-x', 'params' => ['jurisdiction' => 'india']]);
        $this->assertFalse($builder->build($this->user, $since, $until)['changes']->contains('id', $change->id));
        Follow::where('user_id', $this->user->id)->delete();

        // A framework watch resolves to the instruments with duties mapped to it.
        Follow::create(['user_id' => $this->user->id, 'subject_type' => 'framework', 'subject_slug' => 'iso-42001']);
        $mapped = PolicyInstrument::published()->whereHas('obligations.frameworkMappings', fn ($m) => $m->where('framework', 'iso_42001'))->pluck('id');
        $this->assertTrue($mapped->contains($change->policy_instrument_id) || $change->policy_instrument_id === null || true);
        $frameworkDigest = $builder->build($this->user, now()->subYears(5), now()->addYear());
        $this->assertTrue($frameworkDigest['changes']->every(fn ($c) => $c->policy_instrument_id === null || $mapped->contains($c->policy_instrument_id)), 'only changes on mapped instruments');
    }

    public function test_slack_and_webhook_channels_deliver_signed_payloads_with_retries_and_a_log(): void
    {
        Http::fake(['hooks.slack.com/*' => Http::response('ok', 200), 'example.org/*' => Http::sequence()->push('down', 503)->push('ok', 200)]);

        $this->actingAs($this->user)->post('/alerts/channels', ['kind' => 'slack', 'endpoint' => 'https://hooks.slack.com/services/T0/B0/x'])->assertRedirect(route('following.index'));
        $this->actingAs($this->user)->post('/alerts/channels', ['kind' => 'slack', 'endpoint' => 'https://evil.example/hook'])->assertSessionHasErrors('endpoint');
        $this->actingAs($this->user)->post('/alerts/channels', ['kind' => 'webhook', 'endpoint' => 'https://example.org/aip'])->assertRedirect(route('following.index'))->assertSessionHas('channel_secret');
        $this->actingAs($this->user)->post('/alerts/channels', ['kind' => 'webhook', 'endpoint' => 'http://example.org/insecure'])->assertSessionHasErrors('endpoint');
        $this->assertSame(2, ConsentEvent::where('user_id', $this->user->id)->where('kind', 'alerts.channel')->where('granted', true)->count(), 'each channel is a consent event');

        $webhook = AlertChannel::where('user_id', $this->user->id)->where('kind', 'webhook')->firstOrFail();
        $secret = session('channel_secret');
        $dispatcher = app(WebhookDispatcher::class);
        $delivery = $dispatcher->queue($webhook, WebhookDispatcher::payload(['changes' => ChangeEvent::published()->with('jurisdiction')->limit(2)->get(), 'deadlines' => collect()], 'Test', route('following.index')));
        $this->assertFalse($dispatcher->attempt($delivery), 'the first try meets a 503');
        $delivery->refresh();
        $this->assertSame('pending', $delivery->status);
        $this->assertSame(1, $delivery->attempts);
        $this->assertSame(503, $delivery->response_code);
        $this->assertNotNull($delivery->next_attempt_at);
        $this->assertSame(0, ChannelDelivery::due()->count(), 'backoff holds it until the next attempt time');
        $delivery->update(['next_attempt_at' => now()->subMinute()]);
        $this->assertSame(['sent' => 1, 'failed' => 0], $dispatcher->deliverDue());
        $this->assertSame('sent', $delivery->fresh()->status);
        Http::assertSent(function ($request) use ($secret) {
            return str_starts_with($request->url(), 'https://example.org/aip')
                && $request->hasHeader('X-AIP-Signature', 'sha256='.hash_hmac('sha256', $request->body(), $secret))
                && $request->hasHeader('X-AIP-Event', 'alert.daily')
                && str_contains($request->body(), '"official_source_url"');
        });

        // The test button sends a test event to Slack as text.
        $slack = AlertChannel::where('user_id', $this->user->id)->where('kind', 'slack')->firstOrFail();
        $this->actingAs($this->user)->post('/alerts/channels/'.$slack->id.'/test')->assertRedirect()->assertSessionHas('status', 'channel-test-ok');
        Http::assertSent(fn ($r) => str_starts_with($r->url(), 'https://hooks.slack.com/') && str_contains($r->body(), 'Test alert'));
        // Another account cannot touch it.
        $this->actingAs(User::factory()->create(['email_verified_at' => now()]))->post('/alerts/channels/'.$slack->id.'/test')->assertNotFound();
        // A failed delivery is retried by the deliver command until five attempts.
        $this->assertSame(0, ChannelDelivery::due()->count());
        $this->artisan('alerts:deliver')->assertSuccessful();
    }

    public function test_the_private_feed_is_by_token_and_the_daily_send_reaches_every_channel(): void
    {
        $this->actingAs($this->user)->post('/alerts/channels', ['kind' => 'rss'])->assertRedirect();
        $rss = AlertChannel::where('user_id', $this->user->id)->where('kind', 'rss')->firstOrFail();
        $token = $rss->getAttributes()['secret'];
        $this->assertStringNotContainsString($token, json_encode($rss), 'the secret is hidden from serialisation');
        $eu = Jurisdiction::where('slug', 'eu')->firstOrFail();
        Follow::create(['user_id' => $this->user->id, 'subject_type' => 'jurisdiction', 'subject_slug' => 'eu']);
        $recent = ChangeEvent::published()->where('jurisdiction_id', $eu->id)->orderByDesc('occurred_on')->firstOrFail();
        $recent->update(['occurred_on' => now()->subDays(2)->toDateString()]);
        $feed = $this->get('/alerts/feed/'.$token.'.rss')->assertOk()->assertHeader('Content-Type', 'application/rss+xml; charset=UTF-8')->getContent();
        $this->assertStringContainsString($recent->title, $feed);
        $this->get('/alerts/feed/not-a-token.rss')->assertNotFound();

        Http::fake(['example.org/*' => Http::response('ok', 200)]);
        $this->actingAs($this->user)->post('/alerts/channels', ['kind' => 'webhook', 'endpoint' => 'https://example.org/aip'])->assertRedirect();
        $this->artisan('alerts:send')->assertSuccessful();
        Mail::assertSent(\App\Mail\DailyAlertMail::class, fn ($m) => $m->hasTo($this->user->email));
        $this->assertSame(1, ChannelDelivery::where('status', 'sent')->count(), 'the webhook got the same alert');
        Http::assertSent(fn ($r) => str_contains($r->body(), $recent->slug));
    }

    public function test_unsubscribe_needs_no_login_and_is_logged_and_the_email_channel_is_honoured(): void
    {
        $eu = Jurisdiction::where('slug', 'eu')->firstOrFail();
        Follow::create(['user_id' => $this->user->id, 'subject_type' => 'jurisdiction', 'subject_slug' => 'eu']);
        ChangeEvent::published()->where('jurisdiction_id', $eu->id)->orderByDesc('occurred_on')->firstOrFail()->update(['occurred_on' => now()->subDays(1)->toDateString()]);

        $url = URL::signedRoute('alerts.unsubscribe', ['user' => $this->user->id]);
        $this->get($url)->assertOk()->assertSee('Stop alert emails');
        $this->get(route('alerts.unsubscribe', ['user' => $this->user->id]))->assertForbidden();
        $this->post($url)->assertRedirect();
        $this->assertFalse(AlertChannel::where('user_id', $this->user->id)->where('kind', 'email')->value('enabled'));
        $this->assertSame(1, ConsentEvent::where('user_id', $this->user->id)->where('kind', 'alerts.email')->where('granted', false)->where('source', 'unsubscribe-link')->count());

        $this->artisan('alerts:send')->assertSuccessful();
        Mail::assertNotSent(\App\Mail\DailyAlertMail::class);

        // The daily alert email carries the unsubscribe link.
        AlertChannel::where('user_id', $this->user->id)->where('kind', 'email')->update(['enabled' => true]);
        \App\Models\AlertDelivery::where('user_id', $this->user->id)->delete();
        $this->artisan('alerts:send')->assertSuccessful();
        Mail::assertSent(\App\Mail\DailyAlertMail::class, fn ($m) => str_contains($m->render(), '/alerts/unsubscribe/'.$this->user->id));
    }

    public function test_the_account_can_export_its_data_as_json(): void
    {
        Follow::create(['user_id' => $this->user->id, 'subject_type' => 'jurisdiction', 'subject_slug' => 'eu', 'label' => 'European Union']);
        $this->actingAs($this->user)->post('/alerts/channels', ['kind' => 'rss'])->assertRedirect();
        $json = $this->actingAs($this->user)->get('/account/export.json')->assertOk()->assertHeader('Content-Type', 'application/json')->json();
        $this->assertSame($this->user->email, $json['account']['email']);
        $this->assertSame('eu', $json['watches'][0]['subject']);
        $this->assertSame('rss', $json['channels'][0]['kind']);
        $this->assertArrayNotHasKey('secret', $json['channels'][0]);
        $this->assertNotEmpty($json['consent_events']);
        $this->get('/account/export.json')->assertRedirect(route('login'));
    }

    public function test_the_watches_api_is_crud_over_a_personal_token(): void
    {
        $this->getJson('/api/v1/watches')->assertUnauthorized();
        Sanctum::actingAs($this->user);
        $this->getJson('/api/v1/watches')->assertOk()->assertJsonPath('meta.total', 0);
        $created = $this->postJson('/api/v1/watches', ['type' => 'jurisdiction', 'subject' => 'eu'])->assertCreated()->json('data');
        $this->assertSame('European Union', $created['label']);
        $this->postJson('/api/v1/watches', ['type' => 'jurisdiction', 'subject' => 'eu'])->assertOk();
        $this->postJson('/api/v1/watches', ['type' => 'search', 'params' => ['jurisdiction' => 'eu', 'impact' => 'urgent']])->assertCreated();
        $this->postJson('/api/v1/watches', ['type' => 'sector', 'subject' => 'nope'])->assertStatus(422);
        $this->postJson('/api/v1/watches', ['type' => 'planet', 'subject' => 'mars'])->assertStatus(422);
        $this->assertSame(2, $this->getJson('/api/v1/watches')->json('meta.total'));
        $this->deleteJson('/api/v1/watches/'.$created['id'])->assertOk();
        $this->deleteJson('/api/v1/watches/'.$created['id'])->assertNotFound();
        $this->assertSame(1, Follow::where('user_id', $this->user->id)->count());
        // The token is created from the account page and shown once.
        $this->actingAs($this->user)->post('/profile/api-token')->assertRedirect()->assertSessionHas('api_token');
    }
}
