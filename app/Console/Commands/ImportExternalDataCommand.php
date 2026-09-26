<?php

namespace App\Console\Commands;

use App\Models\ExternalIncident;
use App\Models\ExternalIncidentReport;
use App\Models\ExternalRisk;
use App\Services\ExternalData\IncidentEnrichment;
use App\Services\ExternalData\RecordSlugs;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

/**
 * Loads data/external/aiid_incidents.json and data/external/mit_risks.json into the
 * read-model tables (idempotent upsert; rows absent from the files are removed unless
 * the live API sync added them). Runs on deploy after policy:import.
 */
class ImportExternalDataCommand extends Command
{
    protected $signature = 'external:import';

    protected $description = 'Import the AI Incident Database and MIT AI Risk Repository rows from data/external/';

    public function handle(): int
    {
        $incidents = $this->read('data/external/aiid_incidents.json');
        $risks = $this->read('data/external/mit_risks.json');
        $reports = $this->read('data/external/aiid_reports.json');
        // Local snapshot written by the live API sync (persistent disk): newer rows win over the repository file.
        [$incidents, $reports] = $this->mergeLocalSnapshot($incidents, $reports);

        DB::transaction(function () use ($incidents, $risks, $reports) {
            if ($incidents) {
                $ids = [];
                $unique = [];
                // Rows refreshed by the live API sync after this file's snapshot keep their newer state.
                $snapshot = $incidents['snapshot_date'] ?? null;
                $fresh = $snapshot ? ExternalIncident::whereNotNull('synced_at')->whereDate('synced_at', '>=', $snapshot)->pluck('incident_id')->flip() : collect();
                foreach ($incidents['incidents'] ?? [] as $i) {
                    $ids[] = $i['incident_id'];
                    if (isset($fresh[$i['incident_id']])) {
                        continue;
                    }
                    $unique[$i['incident_id']] = $i;
                }
                foreach (array_chunk(array_values($unique), 250) as $chunk) {
                    $rows = array_map(fn ($i) => [
                        'incident_id' => $i['incident_id'], 'occurred_on' => $i['date'], 'year' => (int) substr($i['date'], 0, 4), 'title' => $i['title'], 'description' => $i['description'] ?: null,
                        'deployers' => json_encode($i['deployers'] ?? []), 'developers' => json_encode($i['developers'] ?? []), 'harmed' => json_encode($i['harmed'] ?? []), 'report_count' => $i['report_count'] ?? 0,
                        'mit_domain' => $i['mit_domain'] ?: null, 'mit_subdomain' => $i['mit_subdomain'] ?: null, 'entity' => $i['entity'] ?: null, 'intent' => $i['intent'] ?: null, 'timing' => $i['timing'] ?: null,
                        'sectors' => json_encode($i['sectors'] ?? []), 'countries' => json_encode($i['countries'] ?? []), 'harm_level' => $i['harm_level'] ?: null, 'snapshot_date' => $incidents['snapshot_date'] ?? null,
                        'editor_notes' => ($i['editor_notes'] ?? '') ?: null, 'entities' => isset($i['entities']) ? json_encode($i['entities']) : null, 'implicated_systems' => isset($i['implicated_systems']) ? json_encode($i['implicated_systems']) : null,
                        'similar_incidents' => isset($i['similar_incidents']) ? json_encode($i['similar_incidents']) : null, 'modified_at' => $i['modified_at'] ?? null,
                        'created_at' => now(), 'updated_at' => now(),
                    ], $chunk);
                    ExternalIncident::upsert($rows, ['incident_id'], array_diff(array_keys($rows[0]), ['incident_id', 'created_at']));
                }
                // Rows added by the live sync since the snapshot are kept; everything else absent from the file goes.
                ExternalIncident::whereNotIn('incident_id', $ids)->whereNull('synced_at')->delete();
            }
            if ($reports) {
                $known = ExternalIncident::pluck('incident_id')->flip();
                $ids = [];
                $unique = [];
                foreach ($reports['reports'] ?? [] as $r) {
                    if (isset($known[$r['incident_id']])) {
                        $unique[$r['report_number']] = $r;
                    }
                }
                foreach (array_chunk(array_values($unique), 500) as $chunk) {
                    $rows = array_map(fn ($r) => [
                        'report_number' => $r['report_number'], 'incident_id' => $r['incident_id'], 'title' => $r['title'] ?: '(untitled)', 'url' => $r['url'],
                        'source_domain' => $r['source_domain'] ?: null, 'date_published' => $r['date_published'] ?: null, 'authors' => json_encode($r['authors'] ?? []), 'language' => $r['language'] ?: null,
                        'created_at' => now(), 'updated_at' => now(),
                    ], $chunk);
                    ExternalIncidentReport::upsert($rows, ['report_number'], array_diff(array_keys($rows[0]), ['report_number', 'created_at']));
                    $ids = array_merge($ids, array_column($chunk, 'report_number'));
                }
                ExternalIncidentReport::whereNotIn('report_number', $ids)->whereNull('synced_at')->delete();
            }
            if ($risks) {
                $ids = [];
                $seen = [];
                $unique = [];
                foreach ($risks['risks'] ?? [] as $r) {
                    $key = $r['ev_id'];
                    $seen[$key] = ($seen[$key] ?? 0) + 1;
                    if ($seen[$key] > 1) {
                        $r['ev_id'] = $key.'#'.$seen[$key];
                    }
                    $unique[$r['ev_id']] = $r;
                }
                foreach (array_chunk(array_values($unique), 250) as $chunk) {
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
        // New records get a readable address; existing ones keep theirs.
        $slugged = RecordSlugs::assignIncidents() + RecordSlugs::assignRisks();
        // What this site adds to a record: harm domain, related laws, policy
        // angle, sensitivity. Fills only what is empty; overrides always win.
        $enriched = IncidentEnrichment::apply();
        $this->info(sprintf('External data imported: %d incidents, %d reports, %d risks (%d new addresses, %d enriched).', ExternalIncident::count(), ExternalIncidentReport::count(), ExternalRisk::count(), $slugged, $enriched));

        return self::SUCCESS;
    }

    /** @return array{0: ?array, 1: ?array} */
    private function mergeLocalSnapshot(?array $incidents, ?array $reports): array
    {
        $disk = Storage::disk('local');
        $liveIncidents = $disk->exists(SyncAiidApiCommand::LOCAL_INCIDENTS) ? (json_decode($disk->get(SyncAiidApiCommand::LOCAL_INCIDENTS), true) ?: null) : null;
        $liveReports = $disk->exists(SyncAiidApiCommand::LOCAL_REPORTS) ? (json_decode($disk->get(SyncAiidApiCommand::LOCAL_REPORTS), true) ?: null) : null;
        if ($liveIncidents) {
            $byId = [];
            foreach ($incidents['incidents'] ?? [] as $i) {
                $byId[(int) $i['incident_id']] = $i;
            }
            $added = 0;
            foreach ($liveIncidents['incidents'] ?? [] as $i) {
                $old = $byId[(int) $i['incident_id']] ?? null;
                if (! $old || (($i['modified_at'] ?? '') >= ($old['modified_at'] ?? ''))) {
                    $byId[(int) $i['incident_id']] = $i;
                    $added++;
                }
            }
            $incidents = ($incidents ?: ['snapshot_date' => $liveIncidents['api_synced_at'] ?? null]) + [];
            $incidents['incidents'] = array_values($byId);
            $this->line("Local live snapshot merged: {$added} incidents from ".($liveIncidents['api_synced_at'] ?? 'unknown time').'.');
        }
        if ($liveReports) {
            $byNo = [];
            foreach ($reports['reports'] ?? [] as $r) {
                $byNo[(int) $r['report_number']] = $r;
            }
            foreach ($liveReports['reports'] ?? [] as $r) {
                $byNo[(int) $r['report_number']] = $r;
            }
            $reports = ($reports ?: []) + [];
            $reports['reports'] = array_values($byNo);
        }

        return [$incidents, $reports];
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
