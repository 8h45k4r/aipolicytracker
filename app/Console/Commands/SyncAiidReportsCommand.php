<?php

namespace App\Console\Commands;

use App\Models\ExternalIncidentReport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Process\Process;

/**
 * Downloads the latest AI Incident Database backup (mongodump CSV export) and writes
 * data/external/aiid_reports.json with report metadata per incident: number, title, URL,
 * source domain, date, authors, language. Article text and descriptions are never copied.
 */
class SyncAiidReportsCommand extends Command
{
    protected $signature = 'external:sync-aiid-reports
        {--dir= : Use an already extracted backup directory instead of downloading}
        {--out= : Write here instead of data/external/aiid_reports.json}';

    protected $description = 'Refresh data/external/aiid_reports.json from the latest AIID backup';

    private const SNAPSHOTS_PAGE = 'https://incidentdatabase.ai/research/snapshots/';

    private const BUCKET = 'https://pub-72b2b2fc36ec423189843747af98f80e.r2.dev/';

    public function handle(): int
    {
        $dir = $this->option('dir');
        $backupName = null;
        if (! $dir) {
            $page = Http::timeout(60)->get(self::SNAPSHOTS_PAGE);
            preg_match_all('/backup-(\d{14})\.tar\.bz2/', $page->body(), $m);
            if (! $m[1]) {
                $this->error('No backup link found on the snapshots page.');

                return self::FAILURE;
            }
            rsort($m[1]);
            $backupName = "backup-{$m[1][0]}.tar.bz2";
            $tmp = storage_path('app/aiid-backup');
            File::ensureDirectoryExists($tmp);
            $archive = $tmp.'/'.$backupName;
            if (! is_file($archive)) {
                $this->line("Downloading {$backupName}…");
                $sink = fopen($archive, 'w');
                Http::timeout(900)->sink($sink)->get(self::BUCKET.$backupName);
                fclose($sink);
            }
            $process = new Process(['tar', '-xjf', $archive, '-C', $tmp, '--wildcards', '*/incidents.csv', '*/reports.csv']);
            $process->setTimeout(900)->run();
            if (! $process->isSuccessful()) {
                $this->error('Extraction failed: '.$process->getErrorOutput());

                return self::FAILURE;
            }
            $dir = collect(File::directories($tmp))->first(fn ($d) => is_file($d.'/incidents.csv')) ?? $tmp;
        }
        $incidentsCsv = $dir.'/incidents.csv';
        $reportsCsv = $dir.'/reports.csv';
        if (! is_file($incidentsCsv) || ! is_file($reportsCsv)) {
            $this->error("incidents.csv or reports.csv not found under {$dir}");

            return self::FAILURE;
        }

        try {
            $map = [];
            foreach ($this->rows($incidentsCsv) as $r) {
                foreach (json_decode((string) $r['reports'], true) ?: [] as $n) {
                    $map[(int) $n] = (int) $r['incident_id'];
                }
            }
            $reports = $this->reports($reportsCsv, $map);
        } catch (\UnexpectedValueException $e) {
            // Nothing is written from a file that does not parse: a partial list would be
            // published as if it were the whole backup.
            $this->error($e->getMessage());

            return self::FAILURE;
        }
        usort($reports, fn ($a, $b) => [$a['incident_id'], $a['report_number']] <=> [$b['incident_id'], $b['report_number']]);
        preg_match('/(\d{4})(\d{2})(\d{2})/', (string) $backupName, $d);

        File::put($this->option('out') ?: base_path('data/external/aiid_reports.json'), json_encode([
            'source' => 'AI Incident Database (Responsible AI Collaborative)', 'source_url' => 'https://incidentdatabase.ai/', 'license' => 'CC BY-SA 4.0', 'license_url' => 'https://creativecommons.org/licenses/by-sa/4.0/',
            'backup_file' => $backupName, 'snapshot_date' => $d ? "{$d[1]}-{$d[2]}-{$d[3]}" : now()->toDateString(), 'generated_at' => now()->toDateString(),
            'note' => 'Report metadata only (title, URL, source, date, authors, language). Report texts are not reproduced.', 'count' => count($reports), 'reports' => $reports,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n");
        $this->info(count($reports).' reports written for '.count(array_unique(array_column($reports, 'incident_id'))).' incidents.');

        return self::SUCCESS;
    }

    /**
     * @param  array<int,int>  $map  report number => incident id
     * @return list<array<string,mixed>>
     */
    private function reports(string $reportsCsv, array $map): array
    {
        $reports = [];
        foreach ($this->rows($reportsCsv) as $r) {
            $n = (int) (float) ($r['report_number'] ?? 0);
            if (! $n || ! isset($map[$n])) {
                continue;
            }
            $authors = str_starts_with((string) $r['authors'], '[') ? (json_decode($r['authors'], true) ?: []) : array_values(array_filter(array_map('trim', explode(',', (string) $r['authors']))));
            $reports[] = [
                'report_number' => $n, 'incident_id' => $map[$n], 'title' => mb_substr((string) $r['title'], 0, 300), 'url' => preg_match('#^https?://#i', (string) $r['url']) ? mb_substr((string) $r['url'], 0, 2048) : '',
                'source_domain' => mb_substr((string) $r['source_domain'], 0, 190), 'date_published' => substr((string) $r['date_published'], 0, 10),
                'authors' => ExternalIncidentReport::cleanAuthors($authors), 'language' => mb_substr((string) $r['language'], 0, 8),
            ];
        }

        return $reports;
    }

    /**
     * The rows of one export file, keyed by its header.
     *
     * The export is standard CSV: a quote inside a field is doubled and nothing else is
     * escaped. PHP's default also treats a backslash as an escape, and the entity lists are
     * JSON, so a name such as `jane-\"jd\"-doe` put a backslash before a quote, the
     * field ended early and the row came out longer than the header. That stopped every
     * weekly refresh. The escape character is switched off here.
     *
     * @return \Generator<int, array<string, ?string>>
     */
    private function rows(string $file): \Generator
    {
        $h = fopen($file, 'r');
        $header = fgetcsv($h, null, ',', '"', '');
        if (! is_array($header) || $header === [null]) {
            fclose($h);
            throw new \UnexpectedValueException(basename($file).' has no header row.');
        }
        $line = 1;
        while (($row = fgetcsv($h, null, ',', '"', '')) !== false) {
            $line++;
            if ($row === [null]) {
                continue;
            }
            if (count($row) !== count($header)) {
                fclose($h);
                throw new \UnexpectedValueException(sprintf('%s: record %d has %d fields, the header has %d. Nothing was written.', basename($file), $line, count($row), count($header)));
            }
            yield array_combine($header, $row);
        }
        fclose($h);
    }
}
