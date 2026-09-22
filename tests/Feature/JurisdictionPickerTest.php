<?php

namespace Tests\Feature;

use App\Models\Jurisdiction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Both the comparison page and the applicability check asked a reader to find
 * their market in a flat list of 212 checkboxes. They now share one picker:
 * grouped by region, marked with how many instruments each jurisdiction holds,
 * and — with JavaScript — filterable, with removable chips for the selection.
 *
 * The tests hold it to working without JavaScript, because that is the claim the
 * component makes about itself.
 */
class JurisdictionPickerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->artisan('policy:import');
    }

    public function test_the_compare_page_groups_jurisdictions_by_region_instead_of_one_flat_list(): void
    {
        $html = $this->get('/compare')->assertOk()->getContent();

        $this->assertStringContainsString('data-jurisdiction-picker', $html);
        // Regions become collapsible headings rather than 212 undifferentiated rows.
        foreach (Jurisdiction::published()->whereNotNull('region')->distinct()->pluck('region') as $region) {
            $this->assertStringContainsString('>'.$region.' <', $html, "region {$region} should head a group");
        }
        $this->assertGreaterThan(1, substr_count($html, 'data-picker-group'), 'more than one region group');
        $this->assertSame(
            Jurisdiction::published()->count(),
            substr_count($html, 'data-picker-option'),
            'every published jurisdiction is still selectable'
        );
    }

    public function test_every_option_says_whether_the_jurisdiction_holds_any_instrument(): void
    {
        $withRecords = Jurisdiction::published()->whereHas('policyInstruments', fn ($q) => $q->published())->first();
        $without = Jurisdiction::published()->whereDoesntHave('policyInstruments', fn ($q) => $q->published())->first();
        $this->assertNotNull($withRecords);
        $this->assertNotNull($without);

        $html = $this->get('/compare')->assertOk()->getContent();

        // A reader should not pick a jurisdiction for comparison and then find it empty.
        $this->assertStringContainsString('recorded '.Str::plural('instrument', $withRecords->policyInstruments()->published()->count()), $html);
        $this->assertStringContainsString('No AI-specific instrument recorded yet', $html);
    }

    public function test_the_picker_works_without_javascript_and_the_enhancements_start_hidden(): void
    {
        $html = $this->get('/compare')->assertOk()->getContent();

        // Plain checkboxes carrying the field the controller reads.
        $this->assertStringContainsString('name="j[]"', $html);
        $this->assertStringContainsString('type="submit"', $html);
        // Script-only affordances are rendered hidden, so nothing inert is on screen.
        $this->assertMatchesRegularExpression('/data-picker-search-wrap[^>]*hidden/', $html);
        $this->assertMatchesRegularExpression('/data-picker-summary[^>]*hidden/', $html);
    }

    public function test_a_selection_is_reflected_back_and_still_drives_the_comparison(): void
    {
        $two = Jurisdiction::published()->whereHas('policyInstruments', fn ($q) => $q->published())->take(2)->get();
        $slugs = $two->pluck('slug')->all();

        $response = $this->get('/compare?'.http_build_query(['j' => $slugs]));
        $response->assertOk();
        $html = $response->getContent();

        foreach ($slugs as $slug) {
            $this->assertMatchesRegularExpression('/value="'.preg_quote($slug, '/').'"[^>]*checked/', $html, "{$slug} stays ticked");
            $this->assertStringContainsString('data-picker-remove="'.$slug.'"', $html, 'and appears as a removable chip');
        }
        // The comparison itself still renders for the chosen pair.
        $response->assertSee($two->first()->name);
        $this->assertStringContainsString('Start again', $html);
    }

    public function test_the_compare_picker_states_its_four_jurisdiction_limit(): void
    {
        $html = $this->get('/compare')->assertOk()->getContent();

        $this->assertStringContainsString('data-max="4"', $html);
        $this->assertStringContainsString('Only the first 4 are compared.', $html);
        $this->assertStringContainsString('Choose two to four jurisdictions', $html);
        $this->assertStringContainsString('Up to four.', $html);
    }

    public function test_the_applicability_check_uses_the_same_picker_and_still_screens(): void
    {
        $eu = Jurisdiction::published()->whereHas('policyInstruments', fn ($q) => $q->published())->first();

        $page = $this->get('/tools/applicability-check')->assertOk();
        $html = $page->getContent();
        $this->assertStringContainsString('data-jurisdiction-picker', $html);
        $this->assertStringContainsString('name="jurisdictions[]"', $html);
        $this->assertStringContainsString('Markets or jurisdictions', $html);
        $this->assertStringContainsString('Run screening', $html, 'the submit button keeps its name');

        // Submitting through the picker's field still produces a screening.
        $result = $this->get('/tools/applicability-check?'.http_build_query(['jurisdictions' => [$eu->slug]]));
        $result->assertOk();
        $this->assertMatchesRegularExpression('/value="'.preg_quote($eu->slug, '/').'"[^>]*checked/', $result->getContent());
        $result->assertSee('Likely relevant policies');
    }

    public function test_the_applicability_picker_still_demands_at_least_one_jurisdiction(): void
    {
        $response = $this->get('/tools/applicability-check?jurisdictions=');
        $response->assertOk()->assertSee('Select at least one jurisdiction.');
    }
}
