<?php

namespace Tests\Feature;

use App\Models\ChangeEvent;
use App\Models\Control;
use App\Models\Jurisdiction;
use App\Models\Obligation;
use App\Models\PolicyInstrument;
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
}
