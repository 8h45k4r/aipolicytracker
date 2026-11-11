<?php

namespace Tests\Feature;

use App\Models\Obligation;
use App\Services\Templates\TemplateBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Tests\TestCase;

/**
 * The California ADMT and risk assessment kit is built from the CPPA record's duties, each
 * citing its section, and its workbook computes the dates the regulations set: 15 business
 * days to act on an opt-out, 12 months before asking consent again, the 31 December 2027
 * assessment date for processing already under way, and the 1 April submission date.
 */
class CaliforniaAdmtKitTest extends TestCase
{
    use RefreshDatabase;

    private const SLUG = 'california-admt-compliance-kit';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(TemplateBuilder::DISK);
        $this->artisan('policy:import');
    }

    public function test_the_record_carries_the_duties_from_the_regulation_text_pending_review(): void
    {
        $duties = Obligation::published()->whereHas('policyInstrument', fn ($q) => $q->where('slug', 'us-california-cppa-admt-regulations'))->get();
        $this->assertCount(5, $duties);
        foreach ($duties as $o) {
            $this->assertStringStartsWith('Cal. Code Regs. tit. 11, §', $o->source_reference);
            $this->assertStringStartsWith('https://cppa.ca.gov/', $o->official_source_url);
            $this->assertSame('pending_review', $o->review_status, 'an agent never marks a record verified');
        }
        $this->assertSame('2027-01-01', $duties->firstWhere('slug', 'us-california-ccpa-admt-opt-out')->applies_from->toDateString());
    }

    public function test_the_kit_cites_every_duty_and_its_workbook_computes_the_regulation_dates(): void
    {
        $v = app(TemplateBuilder::class)->build(self::SLUG)['version'];
        $book = IOFactory::load(Storage::disk(TemplateBuilder::DISK)->path($v->file('xlsx')['path']));
        foreach (['Checklist', 'ADMT uses', 'Opt-out log', 'Risk assessments', 'Dates'] as $sheet) {
            $this->assertNotNull($book->getSheetByName($sheet), $sheet);
        }
        $checklist = $book->getSheetByName('Checklist')->toArray(null, false, false, false);
        $this->assertCount(6, $checklist, 'a header and the five recorded duties');
        $this->assertStringContainsString('§ 7221', implode('|', array_column($checklist, 1)));

        $date = fn (string $ymd) => ExcelDate::PHPToExcel(new \DateTime($ymd));
        $read = fn ($cell) => ExcelDate::excelToDateTimeObject($cell->getCalculatedValue())->format('Y-m-d');

        $optOut = $book->getSheetByName('Opt-out log');
        $optOut->getCell('A2')->setValue($date('2027-01-04'));
        $this->assertSame('2027-01-25', $read($optOut->getCell('E2')), '15 business days after receipt');
        $this->assertSame('2028-01-04', $read($optOut->getCell('H2')), '12 months before asking again');
        $optOut->getCell('D2')->setValue($date('2027-01-26'));
        $this->assertSame('Late', $optOut->getCell('F2')->getCalculatedValue());

        $ra = $book->getSheetByName('Risk assessments');
        $ra->getCell('C2')->setValue($date('2025-06-01'));
        $ra->getCell('D2')->setValue($date('2026-03-01'));
        $this->assertSame('2027-12-31', $read($ra->getCell('E2')), 'processing begun before the regulations: 31 December 2027');
        $this->assertSame('2029-03-01', $read($ra->getCell('F2')), 'reviewed at least every three years');
        $this->assertSame('2028-04-01', $read($ra->getCell('I2')), 'assessments of 2026 and 2027 are submitted by 1 April 2028');
        $ra->getCell('D3')->setValue($date('2028-05-01'));
        $this->assertSame('2029-04-01', $read($ra->getCell('I3')));

        // Formulas reach far enough down to be used as a log, and their dates read as dates.
        $this->assertStringStartsWith('=IF(A201=', (string) $optOut->getCell('E201')->getValue());
        $this->assertSame('yyyy-mm-dd', $optOut->getStyle('E2')->getNumberFormat()->getFormatCode());
        $this->assertSame('yyyy-mm-dd', $ra->getStyle('I2')->getNumberFormat()->getFormatCode());

        $docx = Storage::disk(TemplateBuilder::DISK)->get($v->file('docx')['path']);
        $zip = new \ZipArchive;
        $tmp = tempnam(sys_get_temp_dir(), 'kit');
        file_put_contents($tmp, $docx);
        $zip->open($tmp);
        $text = (string) $zip->getFromName('word/document.xml');
        $zip->close();
        @unlink($tmp);
        $this->assertStringContainsString('Pre-use Notice to adapt', $text);
        $this->assertStringContainsString('Opt-out of Automated Decisionmaking Technology', $text);
    }
}
