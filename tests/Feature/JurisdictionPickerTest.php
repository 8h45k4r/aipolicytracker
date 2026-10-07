<?php

namespace Tests\Feature;

use App\Models\Jurisdiction;
use App\Services\Applicability\ApplicabilityScreener;
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

    public function test_quick_picks_are_a_pinned_set_that_includes_the_eu_and_start_hidden(): void
    {
        $html = $this->get('/compare')->assertOk()->getContent();

        // Ranking by instrument count dropped the EU; the pinned set never does.
        $this->assertStringContainsString('data-picker-quick-pick="eu"', $html);
        foreach (['us', 'uk', 'china', 'india', 'japan', 'brazil', 'canada', 'singapore', 'south-korea'] as $slug) {
            if (Jurisdiction::published()->where('slug', $slug)->exists()) {
                $this->assertStringContainsString('data-picker-quick-pick="'.$slug.'"', $html, "{$slug} is a quick pick");
            }
        }
        // Buttons do nothing without script, so the row is revealed by it.
        $this->assertMatchesRegularExpression('/data-picker-quick hidden/', $html);
        // Every applicability-style picker carries the same quick picks.
        $this->assertStringContainsString('data-picker-quick-pick="eu"', $this->get('/tools/applicability-check')->getContent());
    }

    public function test_region_groups_start_collapsed_unless_they_hold_a_selection(): void
    {
        $html = $this->get('/tools/applicability-check')->assertOk()->getContent();
        $this->assertGreaterThan(1, substr_count($html, '<details data-picker-group'));
        $this->assertSame(0, preg_match_all('/<details data-picker-group\s+open/', $html), 'no region opens by default');

        $eu = Jurisdiction::published()->where('slug', 'eu')->firstOrFail();
        $selected = $this->get('/tools/applicability-check?'.http_build_query(['jurisdictions' => ['eu']]))->assertOk()->getContent();
        $this->assertSame(1, preg_match_all('/<details data-picker-group\s+open/', $selected), 'only the region holding the selection opens');
        $this->assertMatchesRegularExpression('/<details data-picker-group\s+open\s*>\s*<summary[^>]*>\s*<span>'.preg_quote($eu->region ?: 'Other', '/').' /', $selected);
    }

    public function test_picker_tap_targets_are_at_least_24_pixels(): void
    {
        $html = $this->get('/compare?'.http_build_query(['j' => ['eu']]))->assertOk()->getContent();

        $this->assertStringNotContainsString('chip !min-h-0', $html, 'chips keep a minimum height');
        $this->assertStringContainsString('chip !min-h-[32px]', $html);
        $this->assertMatchesRegularExpression('/type="checkbox" name="j\[\]"[^>]*class="h-5 w-5/', $html, 'checkboxes are 20px inside a 36px label');
    }

    public function test_applicability_results_open_with_a_summary_computed_from_the_screened_duties(): void
    {
        $page = $this->get('/tools/applicability-check')->assertOk()->getContent();
        $this->assertStringContainsString('action="'.route('tools.applicability').'#results"', $page, 'a submission lands on the results');
        $this->assertStringContainsString('id="results"', $page);

        $answers = ['jurisdictions' => ['eu']];
        $html = $this->get('/tools/applicability-check?'.http_build_query($answers))->assertOk()->getContent();

        $screener = app(ApplicabilityScreener::class);
        $obligations = $screener->screen($screener->normalise($answers))['obligations'];
        $summary = $screener->summarise($obligations);
        $this->assertGreaterThan(0, $summary['total']);

        // The counts are the screened rows', recomputed here independently.
        $today = now()->startOfDay();
        $dates = $obligations->map(fn ($o) => $o->applies_from ?? $o->policyInstrument->applies_from ?? $o->policyInstrument->in_force_on);
        $this->assertSame($dates->filter(fn ($d) => $d && $d->lte($today))->count(), $summary['in_force']);
        $this->assertSame($dates->filter(fn ($d) => $d && $d->gt($today))->min()?->toDateString(), $summary['next_date']?->toDateString());

        $this->assertMatchesRegularExpression('/'.$summary['total'].' dut(y|ies) may apply\s+· '.$summary['in_force'].' already in force\s+· next date:/', $html);
        // The headline comes before the register exports.
        $this->assertLessThan(strpos($html, 'Register XLSX'), strpos($html, 'data-applicability-summary'));
    }

    public function test_the_applicability_picker_still_demands_at_least_one_jurisdiction(): void
    {
        $response = $this->get('/tools/applicability-check?jurisdictions=');
        $response->assertOk()->assertSee('Select at least one jurisdiction.');
    }
}
