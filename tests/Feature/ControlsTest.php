<?php

namespace Tests\Feature;

use App\Models\Control;
use App\Models\ControlFrameworkReference;
use App\Models\FrameworkMapping;
use App\Models\Obligation;
use App\Services\Completeness\CompletenessChecks;
use App\Services\PolicyData\ControlIntelligence;
use App\Services\PolicyData\PolicyDataRepository;
use App\Services\PolicyData\PolicyDataValidator;
use App\Services\PolicyData\SchemaValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The control layer: what an organisation operates to meet a duty, the evidence
 * it produces, and the join that makes "one control, many requirements" a page
 * rather than a claim.
 */
class ControlsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->artisan('policy:import');
    }

    public function test_the_corpus_validates_and_every_published_obligation_names_a_control(): void
    {
        $errors = (new PolicyDataValidator(PolicyDataRepository::default(), new SchemaValidator(PolicyDataRepository::default()->schemaDir())))->run();
        $this->assertSame([], $errors, json_encode($errors, JSON_PRETTY_PRINT));

        $this->assertGreaterThanOrEqual(20, Control::published()->count());
        $orphans = Obligation::published()->doesntHave('controls')->pluck('slug');
        $this->assertSame([], $orphans->all(), 'every obligation must name at least one control: '.$orphans->join(', '));
        $unused = Control::published()->doesntHave('obligations')->pluck('slug');
        $this->assertSame([], $unused->all(), 'a control nothing references is a control nobody needs: '.$unused->join(', '));
    }

    public function test_a_control_page_is_one_control_many_requirements(): void
    {
        $control = Control::published()->withCount('obligations')->orderByDesc('obligations_count')->firstOrFail();
        $duties = $control->obligations()->whereNotNull('obligations.published_at')->with('policyInstrument.jurisdiction')->get();

        $page = $this->get($control->url())->assertOk();
        $page->assertSee($control->title)
            ->assertSee('Which legal duties does it serve?')
            ->assertSee('What evidence shows it is operating?')
            ->assertSee('Cite this record')
            ->assertSee($control->owner_role);
        foreach ($duties as $o) {
            $page->assertSee('href="'.$o->url().'"', false);
        }
        $html = $page->getContent();
        $this->assertStringContainsString('"@type":"Dataset"', $html);
        $this->assertStringContainsString('"@type":"FAQPage"', $html);
        $this->assertStringContainsString('<link rel="alternate" type="text/markdown" href="'.route('controls.context', $control->slug).'">', $html);

        $this->get(route('controls.context', $control->slug))->assertOk()
            ->assertHeader('Content-Type', 'text/markdown; charset=UTF-8')
            ->assertHeader('Link', '<'.$control->url().'>; rel="canonical"')
            ->assertSee('## Legal duties this control serves')
            ->assertSee('## Evidence it produces');

        $this->get('/controls/no-such-control')->assertNotFound();
    }

    public function test_the_index_counts_and_filters_by_kind(): void
    {
        $this->get('/controls')->assertOk()->assertSee('One control, many legal duties')->assertSee('Duties served');
        $kind = Control::published()->value('kind');
        $this->get('/controls?kind='.$kind)->assertOk()->assertSee(Control::KINDS[$kind].' controls');
        $this->get('/controls?q=zzzznothing')->assertOk()->assertSee('noindex,follow', false);
    }

    public function test_an_obligation_page_shows_its_controls_and_a_control_reaches_it_through_the_api_and_exports(): void
    {
        $obligation = Obligation::published()->has('controls')->with('controls')->firstOrFail();
        $control = $obligation->controls->first();

        $this->get($obligation->url())->assertOk()->assertSee('Which controls meet this duty?')->assertSee('href="'.$control->url().'"', false);
        $this->get('/api/v1/obligations/'.$obligation->slug)->assertOk()->assertJsonPath('data.controls.0.slug', fn ($s) => is_string($s));
        $this->get('/api/v1/controls/'.$control->slug)->assertOk()->assertJsonPath('data.slug', $control->slug)->assertJsonPath('data.obligations.0.url', fn ($u) => str_starts_with($u, url('/obligations/')));
        $this->get('/api/v1/controls?kind='.$control->kind)->assertOk()->assertJsonFragment(['slug' => $control->slug]);

        $csv = $this->get('/open-data/controls.csv')->assertOk();
        $this->assertStringContainsString($control->slug, $csv->streamedContent());
        $this->get('/open-data/controls.ndjson')->assertOk();
        $this->get('/sitemap-controls.xml')->assertOk()->assertSee('<loc>'.$control->url().'</loc>', false);
        $this->get('/llms-full.txt')->assertOk()->assertSee('## Controls (');
        $this->get('/openapi.json')->assertOk()->assertSee('/controls/{slug}');
    }

    public function test_a_framework_page_counts_the_controls_behind_it(): void
    {
        $framework = ControlFrameworkReference::whereHas('control', fn ($q) => $q->published())->value('framework');
        $slug = config('frameworks.'.$framework.'.slug');
        $this->assertNotNull($slug, "the registry must know {$framework}");
        $count = Control::published()->whereHas('frameworkReferences', fn ($q) => $q->where('framework', $framework))->count();

        $page = $this->get('/frameworks')->assertOk();
        $page->assertSee('Controls');
        if (FrameworkMapping::where('framework', $framework)->exists()) {
            $this->get(route('frameworks.show', $slug))->assertOk()->assertSee('Controls that cite this')->assertSee((string) $count);
        }
    }

    public function test_the_framework_comparison_is_computed_from_the_mappings(): void
    {
        $page = $this->get('/frameworks/compare')->assertOk()
            ->assertSee('What can be reused between frameworks?')
            ->assertSee('ISO/IEC 42001')->assertSee('NIST AI RMF')->assertSee('OWASP LLM Top 10')->assertSee('MITRE ATLAS')
            ->assertSee('AI risk management');
        $matrix = app(ControlIntelligence::class)->relationshipMatrix();
        $this->assertGreaterThan(0, $matrix['totals']['iso_42001']);
        $this->assertGreaterThan(0, $matrix['totals']['nist_ai_rmf']);
        $this->assertSame(1, substr_count(str_replace(' ', '', $page->getContent()), '"@type":"FAQPage"'));
        $this->get('/sitemap-static.xml')->assertOk()->assertSee(route('frameworks.compare'));
    }

    public function test_a_control_can_be_corrected_screened_for_and_counted_as_missing(): void
    {
        $control = Control::published()->firstOrFail();

        // The correction form prefills the control's own fields.
        $this->get(route('contribute', ['type' => 'correction', 'subject_type' => 'control', 'subject_slug' => $control->slug]))->assertOk()->assertSee($control->title);

        // The applicability screening names the controls behind the duties it found.
        $this->get('/tools/applicability-check?jurisdictions[]=eu&role=provider&use_case=hiring_and_hr&personal_data=yes&genai=yes')->assertOk()->assertSee('Controls to build first');

        // An obligation without a control is a published, expected gap.
        $checks = collect(CompletenessChecks::all())->firstWhere('id', 'obligation-controls');
        $this->assertNotNull($checks);
        $obligation = Obligation::published()->has('controls')->firstOrFail();
        $this->assertFalse($checks['missing']($obligation));
        $obligation->controls()->detach();
        $this->assertTrue($checks['missing']($obligation->fresh()));
    }
}
