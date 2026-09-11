<?php

namespace App\Console\Commands;

use App\Services\ExternalData\ExternalDataset;
use App\Services\ExternalData\XlsxReader;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

/**
 * Refreshes the Domain Taxonomy of AI Risks (names and definitions) from the
 * MIT AI Risk Repository spreadsheet (CC BY 4.0). Our editorial use-case
 * mapping is preserved across refreshes.
 */
class SyncMitRiskCommand extends Command
{
    protected $signature = 'external:sync-mit-risk {--file= : Use a local .xlsx instead of downloading}';

    protected $description = 'Rebuild the MIT AI Risk Repository domain taxonomy file';

    private const SHEET = 'https://docs.google.com/spreadsheets/d/15LeHcpeuZC9txkvcaMoh3sUhkMvdMMry69xxXL46DT0/export?format=xlsx';

    public function handle(): int
    {
        $file = $this->option('file');
        if (! $file) {
            $file = storage_path('app/mit_ai_risk_repository.xlsx');
            File::put($file, Http::timeout(120)->get(self::SHEET)->body());
        }
        $reader = new XlsxReader($file);
        $tab = collect($reader->sheetNames())->first(fn ($n) => str_starts_with($n, 'Domain Taxonomy'));
        if (! $tab) {
            $this->error('Domain taxonomy tab not found.');

            return self::FAILURE;
        }
        $existing = (new ExternalDataset)->mitRisk();
        $keep = collect($existing['domains'] ?? [])->keyBy('id');
        $domains = [];
        $current = null;
        foreach ($reader->rows($tab) as $r) {
            $b = $r[1] ?? '';
            $c = $r[2] ?? '';
            $d = $r[3] ?? '';
            if ($b !== '' && $c !== '' && ctype_digit($b[0])) {
                $id = explode('.', $b)[0];
                $current = ['id' => $id, 'name' => str_replace('Human- Computer', 'Human-Computer', $c), 'description' => ctype_digit(($d[0] ?? 'x')) ? '' : $d, 'aiid_domain_label' => $keep[$id]['aiid_domain_label'] ?? '', 'use_cases' => $keep[$id]['use_cases'] ?? [], 'subdomains' => []];
                $domains[] = &$current;
                unset($current);
                $current = &$domains[count($domains) - 1];
            }
            if ($current !== null && $d !== '' && ctype_digit($d[0])) {
                $current['subdomains'][] = ['id' => $d, 'name' => $r[4] ?? '', 'description' => $r[5] ?? ''];
            }
        }
        unset($current);
        $out = array_merge($existing, ['generated_at' => now()->toDateString(), 'domains' => $domains]);
        File::put(base_path(ExternalDataset::MIT_RISK), json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n");

        // Row-level risk database (AI Risk Database v4 tab).
        $dbTab = collect($reader->sheetNames())->first(fn ($n) => str_starts_with($n, 'AI Risk Database'));
        if ($dbTab) {
            $rows = $reader->rows($dbTab);
            $header = $rows[2] ?? [];
            $idx = array_flip($header);
            $col = fn (array $r, string $name) => trim($r[$idx[$name] ?? -1] ?? '');
            $code = fn (string $v) => preg_match('/^\s*(\d+)\s*[-.]/', $v, $m) ? (int) $m[1] : null;
            $sub = fn (string $v) => preg_match('/^\s*(\d+\.\d+)/', $v, $m) ? $m[1] : null;
            $maps = ['entity' => [1 => 'Human', 2 => 'AI', 3 => 'Other', 4 => 'Not coded'], 'intent' => [1 => 'Intentional', 2 => 'Unintentional', 3 => 'Other', 4 => 'Not coded'], 'timing' => [1 => 'Pre-deployment', 2 => 'Post-deployment', 3 => 'Other', 4 => 'Not coded']];
            $risks = [];
            $papers = [];
            foreach (array_slice($rows, 3) as $r) {
                if ($col($r, 'Title') === '') {
                    continue;
                }
                if ($col($r, 'Category level') === 'Paper') {
                    $papers[$col($r, 'QuickRef')] = ['quick_ref' => $col($r, 'QuickRef'), 'title' => $col($r, 'Title'), 'paper_id' => $col($r, 'Paper_ID'), 'risks' => 0];

                    continue;
                }
                $risks[] = [
                    'ev_id' => $col($r, 'Ev_ID'), 'quick_ref' => $col($r, 'QuickRef'), 'paper_title' => mb_substr($col($r, 'Title'), 0, 200), 'level' => $col($r, 'Category level'),
                    'risk_category' => mb_substr($col($r, 'Risk category'), 0, 200), 'risk_subcategory' => mb_substr($col($r, 'Risk subcategory'), 0, 200), 'description' => mb_substr($col($r, 'Description'), 0, 600),
                    'entity' => $maps['entity'][$code($col($r, 'Entity')) ?? 0] ?? '', 'intent' => $maps['intent'][$code($col($r, 'Intent')) ?? 0] ?? '', 'timing' => $maps['timing'][$code($col($r, 'Timing')) ?? 0] ?? '',
                    'domain' => $code($col($r, 'Domain')), 'subdomain' => $sub($col($r, 'Sub-domain')),
                ];
            }
            $seen = [];
            foreach ($risks as &$rk) {
                $seen[$rk['ev_id']] = ($seen[$rk['ev_id']] ?? 0) + 1;
                if ($seen[$rk['ev_id']] > 1) {
                    $rk['ev_id'] .= '#'.$seen[$rk['ev_id']];
                }
            }
            unset($rk);
            foreach ($risks as $rk) {
                if (isset($papers[$rk['quick_ref']])) {
                    $papers[$rk['quick_ref']]['risks']++;
                }
            }
            usort($papers, fn ($a, $b) => $b['risks'] <=> $a['risks']);
            File::put(base_path(ExternalDataset::MIT_RISKS), json_encode([
                'source' => 'MIT AI Risk Repository, AI Risk Database v4 (MIT AI Risk Initiative)', 'source_url' => 'https://airisk.mit.edu/', 'license' => 'CC BY 4.0', 'license_url' => 'https://creativecommons.org/licenses/by/4.0/',
                'edition' => $existing['edition'] ?? null, 'generated_at' => now()->toDateString(), 'count' => count($risks), 'papers' => array_values($papers), 'risks' => $risks,
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            $this->info('MIT risk database written: '.count($risks).' rows, '.count($papers).' papers.');
        }
        $this->info('MIT domain taxonomy written: '.count($domains).' domains.');

        return self::SUCCESS;
    }
}
