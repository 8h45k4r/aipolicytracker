<?php

namespace Tests\Feature;

use App\Models\ApplicabilityProfile;
use App\Models\Obligation;
use App\Services\Applicability\ApplicabilityScreener;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\Support\OpenApiContract;
use Tests\TestCase;

/** P8: the applicability check's obligations register as XLSX, CSV, JSON and PDF, state in the URL, nothing stored. */
class ObligationsRegisterTest extends TestCase
{
    use OpenApiContract;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('policy:import');
    }

    private function answers(): array
    {
        return ['jurisdictions' => ['eu'], 'role' => 'deployer', 'use_case' => 'hiring_and_hr'];
    }

    public function test_the_register_exports_in_four_formats_from_the_screen_and_stores_nothing(): void
    {
        $screener = app(ApplicabilityScreener::class);
        $expected = $screener->screen($screener->normalise($this->answers()))['obligations']->count();
        $this->assertGreaterThan(0, $expected);
        $q = http_build_query($this->answers());

        $xlsx = $this->get('/tools/applicability-check/register.xlsx?'.$q)->assertOk()->assertHeader('Content-Disposition', 'attachment; filename="obligations-register.xlsx"')->getContent();
        $path = tempnam(sys_get_temp_dir(), 'reg').'.xlsx';
        file_put_contents($path, $xlsx);
        $ss = IOFactory::load($path);
        $register = $ss->getSheetByName('Register');
        $this->assertSame($expected, $register->getHighestDataRow() - 1);
        $this->assertNotEmpty($register->getDataValidationCollection(), 'status dropdown');
        $this->assertNotNull($ss->getSheetByName('README'));
        $this->assertNotNull($ss->getSheetByName('Answers'));
        $ss->disconnectWorksheets();
        unlink($path);

        $csv = $this->get('/tools/applicability-check/register.csv?'.$q)->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8')->getContent();
        $this->assertSame($expected + 1, count(array_filter(explode("\n", trim($csv)))));
        $this->assertStringContainsString('Source reference', $csv);

        $json = $this->get('/tools/applicability-check/register.json?'.$q)->assertOk()->json();
        $this->assertCount($expected, $json['data']);
        $this->assertStringStartsWith(url('/obligations/'), $json['data'][0]['url']);
        $this->assertSame($this->answers()['jurisdictions'], $json['meta']['answers']['jurisdictions']);
        $this->assertStringContainsString('register.xlsx', $json['meta']['downloads']['xlsx']);

        $pdf = $this->get('/tools/applicability-check/register.pdf?'.$q)->assertOk()->assertHeader('Content-Type', 'application/pdf')->getContent();
        $this->assertStringStartsWith('%PDF-', $pdf);

        $this->get('/tools/applicability-check/register.docx?'.$q)->assertNotFound();
        $this->get('/tools/applicability-check/register.csv')->assertNotFound();
        $this->assertSame(0, ApplicabilityProfile::count(), 'nothing is stored by an export');
        $this->assertStringContainsString('register.xlsx', $this->get('/tools/applicability-check?'.$q)->assertOk()->getContent(), 'the check page offers the export');
    }

    public function test_the_api_builds_the_register_and_matches_its_contract(): void
    {
        $json = $this->getJson('/api/v1/applicability/register?'.http_build_query($this->answers()))->assertOk()->json();
        $this->assertMatchesOpenApi('/applicability/register', $json);
        $this->assertNotEmpty($json['data']);
        $slug = $json['data'][0]['slug'];
        $this->assertTrue(Obligation::where('slug', $slug)->exists());
        $this->getJson('/api/v1/applicability/register')->assertStatus(422);
    }
}
