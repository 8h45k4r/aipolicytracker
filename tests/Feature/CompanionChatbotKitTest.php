<?php

namespace Tests\Feature;

use App\Models\Obligation;
use App\Services\Templates\TemplateBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

/**
 * The companion chatbot kit covers the duties recorded for the companion-chatbot laws
 * (Washington ESHB 2225 and New York GBL Article 47 so far), each from its official text and
 * pending review, and lists the laws whose duties are not yet recorded instead of guessing them.
 */
class CompanionChatbotKitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(TemplateBuilder::DISK);
        $this->artisan('policy:import');
    }

    public function test_the_recorded_duties_come_from_the_official_texts_and_stay_pending(): void
    {
        $duties = Obligation::published()->whereHas('policyInstrument', fn ($q) => $q->whereIn('slug', ['us-washington-eshb-2225-ai-companion-chatbots', 'us-new-york-ai-companion-safeguards']))->get();
        $this->assertCount(7, $duties);
        $this->assertSame(['pending_review'], $duties->pluck('review_status')->unique()->values()->all());
        $wa = $duties->firstWhere('slug', 'us-washington-eshb-2225-ai-disclosure');
        $this->assertSame('2027-01-01', $wa->applies_from->toDateString());
        $this->assertNotNull($duties->firstWhere('slug', 'us-new-york-gbl-1701-self-harm-protocol'));
    }

    public function test_the_kit_cites_every_duty_and_names_the_laws_not_yet_recorded(): void
    {
        $v = app(TemplateBuilder::class)->build('companion-chatbot-safety-kit')['version'];
        $book = IOFactory::load(Storage::disk(TemplateBuilder::DISK)->path($v->file('xlsx')['path']));

        $checklist = $book->getSheetByName('Checklist')->toArray(null, false, false, false);
        $this->assertCount(8, $checklist, 'a header and the seven recorded duties');

        $laws = collect($book->getSheetByName('Laws')->toArray(null, false, false, false))->filter(fn ($r) => str_contains((string) $r[0], 'SB 243'))->first();
        $this->assertNotNull($laws, 'a law with no duties recorded is still listed');
        $this->assertSame('0', (string) $laws[4]);
        $this->assertSame(203, $book->getSheetByName('Crisis referral log')->getHighestRow(), 'room for a year of entries');

        $docx = Storage::disk(TemplateBuilder::DISK)->get($v->file('docx')['path']);
        $tmp = tempnam(sys_get_temp_dir(), 'kit');
        file_put_contents($tmp, $docx);
        $zip = new \ZipArchive;
        $zip->open($tmp);
        $text = (string) $zip->getFromName('word/document.xml');
        $zip->close();
        @unlink($tmp);
        $this->assertStringContainsString('Crisis protocol to adapt', $text);
        $this->assertStringContainsString('with no duties recorded yet', $text);
        $this->assertStringContainsString('SB 243', $text);
    }
}
