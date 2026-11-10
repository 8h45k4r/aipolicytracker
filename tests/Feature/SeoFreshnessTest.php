<?php

namespace Tests\Feature;

use App\Models\ChangeEvent;
use App\Models\Control;
use App\Models\Jurisdiction;
use App\Models\Obligation;
use App\Models\PolicyInstrument;
use App\Models\RecordRevision;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A page's modified date (JSON-LD dateModified, sitemap lastmod) is only worth anything if
 * it moves when the record changes and not otherwise. Every deploy re-imports data/, so the
 * import has to leave an unchanged record exactly as it was.
 */
class SeoFreshnessTest extends TestCase
{
    use RefreshDatabase;

    public function test_re_importing_unchanged_data_changes_no_record(): void
    {
        $this->artisan('policy:import')->assertSuccessful();
        $snapshot = fn () => [
            PolicyInstrument::pluck('updated_at', 'slug')->map->toIso8601String()->all(),
            Obligation::pluck('updated_at', 'slug')->map->toIso8601String()->all(),
            Control::pluck('updated_at', 'slug')->map->toIso8601String()->all(),
            Jurisdiction::pluck('updated_at', 'slug')->map->toIso8601String()->all(),
            ChangeEvent::pluck('updated_at', 'slug')->map->toIso8601String()->all(),
            PolicyInstrument::pluck('published_at', 'slug')->map->toIso8601String()->all(),
        ];
        $before = $snapshot();

        $this->travel(3)->days();
        $this->artisan('policy:import')->assertSuccessful();

        $this->assertSame($before, $snapshot());
    }

    public function test_a_changed_record_does_move_its_date(): void
    {
        $this->artisan('policy:import')->assertSuccessful();
        $policy = PolicyInstrument::where('slug', 'eu-ai-act')->firstOrFail();
        $before = $policy->updated_at;

        // As if the YAML had been edited: the next import writes the file's value back,
        // which is a change from what the row holds now.
        $policy->forceFill(['summary_plain' => 'Stale text'])->saveQuietly();
        PolicyInstrument::whereKey($policy->id)->toBase()->update(['updated_at' => $before]);

        $this->travel(3)->days();
        $this->artisan('policy:import')->assertSuccessful();

        $this->assertTrue($policy->fresh()->updated_at->greaterThan($before));
        $this->assertEquals($policy->published_at, $policy->fresh()->published_at, 'first publication is kept');
    }

    private function lastmod(string $section, string $loc): ?string
    {
        $xml = $this->get(route('sitemap.section', $section))->assertOk()->getContent();

        return preg_match('#<loc>'.preg_quote($loc, '#').'</loc><lastmod>([^<]+)</lastmod>#', $xml, $m) ? $m[1] : null;
    }

    public function test_sitemap_lastmod_is_the_content_date_not_the_time_the_row_was_written(): void
    {
        $this->artisan('policy:import')->assertSuccessful();
        $policy = PolicyInstrument::with('jurisdiction')->where('slug', 'eu-ai-act')->firstOrFail();
        $obligation = Obligation::published()->where('policy_instrument_id', $policy->id)->whereNotNull('summary')->firstOrFail();
        $before = [
            'policy' => $this->lastmod('policies', $policy->url()),
            'obligation' => $this->lastmod('obligations', $obligation->url()),
            'jurisdiction' => $this->lastmod('jurisdictions', $policy->jurisdiction->url()),
        ];
        $this->assertNotContains(null, $before, 'every record entry carries a lastmod');

        // A write that changes nothing a reader sees (a bulk edit to every file, a link
        // check, an admin decision re-applied) moves updated_at on every row. It used to
        // move every lastmod with it, to the time of the deploy.
        $this->travel(3)->days();
        foreach ([PolicyInstrument::class, Obligation::class, Jurisdiction::class] as $model) {
            $model::query()->toBase()->update(['updated_at' => now()]);
        }
        $this->assertSame($before, [
            'policy' => $this->lastmod('policies', $policy->url()),
            'obligation' => $this->lastmod('obligations', $obligation->url()),
            'jurisdiction' => $this->lastmod('jurisdictions', $policy->jurisdiction->url()),
        ]);

        // A revision to one of its duties is a change to the policy page, and to the
        // country page that lists the policy, as well as to the duty's own page.
        $this->travel(1)->days();
        RecordRevision::create(['record_type' => 'obligation', 'record_slug' => $obligation->slug, 'fields' => [['field' => 'summary', 'label' => 'Summary', 'from' => null, 'to' => 'revised']], 'changed_at' => now()]);
        $now = now()->startOfSecond()->toAtomString();
        $this->assertSame($now, $this->lastmod('obligations', $obligation->url()));
        $this->assertSame($now, $this->lastmod('policies', $policy->url()));
        $this->assertSame($now, $this->lastmod('jurisdictions', $policy->jurisdiction->url()));
    }
}
