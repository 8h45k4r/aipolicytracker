<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

/** The weekly refresh of report metadata from an AI Incident Database backup. */
class SyncAiidReportsCommandTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = storage_path('framework/testing/aiid-'.uniqid());
        File::ensureDirectoryExists($this->dir);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);
        parent::tearDown();
    }

    private function write(string $incidents, string $reports): void
    {
        File::put($this->dir.'/incidents.csv', $incidents);
        File::put($this->dir.'/reports.csv', $reports);
    }

    private const REPORTS = "_id,authors,date_published,language,report_number,source_domain,text,title,url\n"
        .'a1,"[""Jane Doe""]",2026-10-01,en,11,example.org,"Body, with a comma","First report",https://example.org/one'."\n"
        .'a2,"Ann Lee, Bo Chen",2026-10-02,en,12,example.org,Body,"Second ""quoted"" report",https://example.org/two'."\n"
        .'a3,"[""Sam Roe""]",2026-10-03,en,13,example.net,Body,"Third report",https://example.net/three'."\n"
        .'a4,"[""No Incident""]",2026-10-04,en,99,example.net,Body,"Belongs to no incident",https://example.net/four'."\n";

    public function test_a_name_with_a_backslash_before_a_quote_does_not_split_the_row(): void
    {
        // The entity lists are JSON inside a CSV field, so a nickname arrives as \"" in the
        // file. With a backslash treated as an escape, the first row parsed into more fields
        // than the header and the weekly refresh stopped on it.
        $this->write(
            "_id,incident_id,date,reports,Alleged deployer of AI system,Alleged harmed or nearly harmed parties,description,title\n"
            .'i1,635,2026-01-05,"[11,12]","[""a-company""]","[""jane-\""jd\""-doe"",""general-public""]","A description, with a comma","A title"'."\n"
            .'i2,700,2026-02-06,"[13]","[""unknown-scammer-\""richard\""""]","[""general-public""]","Another description","Another title"'."\n",
            self::REPORTS
        );
        $out = $this->dir.'/out.json';

        $this->artisan('external:sync-aiid-reports', ['--dir' => $this->dir, '--out' => $out])
            ->expectsOutputToContain('3 reports written for 2 incidents.')
            ->assertSuccessful();

        $data = json_decode((string) File::get($out), true);
        $this->assertSame(3, $data['count']);
        $this->assertSame([[11, 635], [12, 635], [13, 700]], array_map(fn ($r) => [$r['report_number'], $r['incident_id']], $data['reports']));
        $this->assertSame('Second "quoted" report', $data['reports'][1]['title']);
        $this->assertSame(['Ann Lee', 'Bo Chen'], $data['reports'][1]['authors']);
        $this->assertSame('https://example.net/three', $data['reports'][2]['url']);
        // Metadata only: the article text is never copied.
        $this->assertArrayNotHasKey('text', $data['reports'][0]);
        $this->assertStringNotContainsString('Body', (string) File::get($out));
    }

    public function test_a_file_that_does_not_parse_writes_nothing_and_says_which_record(): void
    {
        $this->write(
            "_id,incident_id,date,reports,title\n"
            .'i1,635,2026-01-05,"[11]","A title"'."\n"
            .'i2,700,2026-02-06,"[13]","Another title",an,extra'."\n",
            self::REPORTS
        );
        $out = $this->dir.'/out.json';

        $this->artisan('external:sync-aiid-reports', ['--dir' => $this->dir, '--out' => $out])
            ->expectsOutputToContain('incidents.csv: record 3 has 7 fields, the header has 5. Nothing was written.')
            ->assertFailed();

        $this->assertFileDoesNotExist($out);
    }
}
