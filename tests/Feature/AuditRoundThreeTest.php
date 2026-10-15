<?php

namespace Tests\Feature;

use App\Models\AlertChannel;
use App\Models\ChannelDelivery;
use App\Models\User;
use App\Services\Alerts\WebhookDispatcher;
use App\Services\ExternalData\AiidApiClient;
use App\Support\OutboundUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Findings of the third October audit: cacheable responses, SSRF forms, retries, cursors, validation. */
class AuditRoundThreeTest extends TestCase
{
    use RefreshDatabase;

    public function test_publicly_cacheable_files_set_no_cookie(): void
    {
        $this->artisan('policy:import');
        $user = User::factory()->create();
        foreach (['/policies/eu-ai-act.json', '/policies/eu-ai-act.md', '/open-data/health.json', '/schema/policy.schema.json', '/search/suggest?q=eu', '/embed/deadlines'] as $url) {
            // Signed in, so a session cookie would be sent if the route started one.
            $response = $this->actingAs($user)->get($url)->assertOk();
            $this->assertSame([], $response->headers->getCookies(), $url.' sets no cookie on a public response');
        }
    }

    public function test_ipv6_forms_that_carry_or_reach_internal_addresses_are_refused(): void
    {
        foreach (['2002:a9fe:a9fe::', '2002:7f00:1::', '64:ff9b:1::a9fe:a9fe', '2001::1', 'fec0::1'] as $ip) {
            $this->assertFalse(OutboundUrl::isPublic($ip), $ip);
        }
        foreach (['2002:0808:0808::', '64:ff9b::808:808', '2606:4700::1111'] as $ip) {
            $this->assertTrue(OutboundUrl::isPublic($ip), $ip);
        }
    }

    public function test_a_name_that_does_not_resolve_is_retried_not_dropped(): void
    {
        $channel = AlertChannel::forceCreate(['user_id' => User::factory()->create()->id, 'kind' => 'webhook', 'enabled' => true, 'secret' => 's', 'endpoint' => 'https://hook.does-not-exist.invalid/x']);
        $delivery = ChannelDelivery::forceCreate(['alert_channel_id' => $channel->id, 'payload' => ['event' => 'alert.daily', 'title' => 'T', 'changes' => [], 'deadlines' => []], 'status' => 'pending', 'attempts' => 0]);

        $this->assertFalse(app(WebhookDispatcher::class)->attempt($delivery));

        $delivery->refresh();
        $this->assertSame('pending', $delivery->status);
        $this->assertNotNull($delivery->next_attempt_at);
    }

    public function test_the_organisation_name_is_validated(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->patch('/profile', ['name' => $user->name, 'email' => $user->email, 'organization_name' => str_repeat('x', 300)])
            ->assertSessionHasErrors('organization_name');
        $this->actingAs($user)->patch('/profile', ['name' => $user->name, 'email' => $user->email, 'organization_name' => 'Acme'])->assertSessionHasNoErrors();
        $this->assertSame('Acme', $user->fresh()->organization_name);
    }

    public function test_cached_api_page_links_carry_only_the_cached_parameters(): void
    {
        $this->artisan('policy:import');
        $first = $this->getJson('/api/v1/policies?per_page=5&tracking=abc123')->assertOk()->json('links.next');
        $this->assertStringNotContainsString('tracking', (string) $first);
        $this->assertStringContainsString('per_page=5', (string) $first);
    }

    public function test_a_capped_incremental_sync_resumes_where_it_stopped(): void
    {
        Storage::fake('local'); // the sync keeps a durable copy on this disk
        $this->artisan('policy:import');
        $row = fn (int $id, string $modified) => ['incident_id' => $id, 'date' => '2026-09-10', 'date_modified' => $modified, 'title' => 'Incident '.$id, 'description' => 'D', 'editor_notes' => '', 'reports' => []];
        $page = fn (int $from) => ['data' => ['incidents' => array_map(fn ($i) => $row($i, sprintf('2026-10-14T%02d:%02d:00Z', intdiv($i, 60) % 24, $i % 60)), range($from, $from + 99))]];
        Http::fake([AiidApiClient::ENDPOINT => Http::sequence()->push($page(1))->whenEmpty(Http::response(['data' => ['classifications' => [], 'incidents' => []]]))]);

        $this->artisan('external:sync-aiid-api', ['--since' => '2026-10-13', '--max' => 100])->assertSuccessful();

        $this->assertSame('2026-10-14T01:40:00Z', Cache::get('aiid.sync.cursor'), 'the newest change taken in a capped run');

        Http::fake([AiidApiClient::ENDPOINT => Http::response(['data' => ['incidents' => []]])]);
        $this->artisan('external:sync-aiid-api', ['--max' => 100])->assertSuccessful();
        Http::assertSent(fn ($r) => str_contains($r->body(), '2026-10-14T01:39:59Z'));
    }
}
