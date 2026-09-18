<?php

namespace Tests\Feature;

use App\Models\Jurisdiction;
use App\Models\Obligation;
use App\Models\PolicyInstrument;
use App\Services\Social\SocialCard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Every page shared one static image, and that image had counts painted into it:
 * it read "117 jurisdictions · 182 instruments" against a corpus of 212 and 186.
 * A number inside a PNG cannot be kept true, and nothing in the system could tell
 * that it had stopped being true. On a site whose argument is that its figures can
 * be trusted, that is the worst place to carry a stale one.
 *
 * Cards are now drawn from the record. These assert that they are drawn from the
 * record, that the URL moves when the record does — a platform caches a preview
 * against its URL and will otherwise show yesterday's title forever — and that a
 * host which cannot draw them falls back to exactly the old behaviour.
 */
class SocialCardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->artisan('policy:import');
        Storage::fake(config('social.cache_disk'));
    }

    private function skipWithoutFont(): void
    {
        if (! app(SocialCard::class)->available()) {
            $this->markTestSkipped('no TrueType font on this host; the fallback path is covered separately');
        }
    }

    public function test_a_record_page_points_at_its_own_card_rather_than_the_shared_image(): void
    {
        $policy = PolicyInstrument::published()->firstOrFail();

        $html = $this->get($policy->url())->assertOk()->getContent();

        $this->assertStringContainsString(route('social.card', ['kind' => 'policy', 'slug' => $policy->slug]), $html);
        $this->assertStringNotContainsString(config('aipolicytracker.default_og_image'), $html);
        // Both networks read their own tag; neither should be left on the old image.
        $this->assertSame(2, substr_count($html, route('social.card', ['kind' => 'policy', 'slug' => $policy->slug])));
    }

    public function test_a_page_with_no_record_of_its_own_gets_the_site_card_not_the_static_file(): void
    {
        // The static file is where the stale numbers lived, so no page may quietly
        // keep using it while cards are on.
        foreach (['/', route('methodology'), route('about'), route('open-data')] as $url) {
            $html = $this->get($url)->assertOk()->getContent();
            $this->assertStringContainsString(route('social.card', ['kind' => 'site', 'slug' => 'default']), $html, "still the static image on {$url}");
        }
    }

    public function test_the_card_url_changes_when_the_record_does(): void
    {
        // This is the whole mechanism. Platforms cache a preview against its URL;
        // a card whose URL never moves keeps showing the old title after an edit.
        $policy = PolicyInstrument::published()->firstOrFail();

        $before = $this->cardUrlOn($policy->url());
        $policy->forceFill(['updated_at' => now()->addDay()])->save();
        $after = $this->cardUrlOn($policy->fresh()->url());

        $this->assertNotSame($before, $after, 'the card URL must move when the record moves');
        $this->assertStringStartsWith(route('social.card', ['kind' => 'policy', 'slug' => $policy->slug]), $after);
    }

    private function cardUrlOn(string $url): string
    {
        preg_match('#<meta property="og:image" content="([^"]+)"#', $this->get($url)->assertOk()->getContent(), $m);
        $this->assertNotEmpty($m[1] ?? '', "no og:image on {$url}");

        return $m[1];
    }

    public function test_a_card_is_a_png_at_the_size_every_platform_crops_from(): void
    {
        $this->skipWithoutFont();
        $policy = PolicyInstrument::published()->firstOrFail();

        $response = $this->get(route('social.card', ['kind' => 'policy', 'slug' => $policy->slug]))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');

        $size = getimagesizefromstring($response->getContent());
        $this->assertSame((int) config('social.width'), $size[0]);
        $this->assertSame((int) config('social.height'), $size[1]);
        $this->assertSame(IMAGETYPE_PNG, $size[2]);
    }

    public function test_each_record_gets_its_own_drawing(): void
    {
        $this->skipWithoutFont();
        $policies = PolicyInstrument::published()->orderBy('slug')->take(2)->get();
        $this->assertCount(2, $policies, 'two records are needed for this to mean anything');

        $first = $this->get(route('social.card', ['kind' => 'policy', 'slug' => $policies[0]->slug]))->assertOk()->getContent();
        $second = $this->get(route('social.card', ['kind' => 'policy', 'slug' => $policies[1]->slug]))->assertOk()->getContent();

        $this->assertNotSame($first, $second, 'two different records produced the same image');
    }

    public function test_every_kind_of_record_can_be_drawn(): void
    {
        $this->skipWithoutFont();

        $cases = [
            ['policy', PolicyInstrument::published()->firstOrFail()->slug],
            ['jurisdiction', Jurisdiction::published()->firstOrFail()->slug],
            ['obligation', Obligation::published()->firstOrFail()->slug],
            ['site', 'default'],
        ];

        foreach ($cases as [$kind, $slug]) {
            $this->get(route('social.card', compact('kind', 'slug')))
                ->assertOk()
                ->assertHeader('Content-Type', 'image/png');
        }
    }

    public function test_a_card_is_drawn_once_and_then_served_from_disk(): void
    {
        $this->skipWithoutFont();
        $policy = PolicyInstrument::published()->firstOrFail();
        $url = route('social.card', ['kind' => 'policy', 'slug' => $policy->slug]);

        $first = $this->get($url)->assertOk()->getContent();
        $files = Storage::disk(config('social.cache_disk'))->allFiles(config('social.cache_path'));
        $second = $this->get($url)->assertOk()->getContent();

        $this->assertCount(1, $files, 'one request should leave exactly one file');
        $this->assertSame($first, $second);
        $this->assertSame($files, Storage::disk(config('social.cache_disk'))->allFiles(config('social.cache_path')), 'the second request redrew the card');
    }

    public function test_a_record_that_does_not_exist_is_not_drawn(): void
    {
        $this->get(route('social.card', ['kind' => 'policy', 'slug' => 'no-such-record']))->assertNotFound();
        $this->get('/og/nonsense/whatever.png')->assertNotFound();

        // An unpublished record has no page, so it gets no card either.
        $draft = PolicyInstrument::published()->firstOrFail();
        $draft->forceFill(['published_at' => null])->save();
        $this->get(route('social.card', ['kind' => 'policy', 'slug' => $draft->slug]))->assertNotFound();
    }

    public function test_turning_cards_off_returns_the_site_to_exactly_what_it_did_before(): void
    {
        config(['social.cards' => false]);
        $policy = PolicyInstrument::published()->firstOrFail();

        $this->get(route('social.card', ['kind' => 'policy', 'slug' => $policy->slug]))
            ->assertRedirect(url(config('aipolicytracker.default_og_image')));

        $this->get('/')->assertOk()->assertSee(config('aipolicytracker.default_og_image'), false);
    }

    public function test_a_host_with_no_font_serves_the_old_image_rather_than_a_broken_one(): void
    {
        // The brand faces are not vendored, so a host without system fonts must
        // degrade to the previous behaviour instead of erroring or serving 0 bytes.
        config(['social.fonts.bold' => ['/nonexistent/none.ttf'], 'social.fonts.regular' => ['/nonexistent/none.ttf']]);

        $this->assertFalse(app(SocialCard::class)->available());

        $policy = PolicyInstrument::published()->firstOrFail();
        $this->get(route('social.card', ['kind' => 'policy', 'slug' => $policy->slug]))
            ->assertRedirect(url(config('aipolicytracker.default_og_image')));
    }

    public function test_the_operator_can_find_out_whether_cards_work_on_a_host(): void
    {
        if (app(SocialCard::class)->available()) {
            $this->artisan('social:doctor')->assertExitCode(0);
        }

        config(['social.fonts.bold' => ['/nonexistent/none.ttf'], 'social.fonts.regular' => ['/nonexistent/none.ttf']]);
        $this->artisan('social:doctor')
            ->expectsOutputToContain('NOT FOUND')
            ->assertExitCode(1);
    }

    public function test_the_markup_declares_the_size_and_type_of_what_it_links(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('<meta property="og:image:width" content="'.config('social.width').'"', $html);
        $this->assertStringContainsString('<meta property="og:image:height" content="'.config('social.height').'"', $html);
        $this->assertStringContainsString('<meta property="og:image:type" content="image/png"', $html);
    }
}
