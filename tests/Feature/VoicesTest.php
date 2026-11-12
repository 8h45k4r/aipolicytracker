<?php

namespace Tests\Feature;

use App\Services\PolicyData\PolicyDataRepository;
use App\Services\Voices\VoiceCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * AI Voices: profiles from data/voices, every one validated, sourced, pending review unless a
 * reviewer verified it; the index filters by role, a profile states its sources and review
 * status and carries no image; people are never ranked.
 */
class VoicesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('policy:import');
    }

    public function test_every_profile_is_valid_sourced_and_not_marked_verified_without_a_reviewer(): void
    {
        $this->artisan('policy:validate')->assertSuccessful();
        $voices = PolicyDataRepository::default()->voices();
        $this->assertNotEmpty($voices);
        foreach ($voices as $file => $v) {
            $this->assertNotEmpty($v['sources'], $file);
            $this->assertStringStartsWith('https://', $v['official_profile_url'], $file);
            if ($v['review_status'] === 'verified') {
                $this->assertNotEmpty($v['reviewed_by'], $file);
            }
            $this->assertArrayNotHasKey('photo', $v, 'no images of people');
        }
    }

    public function test_the_index_lists_profiles_and_filters_by_role(): void
    {
        $all = VoiceCatalog::all();
        $first = $all->first();
        $html = $this->get(route('voices.index'))->assertOk()->getContent();
        $this->assertStringContainsString('<meta name="robots" content="index', $html);
        $this->assertStringContainsString(e($first['name']), $html);
        $this->assertStringContainsString('The mix of roles', $html);
        $this->assertStringContainsString('not ranked', $html);

        $category = $first['category'];
        $filtered = $this->get(route('voices.index', ['category' => $category]))->assertOk()->getContent();
        $this->assertStringContainsString('<meta name="robots" content="noindex', $filtered);
        $other = $all->first(fn ($v) => $v['category'] !== $category);
        if ($other) {
            $list = substr($filtered, strpos($filtered, 'data-voices-list'));
            $this->assertStringNotContainsString(e($other['name']), $list);
        }
    }

    public function test_a_profile_states_its_role_sources_and_review_status_without_an_image(): void
    {
        $v = VoiceCatalog::all()->first();
        $html = $this->get(route('voices.show', $v['slug']))->assertOk()->getContent();
        $this->assertStringContainsString(e($v['role']), $html);
        $this->assertStringContainsString('href="'.e($v['official_profile_url']).'"', $html);
        $this->assertStringContainsString(e($v['sources'][0]['url']), $html);
        $this->assertStringContainsString($v['review_status'] === 'verified' ? 'Verified by' : 'Pending review.', $html);
        $this->assertStringContainsString('"@type":"Person"', $html);
        $main = substr($html, strpos($html, '<main'));
        $this->assertStringNotContainsString('<img', substr($main, 0, strpos($main, '</main>')), 'no photographs of people');
        $this->get(route('voices.show', 'no-such-person'))->assertNotFound();
    }

    public function test_profiles_are_in_the_sitemap_and_llms_txt(): void
    {
        $v = VoiceCatalog::all()->first();
        $this->get(route('sitemap.section', 'resources'))->assertOk()->assertSee(route('voices.show', $v['slug']), false);
        $this->get(route('llms'))->assertOk()->assertSee(route('voices.index'), false);
    }
}
