<?php

namespace Tests\Feature;

use App\Models\Jurisdiction;
use App\Models\TransitionMeasure;
use App\Services\Transition\DisplacementPolicyIndex;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\OpenApiContract;
use Tests\TestCase;

/**
 * P9: the AI economic transition tracker. Records import from data/transition
 * as drafts with every factual field null, drafts are served but never indexed
 * or scored, the index is a versioned pure function, and the API and MCP
 * surfaces carry the new entities.
 */
class TransitionTrackerTest extends TestCase
{
    use OpenApiContract;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('policy:import');
    }

    public function test_seeded_measures_import_unverified_with_nothing_invented(): void
    {
        $this->assertGreaterThanOrEqual(5, TransitionMeasure::count());
        foreach (TransitionMeasure::all() as $m) {
            // Nothing ships verified: a record is either an empty draft or, once recorded
            // from a cited source, pending a named reviewer's confirmation.
            $this->assertNotSame('verified', $m->review_status, $m->slug.' is not verified');
            $this->assertNull($m->last_verified_at, $m->slug);
            $this->assertNull($m->reviewed_by, $m->slug);
            if ($m->isDraft()) {
                $this->assertNull($m->summary, $m->slug.' draft has no summary');
                $this->assertNull($m->bill_number, $m->slug);
                $this->assertSame([], $m->sponsors, $m->slug);
                $this->assertNull($m->introduced_on, $m->slug);
                $this->assertNull($m->official_source_url, $m->slug);
                $this->assertFalse($m->isIndexable(), $m->slug);
            } else {
                $this->assertSame('pending_review', $m->review_status, $m->slug);
                $this->assertNotNull($m->official_source_url, $m->slug.' cites its official source');
                $this->assertNotNull($m->bill_number, $m->slug);
            }
        }
        $this->assertGreaterThanOrEqual(3, TransitionMeasure::all()->filter(fn ($m) => $m->isDraft())->count(), 'the unresearched measures stay drafts');
        $this->artisan('policy:validate')->assertSuccessful();
    }

    public function test_the_hub_and_landings_are_computed_and_a_draft_detail_page_is_noindex_and_honest(): void
    {
        $hub = $this->get('/ai-economic-transition')->assertOk()->getContent();
        $this->assertSame(1, preg_match_all('#<h1\b#', $hub));
        $this->assertMatchesRegularExpression('/\d+ measures recorded across \d+ jurisdictions: 0 verified against an official source by a named reviewer, \d+ recorded from a cited source and awaiting that review, and \d+ still in draft/', $hub);
        $this->assertStringContainsString('"@type":"Dataset"', $hub);
        $this->assertStringContainsString('"@type":"FAQPage"', $hub);
        $this->assertStringContainsString('name="robots" content="index', $hub);
        $this->assertStringContainsString('No jurisdiction scores yet', $hub);
        $this->assertStringContainsString('name="robots" content="noindex', $this->get('/ai-economic-transition?type=ai_tax')->assertOk()->getContent(), 'filtered views are not indexed');

        foreach (['ubi', 'ai-dividend', 'ai-tax', 'ai-layoff-disclosure-laws'] as $landing) {
            $html = $this->get('/'.$landing)->assertOk()->getContent();
            $this->assertSame(1, preg_match_all('#<h1\b#', $html));
            $this->assertStringContainsString('name="robots" content="noindex', $html, $landing.' is thin until three verified measures exist');
        }

        $m = TransitionMeasure::where('slug', 'universal-basic-income-ai-proposals')->firstOrFail();
        $this->assertTrue($m->isDraft());
        $page = $this->get($m->url())->assertOk()->getContent();
        $this->assertStringContainsString('name="robots" content="noindex', $page);
        $this->assertStringContainsString('Draft: not verified', $page);
        $this->assertStringContainsString('Nothing about it has yet been read from an official source', $page);
        $this->assertStringNotContainsString('Bill number</dt>', $page, 'an empty field is not shown as a fact');
        // A measure recorded from a cited source is a page worth indexing, but it says it is awaiting review.
        $recorded = TransitionMeasure::where('slug', 'us-ai-excise-tax-bill')->firstOrFail();
        $this->assertSame('pending_review', $recorded->review_status);
        $this->get($recorded->url())->assertOk()->assertSee('H.R. 10044')->assertSee('name="robots" content="index', false)->assertDontSee('Draft: not verified');
        $this->get('/ai-economic-transition/methodology')->assertOk()->assertSee('Four dimensions, 25 points each');
        $this->get('/ai-economic-transition/measures/nope')->assertNotFound();
    }

    public function test_the_index_is_a_versioned_pure_function_that_ignores_drafts_and_future_dates(): void
    {
        $base = ['slug' => 'x', 'measure_type' => 'layoff_disclosure', 'status' => 'in_force', 'review_status' => 'verified', 'published_at' => '2026-01-01', 'in_force_on' => '2026-01-15', 'enacted_on' => null, 'introduced_on' => null, 'last_verified_at' => null];
        $this->assertSame(0, DisplacementPolicyIndex::score([['review_status' => 'draft'] + $base], '2026-Q3')['score'], 'a draft scores nothing');
        $one = DisplacementPolicyIndex::score([$base], '2026-Q3');
        $this->assertSame(25, $one['score']);
        $this->assertSame(['disclosure' => 25, 'safety_net' => 0, 'transition_funding' => 0, 'worker_voice' => 0], $one['subscores']);
        $this->assertSame(DisplacementPolicyIndex::VERSION, $one['version']);
        $this->assertSame(0, DisplacementPolicyIndex::score([$base], '2025-Q4')['score'], 'a measure dated after the quarter does not count in it');
        $two = DisplacementPolicyIndex::score([$base, ['slug' => 'y', 'measure_type' => 'ai_dividend', 'status' => 'proposed'] + $base, ['slug' => 'z', 'measure_type' => 'ai_tax', 'status' => 'proposed'] + $base], '2026-Q3');
        $this->assertSame(25 + 8 + 8, $two['score']);
        $this->assertCount(3, $two['inputs']);
        $this->assertSame(25, DisplacementPolicyIndex::score([$base, ['slug' => 'w', 'status' => 'proposed'] + $base], '2026-Q3')['subscores']['disclosure'], 'the strongest measure in a dimension, not the sum');

        // Verifying a record makes it count, on the page and in the API.
        $m = TransitionMeasure::where('slug', 'us-new-york-warn-ai-disclosure')->firstOrFail();
        $m->update(['review_status' => 'verified', 'status' => 'in_force', 'in_force_on' => '2025-03-13', 'official_source_url' => 'https://example.gov/warn', 'summary' => 'A summary a reviewer wrote from the official text.', 'source_publisher' => 'Example agency']);
        DisplacementPolicyIndex::compute('2026-Q3');
        $latest = DisplacementPolicyIndex::latest();
        $this->assertSame(25, $latest->first()->score);
        $this->assertSame(Jurisdiction::where('slug', 'us-new-york')->value('id'), $latest->first()->jurisdiction_id);
        $this->assertStringContainsString('name="robots" content="index', $this->get($m->fresh()->url())->assertOk()->getContent(), 'a verified, sourced, summarised record is indexable');
        $this->assertStringContainsString('New York', $this->get('/ai-economic-transition')->getContent());
    }

    public function test_api_surfaces_carry_measures_indicators_and_the_index_and_match_the_contract(): void
    {
        $measures = $this->getJson('/api/v1/transition/measures')->assertOk()->json();
        $this->assertMatchesOpenApi('/transition/measures', $measures);
        $this->assertGreaterThanOrEqual(5, count($measures['data']));
        $this->assertNotSame('verified', $measures['data'][0]['review_status']);
        $this->assertContains('draft', array_column($measures['data'], 'review_status'));
        $one = $this->getJson('/api/v1/transition/measures/us-ai-excise-tax-bill')->assertOk()->json();
        $this->assertMatchesOpenApi('/transition/measures/{slug}', $one);
        $this->getJson('/api/v1/transition/measures/nope')->assertNotFound();
        $indicators = $this->getJson('/api/v1/transition/indicators')->assertOk()->json();
        $this->assertMatchesOpenApi('/transition/indicators', $indicators);
        $this->assertSame([], $indicators['data'][0]['series']);
        $index = $this->getJson('/api/v1/transition/index')->assertOk()->json();
        $this->assertMatchesOpenApi('/transition/index', $index);
        $this->assertSame(DisplacementPolicyIndex::VERSION, $index['meta']['version']);
        $this->assertStringContainsString('/ai-economic-transition', $this->get('/llms.txt')->getContent());
        $this->assertStringContainsString(route('transition.index'), $this->get('/sitemap-static.xml')->getContent());
        $this->assertStringContainsString('transition-measure', $this->get('/schema/transition-measure.schema.json')->assertOk()->getContent());
        $gaps = $this->get('/gaps')->assertOk()->getContent();
        $this->assertStringContainsString('Transition measures', $gaps);
    }
}
