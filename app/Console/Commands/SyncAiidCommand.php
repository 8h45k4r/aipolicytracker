<?php

namespace App\Console\Commands;

use App\Services\ExternalData\ExternalDataset;
use App\Services\ExternalData\XlsxReader;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

/**
 * Refreshes data/external/aiid_summary.json from the AI Incident Database's
 * weekly Excel export (CC BY-SA 4.0). Only aggregate counts and incident
 * metadata (id, date, title, domain, countries) are kept; report texts are
 * never stored. Run weekly by .github/workflows/refresh-external-data.yml.
 */
class SyncAiidCommand extends Command
{
    protected $signature = 'external:sync-aiid {--file= : Use a local .xlsx instead of downloading}';

    protected $description = 'Rebuild the AI Incident Database summary from the latest weekly export';

    private const SNAPSHOTS_PAGE = 'https://incidentdatabase.ai/research/snapshots/';

    private const BUCKET = 'https://pub-72b2b2fc36ec423189843747af98f80e.r2.dev/';

    public function handle(): int
    {
        $file = $this->option('file');
        $exportName = $file ? basename($file) : null;
        if (! $file) {
            $page = Http::withUserAgent('aipolicytracker.org external-data sync (+https://aipolicytracker.org)')->get(self::SNAPSHOTS_PAGE);
            preg_match_all('/AIID_Excel_Export-(\d{8})\.xlsx/', $page->body(), $m);
            if (! $m[1]) {
                $this->error('No Excel export link found on the snapshots page.');

                return self::FAILURE;
            }
            rsort($m[1]);
            $exportName = "AIID_Excel_Export-{$m[1][0]}.xlsx";
            $file = storage_path("app/{$exportName}");
            File::put($file, Http::withUserAgent('aipolicytracker.org external-data sync')->timeout(120)->get(self::BUCKET.$exportName)->throw()->body());
        }
        preg_match('/(\d{4})(\d{2})(\d{2})/', $exportName, $d);
        $snapshotDate = $d ? "{$d[1]}-{$d[2]}-{$d[3]}" : now()->toDateString();

        $reader = new XlsxReader($file);
        $rows = $reader->rows('Incidents');
        $header = $rows[2] ?? [];
        $idx = array_flip($header);
        $col = fn (array $r, string $name) => $r[$idx[$name] ?? -1] ?? '';
        $list = function (string $v): array {
            $v = trim($v);
            if (str_starts_with($v, '[')) {
                $decoded = json_decode($v, true);
                if (is_array($decoded)) {
                    return array_values(array_filter(array_map(fn ($x) => trim((string) $x), $decoded)));
                }
            }

            return array_values(array_filter(array_map('trim', preg_split('/[;|]/', $v))));
        };

        $years = $domains = $sectors = $countries = $harm = [];
        $domainByYear = [];
        $latest = [];
        $incidents = [];
        foreach (array_slice($rows, 3) as $r) {
            $date = XlsxReader::excelDate($col($r, 'date'));
            if ($date === '') {
                continue;
            }
            $year = substr($date, 0, 4);
            $years[$year] = ($years[$year] ?? 0) + 1;
            $domain = trim($col($r, 'Risk Domain'));
            if ($domain !== '') {
                $domains[$domain] = ($domains[$domain] ?? 0) + 1;
                $domainByYear[$year][$domain] = ($domainByYear[$year][$domain] ?? 0) + 1;
            }
            foreach ($list($col($r, 'Sector of Deployment')) as $s) {
                $s = strtolower($s);
                $sectors[$s] = ($sectors[$s] ?? 0) + 1;
            }
            $cc = $list($col($r, 'Country Code'));
            foreach ($cc as $c) {
                $c = strtoupper($c);
                $countries[$c] = ($countries[$c] ?? 0) + 1;
            }
            $hl = trim($col($r, 'AI Harm Level'));
            if ($hl !== '') {
                $harm[$hl] = ($harm[$hl] ?? 0) + 1;
            }
            $latest[] = ['id' => (int) $col($r, 'Incident ID'), 'date' => $date, 'title' => mb_substr($col($r, 'title'), 0, 160), 'domain' => $domain, 'countries' => array_slice($cc, 0, 3)];
            $incidents[] = [
                'incident_id' => (int) $col($r, 'Incident ID'), 'date' => $date, 'title' => mb_substr($col($r, 'title'), 0, 200), 'description' => mb_substr($col($r, 'description'), 0, 500),
                'deployers' => array_slice($list($col($r, 'deployer')), 0, 5), 'developers' => array_slice($list($col($r, 'developer')), 0, 5), 'harmed' => array_slice($list($col($r, 'harmed')), 0, 5), 'report_count' => (int) $col($r, 'report_count'),
                'mit_domain' => $domain, 'mit_subdomain' => mb_substr(trim($col($r, 'Risk Subdomain')), 0, 120), 'entity' => trim($col($r, 'Responsible Entity')), 'intent' => trim($col($r, 'Intent')), 'timing' => trim($col($r, 'Timing')),
                'sectors' => array_slice(array_map('strtolower', $list($col($r, 'Sector of Deployment'))), 0, 5), 'countries' => array_slice(array_map('strtoupper', $cc), 0, 5), 'harm_level' => $hl,
            ];
        }
        ksort($years);
        arsort($domains);
        arsort($sectors);
        arsort($countries);
        arsort($harm);
        usort($latest, fn ($a, $b) => [$b['date'], $b['id']] <=> [$a['date'], $a['id']]);
        $domainByYear = array_filter($domainByYear, fn ($y) => $y >= '2016', ARRAY_FILTER_USE_KEY);
        ksort($domainByYear);

        $existing = (new ExternalDataset)->aiid();
        $out = [
            'source' => 'AI Incident Database (Responsible AI Collaborative)',
            'source_url' => 'https://incidentdatabase.ai/',
            'export_file' => $exportName,
            'snapshot_date' => $snapshotDate,
            'generated_at' => now()->toDateString(),
            'license' => 'CC BY-SA 4.0',
            'license_url' => 'https://creativecommons.org/licenses/by-sa/4.0/',
            'terms_url' => 'https://incidentdatabase.ai/terms-of-use/',
            'citation' => $existing['citation'] ?? 'McGregor, S. (2021). Preventing Repeated Real World AI Failures by Cataloging Incidents: The AI Incident Database. Proceedings of the AAAI Conference on Artificial Intelligence (IAAI-21).',
            'totals' => ['incidents' => array_sum($years), 'classified_mit' => array_sum($domains), 'with_country' => array_sum($countries)],
            'incidents_per_year' => $years,
            'by_mit_domain' => $domains,
            'by_sector' => array_slice($sectors, 0, 12, true),
            'by_country' => array_slice($countries, 0, 20, true),
            'by_harm_level' => $harm,
            'domain_by_year' => $domainByYear,
            'latest' => array_slice($latest, 0, 30),
        ];
        File::put(base_path(ExternalDataset::AIID), json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n");
        usort($incidents, fn ($a, $b) => [$b['date'], $b['incident_id']] <=> [$a['date'], $a['incident_id']]);
        File::put(base_path(ExternalDataset::AIID_INCIDENTS), json_encode([
            'source' => $out['source'], 'source_url' => $out['source_url'], 'license' => $out['license'], 'license_url' => $out['license_url'], 'snapshot_date' => $snapshotDate, 'export_file' => $exportName, 'generated_at' => now()->toDateString(), 'count' => count($incidents), 'incidents' => $incidents,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $this->info("AIID summary written: {$out['totals']['incidents']} incidents from {$exportName}.");

        return self::SUCCESS;
    }
}
