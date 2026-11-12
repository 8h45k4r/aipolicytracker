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
            // A photo only with its open licence, author and Commons source, and the file in place.
            if (isset($v['photo'])) {
                $this->assertStringStartsWith('https://commons.wikimedia.org/', $v['photo']['source_url'], $file);
                $this->assertNotEmpty($v['photo']['license'], $file);
                $this->assertDoesNotMatchRegularExpression('/\bNC\b|\bND\b|non-?commercial/i', $v['photo']['license'], $file);
                $this->assertNotEmpty($v['photo']['author'], $file);
                $this->assertFileExists(public_path('images/voices/'.$v['photo']['file']), $file);
            }
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

    public function test_a_profile_states_its_role_sources_and_review_status_with_a_credited_photo_or_initials(): void
    {
        $v = VoiceCatalog::all()->first();
        $html = $this->get(route('voices.show', $v['slug']))->assertOk()->getContent();
        $this->assertStringContainsString(e($v['role']), $html);
        $this->assertStringContainsString('href="'.e($v['official_profile_url']).'"', $html);
        $this->assertStringContainsString(e($v['sources'][0]['url']), $html);
        $this->assertStringContainsString($v['review_status'] === 'verified' ? 'Verified by' : 'Pending review.', $html);
        $this->assertStringContainsString('"@type":"Person"', $html);
        $main = substr($html, strpos($html, '<main'));
        $main = substr($main, 0, strpos($main, '</main>'));
        if (VoiceCatalog::photoUrl($v)) {
            $this->assertStringContainsString('alt="Photo of '.e($v['name']).'"', $main);
            $this->assertStringContainsString('data-photo-credit', $main);
        } else {
            $this->assertStringNotContainsString('alt="Photo of', $main, 'initials, not an image, without a licensed photo');
        }
        $this->get(route('voices.show', 'no-such-person'))->assertNotFound();
    }

    public function test_a_licensed_photo_is_shown_with_its_credit_and_described_for_search(): void
    {
        $v = VoiceCatalog::all()->first(fn ($v) => VoiceCatalog::photoUrl($v));
        if (! $v) {
            $this->markTestSkipped('no profile has a photo');
        }
        $html = $this->get(route('voices.show', $v['slug']))->assertOk()->getContent();
        $credit = substr($html, strpos($html, 'data-photo-credit'), 1500);
        $this->assertStringContainsString(e($v['photo']['license']), $credit);
        $this->assertStringContainsString(e($v['photo']['source_url']), $credit);
        $this->assertStringContainsString('"@type":"ImageObject"', $html);
        $this->assertStringContainsString('"acquireLicensePage":'.json_encode($v['photo']['source_url'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP), $html);
        $this->assertStringContainsString(asset('images/voices/'.$v['photo']['file']), $this->get(route('voices.index'))->getContent());
    }

    public function test_profiles_are_in_the_sitemap_and_llms_txt(): void
    {
        $v = VoiceCatalog::all()->first();
        $this->get(route('sitemap.section', 'resources'))->assertOk()->assertSee(route('voices.show', $v['slug']), false);
        $this->get(route('llms'))->assertOk()->assertSee(route('voices.index'), false);
    }
}
