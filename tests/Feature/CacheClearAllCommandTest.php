<?php

namespace Tests\Feature;

use App\Support\ContentCache;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/** aip:cache-clear, run by the Maintenance workflow: clears compiled caches, never the durable cache store. */
class CacheClearAllCommandTest extends TestCase
{
    public function test_it_drops_page_content_but_keeps_durable_state_in_the_cache_store(): void
    {
        Cache::put('scheduler.last_tick', '2026-11-09T10:00:00Z', 3600);
        Cache::forever('aiid.sync.cursor', '2026-11-01');
        $page = ContentCache::remember('test-page', 600, fn () => 'old');

        $this->artisan('aip:cache-clear')->assertSuccessful();

        $this->assertSame('old', $page);
        $this->assertSame('new', ContentCache::remember('test-page', 600, fn () => 'new'), 'page content is stale after a clear');
        $this->assertSame('2026-11-09T10:00:00Z', Cache::get('scheduler.last_tick'));
        $this->assertSame('2026-11-01', Cache::get('aiid.sync.cursor'));
    }

    public function test_the_workflow_and_script_never_deploy_or_prune_outside_the_project(): void
    {
        $workflow = file_get_contents(base_path('.github/workflows/maintenance.yml'));
        $script = file_get_contents(base_path('deploy/remote-maintenance.sh'));

        $this->assertStringNotContainsString('DEPLOY_SSH_KEY', $workflow, 'no fallback to the deploy key');
        $this->assertStringNotContainsString('branches/main', $workflow);
        $this->assertStringNotContainsString('system prune', $script);
        $this->assertStringNotContainsString('--volumes', $script);
        $this->assertStringContainsString('aip-deploy.lock', $script, 'shares the deploy lock');
        $this->assertStringNotContainsString('cache:clear ', $script);
    }
}
