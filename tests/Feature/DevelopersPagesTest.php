<?php

namespace Tests\Feature;

use App\Http\Controllers\Site\DevelopersController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/** The developer and MCP pages describe what exists, and are offered to search engines. */
class DevelopersPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('policy:import');
    }

    public function test_the_developer_page_lists_real_endpoints_and_answers_the_api_questions(): void
    {
        $html = $this->get(route('developers'))->assertOk()->getContent();
        $this->assertStringNotContainsString('noindex', $html);
        $this->assertStringContainsString('"@type":"FAQPage"', $html);
        $this->assertStringContainsString('Is there an API for AI regulations?', $html);
        $registered = collect(Route::getRoutes()->getRoutes())->map(fn ($r) => '/'.$r->uri())->all();
        foreach (DevelopersController::ENDPOINTS as [$path]) {
            $this->assertContains($path, $registered, "{$path} is listed but not served");
        }
    }

    public function test_the_mcp_page_lists_the_tools_the_server_actually_has(): void
    {
        $html = $this->get(route('mcp'))->assertOk()->getContent();
        $this->assertStringContainsString('"@type":"SoftwareSourceCode"', $html);
        $this->assertStringContainsString('there is no hosted endpoint yet', $html);
        $server = File::get(base_path('agent/server.mjs'));
        foreach (DevelopersController::MCP_TOOLS as [$tool]) {
            $this->assertStringContainsString("'{$tool}'", $server, "{$tool} is listed but the server has no such tool");
        }
    }

    public function test_both_pages_are_in_the_sitemap_and_llms_txt(): void
    {
        $sitemap = $this->get(route('sitemap.section', 'static'))->assertOk()->getContent();
        $this->assertStringContainsString(route('developers'), $sitemap);
        $this->assertStringContainsString(route('mcp'), $sitemap);
        $this->get(route('llms'))->assertOk()->assertSee(route('mcp'));
    }
}
