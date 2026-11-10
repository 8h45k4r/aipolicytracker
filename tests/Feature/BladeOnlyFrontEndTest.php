<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The account screens are Blade; the removed Inertia middleware no longer overwrites Vary. */
class BladeOnlyFrontEndTest extends TestCase
{
    use RefreshDatabase;

    public function test_account_pages_render_without_inertia(): void
    {
        foreach (['/login', '/register', '/forgot-password'] as $path) {
            $response = $this->get($path)->assertOk();
            $this->assertStringNotContainsString('X-Inertia', (string) $response->headers->get('Vary'), $path);
            $this->assertStringNotContainsString('data-page=', $response->getContent(), $path);
        }
    }

    public function test_no_react_sources_or_build_entries_remain(): void
    {
        $this->assertFileDoesNotExist(resource_path('js/app.jsx'));
        $this->assertDirectoryDoesNotExist(resource_path('js/Pages'));
        $manifest = json_decode(file_get_contents(public_path('build/manifest.json')), true);
        $this->assertSame([], array_values(array_filter(array_keys($manifest), fn ($k) => str_ends_with($k, '.jsx'))));
    }
}
