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
        $this->info('MIT domain taxonomy written: '.count($domains).' domains.');

        return self::SUCCESS;
    }
}
