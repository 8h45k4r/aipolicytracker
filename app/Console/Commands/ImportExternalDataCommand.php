<?php

namespace App\Console\Commands;

use App\Models\ExternalIncident;
use App\Models\ExternalRisk;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Loads data/external/aiid_incidents.json and data/external/mit_risks.json into the
 * read-model tables (idempotent upsert; rows absent from the files are removed).
 * Runs on deploy after policy:import.
 */
class ImportExternalDataCommand extends Command
{
    protected $signature = 'external:import';

    protected $description = 'Import the AI Incident Database and MIT AI Risk Repository rows from data/external/';

    public function handle(): int
    {
        $incidents = $this->read('data/external/aiid_incidents.json');
        $risks = $this->read('data/external/mit_risks.json');

        DB::transaction(function () use ($incidents, $risks) {
            if ($incidents) {
                $ids = [];
                foreach (array_chunk($incidents['incidents'] ?? [], 250) as $chunk) {
                    $rows = array_map(fn ($i) => [
                        'incident_id' => $i['incident_id'], 'occurred_on' => $i['date'], 'year' => (int) substr($i['date'], 0, 4), 'title' => $i['title'], 'description' => $i['description'] ?: null,
                        'deployers' => json_encode($i['deployers'] ?? []), 'developers' => json_encode($i['developers'] ?? []), 'harmed' => json_encode($i['harmed'] ?? []), 'report_count' => $i['report_count'] ?? 0,
                        'mit_domain' => $i['mit_domain'] ?: null, 'mit_subdomain' => $i['mit_subdomain'] ?: null, 'entity' => $i['entity'] ?: null, 'intent' => $i['intent'] ?: null, 'timing' => $i['timing'] ?: null,
                        'sectors' => json_encode($i['sectors'] ?? []), 'countries' => json_encode($i['countries'] ?? []), 'harm_level' => $i['harm_level'] ?: null, 'snapshot_date' => $incidents['snapshot_date'] ?? null,
                        'created_at' => now(), 'updated_at' => now(),
                    ], $chunk);
                    ExternalIncident::upsert($rows, ['incident_id'], array_diff(array_keys($rows[0]), ['incident_id', 'created_at']));
                    $ids = array_merge($ids, array_column($chunk, 'incident_id'));
                }
                ExternalIncident::whereNotIn('incident_id', $ids)->delete();
            }
            if ($risks) {
                $ids = [];
                foreach (array_chunk($risks['risks'] ?? [], 250) as $chunk) {
                    $rows = array_map(fn ($r) => [
                        'ev_id' => $r['ev_id'], 'quick_ref' => $r['quick_ref'], 'paper_title' => $r['paper_title'], 'level' => $r['level'], 'risk_category' => $r['risk_category'] ?: null, 'risk_subcategory' => $r['risk_subcategory'] ?: null,
                        'description' => $r['description'] ?: null, 'entity' => $r['entity'] ?: null, 'intent' => $r['intent'] ?: null, 'timing' => $r['timing'] ?: null, 'domain' => $r['domain'], 'subdomain' => $r['subdomain'],
                        'created_at' => now(), 'updated_at' => now(),
                    ], $chunk);
                    ExternalRisk::upsert($rows, ['ev_id'], array_diff(array_keys($rows[0]), ['ev_id', 'created_at']));
                    $ids = array_merge($ids, array_column($chunk, 'ev_id'));
                }
                ExternalRisk::whereNotIn('ev_id', $ids)->delete();
            }
        });
        $this->info(sprintf('External data imported: %d incidents, %d risks.', ExternalIncident::count(), ExternalRisk::count()));

        return self::SUCCESS;
    }

    private function read(string $relative): ?array
    {
        $path = base_path($relative);
        if (! File::exists($path)) {
            $this->warn("Missing {$relative}; skipped.");

            return null;
        }

        return json_decode(File::get($path), true, 512, JSON_THROW_ON_ERROR);
    }
}
