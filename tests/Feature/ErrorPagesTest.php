<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * The site shipped one custom error page (404) and let the framework render
 * everything else, so a real fault showed the visitor the words "Server Error"
 * on a white page.
 *
 * The important property is not that the 500 page is pretty. It is that it
 * renders when the application is broken. A page that extends the site layout
 * pulls in navigation and a footer built from the app's own routes and data, so
 * the page meant to explain the failure can fail too. These tests hold the 500
 * and 503 pages to being self-contained.
 */
class ErrorPagesTest extends TestCase
{
    private function renderError(int $status): string
    {
        return view('errors.'.$status)->render();
    }

    public function test_the_server_error_page_renders_without_touching_the_database(): void
    {
        // The most common cause of a 500 is the database being unreachable. If the
        // error page queries anything, the visitor gets the framework's bare page
        // instead of ours, which is exactly what production was doing.
        $queries = 0;
        DB::listen(function () use (&$queries) {
            $queries++;
        });

        $html = $this->renderError(500);

        $this->assertSame(0, $queries, 'the 500 page must not query the database');
        $this->assertStringContainsString('Something broke on our side', $html);
        $this->assertStringContainsString('<!DOCTYPE html>', $html);
    }

    public function test_the_server_error_page_does_not_depend_on_the_site_layout_or_built_assets(): void
    {
        $html = $this->renderError(500);

        // Markers of the shared layout and the compiled asset bundle. If any appear,
        // the page has taken on a dependency that can fail with the app.
        $this->assertStringNotContainsString('container-site', $html);
        $this->assertStringNotContainsString('/build/assets/', $html);
        $this->assertStringNotContainsString('@vite', $html);
        $this->assertStringContainsString('<style>', $html, 'styles must be inline, not a linked bundle');

        // Its links are literal paths, not named routes, so an unbuilt route cache
        // cannot throw while rendering the page that explains the failure.
        $this->assertStringContainsString('href="/"', $html);
        $this->assertStringContainsString('href="/policies"', $html);
    }

    public function test_the_server_error_page_tells_the_reader_the_data_is_still_reachable(): void
    {
        $html = $this->renderError(500);

        // A regulatory reference that is down is only useful if the reader is told
        // the same records are published as open data.
        $this->assertStringContainsString('/api/v1', $html);
        $this->assertStringContainsString('/open-data', $html);
        $this->assertStringContainsString('not in anything you did', $html);
    }

    public function test_the_maintenance_page_is_self_contained_too(): void
    {
        $html = $this->renderError(503);

        $this->assertStringContainsString('Down for maintenance', $html);
        $this->assertStringContainsString('<!DOCTYPE html>', $html);
        $this->assertStringNotContainsString('container-site', $html);
    }

    public function test_error_pages_are_not_indexable(): void
    {
        foreach ([500, 503] as $status) {
            $this->assertStringContainsString('noindex', $this->renderError($status), "{$status} must not be indexed");
        }
    }

    public function test_a_thrown_exception_renders_the_custom_page_rather_than_the_framework_default(): void
    {
        // Proves the view is actually wired to a real failure, not just renderable.
        Route::get('/_test/boom', fn () => throw new \RuntimeException('exploded'))->middleware('web');
        config(['app.debug' => false]);

        $response = $this->get('/_test/boom');

        $response->assertStatus(500);
        $response->assertSee('Something broke on our side');
        $response->assertDontSee('Whoops, looking for something?');
    }

    public function test_the_remaining_status_pages_render_in_the_site_theme(): void
    {
        // These fire while the app is healthy, so they should look like the site.
        foreach ([403 => 'do not have access', 404 => 'Page not found', 419 => 'expired', 429 => 'Too many requests'] as $status => $needle) {
            $html = $this->renderError($status);
            $this->assertStringContainsString($needle, $html, "the {$status} page should say what happened");
            $this->assertStringContainsString('container-site', $html, "the {$status} page should use the site layout");
        }
    }

    public function test_the_rate_limit_page_points_a_scraper_at_the_bulk_downloads(): void
    {
        $html = $this->renderError(429);

        $this->assertStringContainsString('do not scrape', $html);
        $this->assertStringContainsString(route('open-data'), $html);
    }
}
