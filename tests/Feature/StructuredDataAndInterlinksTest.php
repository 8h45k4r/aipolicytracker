<?php

namespace Tests\Feature;

use App\Models\Jurisdiction;
use App\Models\Obligation;
use App\Models\PolicyInstrument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Two things at once, because they are the same problem seen from either side:
 * what a machine is told about a record, and whether a reader can reach the
 * pages that qualify it.
 *
 * Several surfaces shipped between #78 and #84 — the verification policy, the
 * per-jurisdiction calendar feed, the per-record Markdown context files — and no
 * record page linked to any of them.
 */
class StructuredDataAndInterlinksTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->artisan('policy:import');
    }

    /** @return list<array<string,mixed>> every JSON-LD block on the page */
    private function schemas(string $url): array
    {
        $html = $this->get($url)->assertOk()->getContent();
        preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $html, $m);
        $this->assertNotEmpty($m[1], "no structured data on {$url}");

        return array_map(fn ($json) => json_decode($json, true, 512, JSON_THROW_ON_ERROR), $m[1]);
    }

    private function ofType(array $schemas, string $type): ?array
    {
        foreach ($schemas as $s) {
            if (($s['@type'] ?? null) === $type) {
                return $s;
            }
        }

        return null;
    }

    public function test_a_binding_instrument_is_described_as_legislation_with_its_legal_force(): void
    {
        $policy = PolicyInstrument::published()->where('slug', 'eu-ai-act')->firstOrFail();
        $this->assertTrue((bool) $policy->is_binding);

        $legislation = $this->ofType($this->schemas($policy->url()), 'Legislation');
        $this->assertNotNull($legislation, 'a binding instrument should be typed as Legislation');

        $this->assertSame($policy->url(), $legislation['url']);
        $this->assertSame($policy->jurisdiction->name, $legislation['legislationJurisdiction']['name']);
        $this->assertSame($policy->issuing_body, $legislation['legislationPassedBy']['name']);
        $this->assertSame($policy->adopted_on->toDateString(), $legislation['legislationDate']);
        $this->assertSame($policy->applies_from->toDateString(), $legislation['legislationDateOfApplicability']);
        $this->assertSame($policy->official_source_url, $legislation['isBasedOn']);
        $this->assertStringStartsWith('https://schema.org/', $legislation['legislationLegalForce']);
    }

    public function test_legal_force_follows_the_record_and_is_left_unstated_when_it_would_be_a_guess(): void
    {
        $policy = PolicyInstrument::published()->where('is_binding', true)->firstOrFail();

        $cases = [
            'in_force' => 'https://schema.org/InForce',
            'partially_applicable' => 'https://schema.org/PartiallyInForce',
            'repealed' => 'https://schema.org/NotInForce',
        ];
        foreach ($cases as $status => $expected) {
            $policy->forceFill(['status' => $status])->save();
            $this->assertSame($expected, $this->ofType($this->schemas($policy->url()), 'Legislation')['legislationLegalForce'], $status);
        }

        // A proposal is not "not in force" — it is a bill. Saying nothing is the honest option.
        $policy->forceFill(['status' => 'proposed'])->save();
        $this->assertArrayNotHasKey('legislationLegalForce', $this->ofType($this->schemas($policy->url()), 'Legislation'));
    }

    public function test_a_non_binding_instrument_is_never_called_legislation(): void
    {
        // The site's whole argument is that binding and guidance differ; claiming
        // otherwise to an answer engine would be the same overclaim in machine form.
        $guidance = PolicyInstrument::published()->where('is_binding', false)->firstOrFail();

        $schemas = $this->schemas($guidance->url());
        $this->assertNull($this->ofType($schemas, 'Legislation'));
        $this->assertNotNull($this->ofType($schemas, 'WebPage'), 'it is still described as a page');
    }

    public function test_a_guide_with_ordered_steps_is_described_as_a_how_to(): void
    {
        $slug = collect(config('content.guides'))->keys()->first(fn ($k) => ! empty(config("content.guides.{$k}.steps")));
        $this->assertNotNull($slug, 'a guide with steps is needed for this to mean anything');

        $howTo = $this->ofType($this->schemas(route('guides.show', $slug)), 'HowTo');
        $this->assertNotNull($howTo);
        $this->assertSame(config("content.guides.{$slug}.h1"), $howTo['name']);
        $this->assertCount(count(config("content.guides.{$slug}.steps")), $howTo['step']);
        $this->assertSame(1, $howTo['step'][0]['position']);
        $this->assertSame(config("content.guides.{$slug}.steps.0.title"), $howTo['step'][0]['name']);
        $this->assertNotEmpty($howTo['step'][0]['text']);
    }

    public function test_every_json_ld_block_on_a_record_page_is_valid_and_typed(): void
    {
        foreach ([PolicyInstrument::published()->firstOrFail()->url(), Jurisdiction::published()->firstOrFail()->url()] as $url) {
            foreach ($this->schemas($url) as $schema) {
                $this->assertArrayHasKey('@type', $schema, "an untyped block on {$url}");
                $this->assertNotEmpty($schema['@type']);
            }
        }
    }

    public function test_a_record_links_to_the_policy_that_says_how_current_it_must_be(): void
    {
        $policy = PolicyInstrument::published()->firstOrFail();
        $jurisdiction = Jurisdiction::published()->firstOrFail();

        $this->get($policy->url())->assertOk()->assertSee(route('verification'), false);
        $this->get($jurisdiction->url())->assertOk()->assertSee(route('verification'), false);
    }

    public function test_every_record_offers_its_machine_readable_context_file(): void
    {
        $policy = PolicyInstrument::published()->firstOrFail();
        $jurisdiction = Jurisdiction::published()->firstOrFail();
        $obligation = Obligation::published()->firstOrFail();

        $this->get($policy->url())->assertOk()->assertSee(route('policies.context', $policy->slug), false);
        $this->get($jurisdiction->url())->assertOk()->assertSee(route('jurisdictions.context', $jurisdiction->slug), false);
        $this->get($obligation->url())->assertOk()->assertSee(route('obligations.context', $obligation->slug), false);

        // And the link resolves to the file rather than 404ing.
        $this->get(route('policies.context', $policy->slug))->assertOk()->assertHeader('Content-Type', 'text/markdown; charset=UTF-8');
    }

    public function test_a_jurisdiction_with_deadlines_offers_its_own_calendar_feed(): void
    {
        $jurisdiction = Jurisdiction::published()
            ->whereHas('policyInstruments', fn ($q) => $q->published()->whereHas('deadlines'))
            ->firstOrFail();

        $this->get($jurisdiction->url())->assertOk()
            ->assertSee(route('calendar.feed.jurisdiction', $jurisdiction->slug), false)
            ->assertSee(route('calendar'), false);
    }
}
