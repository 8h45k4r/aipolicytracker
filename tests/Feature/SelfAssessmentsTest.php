<?php

namespace Tests\Feature;

use App\Models\Jurisdiction;
use App\Models\PolicyInstrument;
use App\Services\Assessments\AssessmentCatalog;
use App\Services\Templates\TemplateCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SelfAssessmentsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('policy:import');
    }

    public function test_every_place_an_assessment_is_attached_to_exists(): void
    {
        $missing = [];
        foreach (AssessmentCatalog::all() as $slug => $a) {
            foreach ($a['policies'] ?? [] as $p) {
                PolicyInstrument::where('slug', $p)->exists() || $missing[] = "{$slug}: policy {$p}";
            }
            foreach ($a['jurisdictions'] ?? [] as $j) {
                Jurisdiction::where('slug', $j)->exists() || $missing[] = "{$slug}: jurisdiction {$j}";
            }
            foreach ($a['templates'] ?? [] as $t) {
                TemplateCatalog::find($t) || $missing[] = "{$slug}: template {$t}";
            }
            foreach ($a['guides'] ?? [] as $g) {
                config("content.guides.{$g}") || $missing[] = "{$slug}: guide {$g}";
            }
            foreach ($a['audiences'] ?? [] as $au) {
                config("content.audiences.{$au}") || $missing[] = "{$slug}: audience {$au}";
            }
            foreach ($a['frameworks'] ?? [] as $f) {
                config("frameworks.{$f}") || $missing[] = "{$slug}: framework {$f}";
            }
            array_key_exists($a['type'], config('assessments.types')) || $missing[] = "{$slug}: type {$a['type']}";
            $this->assertStringStartsWith('https://self.getcertifyi.com/assessments/'.$slug.'?utm_source=aipolicytracker', $a['url']);
        }
        $this->assertSame([], $missing);
    }

    public function test_the_catalogue_lists_filters_and_discloses_who_runs_the_assessments(): void
    {
        $html = $this->get(route('assessments.index'))->assertOk()->getContent();
        $this->assertStringContainsString('Free AI self-assessments', $html);
        $this->assertStringContainsString('They run on Certifyi, a related product.', $html);
        $this->assertStringNotContainsString('Dignep', $html, 'one plain line naming the product, no company');
        $this->assertSame(AssessmentCatalog::all()->count(), substr_count($html, 'data-track="assessment_click" data-track-label=') / 2);
        $this->assertStringContainsString('"@type":"ItemList"', $html);
        $this->assertStringContainsString('"@type":"FAQPage"', $html);

        $html = $this->get(route('assessments.index', ['region' => 'Colorado']))->assertOk()->assertSee('noindex', false)->getContent();
        $this->assertStringContainsString('Colorado AI Act Readiness Assessment', $html);
        $this->assertStringNotContainsString('ISO/IEC 42001 Readiness Assessment', $html);

        $this->get(route('assessments.index', ['type' => 'nonsense', 'q' => 'fria']))->assertOk()->assertSee('Fundamental Rights Impact Assessment (FRIA)');
    }

    public function test_record_pages_offer_the_assessments_that_fit_them(): void
    {
        $html = $this->get(route('policies.show', 'eu-ai-act'))->assertOk()->assertSee('EU AI Act Readiness Assessment')->assertSee('All '.AssessmentCatalog::all()->count().' self-assessments')->getContent();
        $this->assertStringNotContainsString('data-track="certifyi_click"', $html, 'the related product is named only where the self-assessments run on it, never promoted');
        $this->assertSame(2, substr_count($html, 'data-track="assessment_click"'), 'two, not a wall of links');
        $this->get(route('frameworks.show', 'iso-42001'))->assertOk()->assertSee('ISO/IEC 42001 Readiness Assessment');
        $this->get(route('audiences.show', 'hr-and-recruitment'))->assertOk()->assertSee('AI in Hiring &amp; Employment Bias Assessment', false);
        $this->get(route('guides.show', 'colorado-ai-act-repeal-sb-26-189'))->assertOk()->assertSee('Colorado AI Act Readiness Assessment');

        $this->artisan('templates:build');
        $this->get(TemplateCatalog::url('fundamental-rights-impact-assessment'))->assertOk()->assertSee('Score yourself first');

        // A page with nothing that fits shows no block at all.
        $this->get(route('policies.show', 'nepal-national-ai-policy'))->assertOk()->assertDontSee('aria-label="Free self-assessments"', false);
    }

    /** The page body, without the site-wide header and footer. */
    private function main(string $html): string
    {
        return substr($html, strpos($html, '<main'), strpos($html, '</main>') - strpos($html, '<main'));
    }
}
