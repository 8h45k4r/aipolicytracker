<?php

namespace Tests\Feature;

use App\Services\Hubs\WorldMap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorldMapTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->artisan('policy:import');
    }

    public function test_the_map_data_covers_countries_and_carries_the_eu_ai_act_to_member_states(): void
    {
        $countries = collect(WorldMap::countries())->keyBy('id');

        $this->assertGreaterThan(150, $countries->count());
        $this->assertTrue($countries->every(fn ($c) => preg_match('/^[A-Z]{2}$/', $c['id']) && isset(WorldMap::LEVELS[$c['level']])));
        foreach (array_intersect(WorldMap::EU_MEMBERS, $countries->keys()->all()) as $member) {
            $this->assertNotSame('none', $countries[$member]['level'], $member.' is covered by the EU AI Act');
        }
        $this->assertFalse($countries->has('EU'), 'the Union itself is not a country on the map');
    }

    public function test_the_map_is_on_the_jurisdictions_page_and_the_report_with_a_text_legend(): void
    {
        foreach (['/jurisdictions', '/state-of-ai-regulation'] as $path) {
            $html = $this->get($path)->assertOk()->getContent();
            $this->assertStringContainsString('data-world-map="world-map-data"', $html, $path);
            $this->assertStringContainsString('<script type="application/json" id="world-map-data">', $html, $path);
            $this->assertStringContainsString('Binding AI law in force', $html, $path);
            $this->assertMatchesRegularExpression('/role="img" aria-label="World map of AI regulation: \d+ countries/', $html, $path);
            // The legend reads before the map, so its counts are on screen while the map script waits.
            $this->assertLessThan(strpos($html, 'data-world-map="world-map-data"'), strpos($html, 'Binding AI law in force'), $path);
        }

        // On a phone the jurisdictions list comes first and the map, with its script, last.
        $this->assertStringContainsString('order-last mt-8 sm:order-none sm:mt-6', $this->get('/jurisdictions')->getContent());
        $js = file_get_contents(resource_path('js/public.js'));
        $this->assertStringContainsString("import('./world-map.js')", $js, 'the map library is a separate chunk, fetched on demand');
        $this->assertStringContainsString('IntersectionObserver', $js);
    }
}
