<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\V1\PublicApiController;
use App\Models\AlertChannel;
use App\Models\User;
use App\Services\Alerts\WebhookDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** The audit's code-quality, performance and accessibility fixes, each held in place. */
class QualityFixesTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_csp_trusts_only_nonced_scripts_in_modern_browsers(): void
    {
        $csp = $this->get('/')->assertOk()->headers->get('Content-Security-Policy');
        $this->assertMatchesRegularExpression("/script-src [^;]*'nonce-[^']+' 'strict-dynamic'/", $csp);
        $this->assertStringNotContainsString("'unsafe-inline'", explode(';', explode('script-src', $csp)[1])[0]);
    }

    public function test_every_executable_script_on_a_page_carries_the_nonce(): void
    {
        $this->artisan('policy:import');
        foreach (['/', '/jurisdictions', '/policies/eu-ai-act', '/login', '/pricing'] as $path) {
            $html = $this->get($path)->assertOk()->getContent();
            preg_match_all('/<script\b([^>]*)>/i', $html, $m);
            foreach ($m[1] as $attrs) {
                if (preg_match('/type="application\/(ld\+)?json"/', $attrs)) {
                    continue;
                }
                $this->assertStringContainsString('nonce=', $attrs, "{$path}: <script{$attrs}> has no nonce and would not run under strict-dynamic");
            }
        }
    }

    public function test_the_jurisdiction_page_does_not_query_its_own_jurisdiction_per_row(): void
    {
        $this->artisan('policy:import');
        $queries = [];
        DB::listen(function ($q) use (&$queries) {
            if (preg_match('/from "jurisdictions" where "jurisdictions"\."id" = \?/', $q->sql)) {
                $queries[] = $q->sql;
            }
        });
        $this->get(route('jurisdictions.show', 'us'))->assertOk();
        $this->assertLessThanOrEqual(2, count($queries), count($queries).' single-jurisdiction lookups');
    }

    public function test_growing_api_lists_page_and_keep_their_old_shape_by_default(): void
    {
        $this->artisan('policy:import');
        foreach (['/api/v1/enforcement', '/api/v1/implementation-measures'] as $path) {
            $all = $this->getJson($path)->assertOk()->json();
            $this->assertSame(1, $all['meta']['page']);
            $this->assertSame(PublicApiController::LIST_PER_PAGE, $all['meta']['per_page']);
            $this->assertCount(min($all['meta']['total'], PublicApiController::LIST_PER_PAGE), $all['data'], 'the default page is the whole list it always was');
            if ($all['meta']['total'] >= 2) {
                $first = $this->getJson($path.'?per_page=1')->assertOk()->json();
                $this->assertCount(1, $first['data']);
                $this->assertNotNull($first['links']['next']);
                $second = $this->getJson($first['links']['next'])->assertOk()->json();
                $this->assertSame(2, $second['meta']['page']);
                $this->assertNotEquals($first['data'][0], $second['data'][0]);
            }
            $this->getJson($path.'?per_page=99999')->assertOk()->assertJsonPath('meta.per_page', PublicApiController::LIST_PER_PAGE);
        }
    }

    public function test_the_world_map_does_not_hide_its_controls_inside_an_image_role(): void
    {
        $view = file_get_contents(resource_path('views/components/site/world-map.blade.php'));
        $this->assertStringNotContainsString('data-world-map="world-map-data" role="img"', $view);
        $this->assertStringContainsString('role="group"', $view);
    }

    public function test_foreign_keys_have_an_index(): void
    {
        foreach (['enforcement_events' => 'policy_instrument_id', 'subscriptions' => 'billing_customer_id', 'jurisdictions' => 'parent_jurisdiction_id', 'billing_checkouts' => 'user_id'] as $table => $column) {
            $leading = collect(Schema::getIndexes($table))->map(fn ($i) => $i['columns'][0] ?? null)->all();
            $this->assertContains($column, $leading, "{$table}.{$column}");
        }
    }

    public function test_a_failed_alert_delivery_never_logs_the_endpoint_address(): void
    {
        $user = User::factory()->create();
        $channel = AlertChannel::create(['user_id' => $user->id, 'kind' => 'slack', 'endpoint' => 'https://hooks.slack.com/services/T000/B000/SECRETPART', 'enabled' => true]);
        Http::fake(fn () => throw new ConnectionException('cURL error 28: timed out for https://hooks.slack.com/services/T000/B000/SECRETPART'));
        Log::spy();
        // The SSRF guard resolves the host; skip that here and test only the logging.
        $dispatcher = app(WebhookDispatcher::class);
        $delivery = $dispatcher->queue($channel, ['event' => 'alert.test']);
        $dispatcher->attempt($delivery);

        Log::shouldHaveReceived('notice')->with('alert.webhook.failed', \Mockery::on(fn ($context) => ($context['host'] ?? null) === 'hooks.slack.com'
            && ($context['kind'] ?? null) === 'slack'
            && ! str_contains(json_encode($context), 'SECRETPART')))->once();
        $this->assertStringNotContainsString('SECRETPART', (string) $delivery->fresh()->last_error);
    }
}
