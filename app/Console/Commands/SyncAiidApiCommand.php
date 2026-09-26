<?php

namespace App\Console\Commands;

use App\Models\ExternalIncident;
use App\Models\ExternalIncidentReport;
use App\Services\ExternalData\AiidApiClient;
use App\Services\ExternalData\ExternalDataset;
use App\Services\ExternalData\RecordSlugs;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Live sync from the AI Incident Database GraphQL API into the read-model tables.
 * By default it pulls only records modified since the last sync (a day of overlap),
 * so it is cheap enough to run every few hours from the cron trigger. With --full
 * it walks the whole database; with --write-json it also merges the rows into the
 * reviewed JSON files under data/external/. Every run also merges the rows into a local
 * snapshot on the private disk, which external:import reads after the repository files,
 * so the site keeps its last good data when the API or a database is lost. Stored fields are metadata AIID publishes under CC BY-SA 4.0; report texts
 * are never fetched.
 */
class SyncAiidApiCommand extends Command
{
    protected $signature = 'external:sync-aiid-api
        {--since= : Only records modified after this date or time (ISO 8601)}
        {--full : Walk every incident instead of the modified-since window}
        {--max=600 : Stop after this many incidents in one run}
        {--write-json : Merge the synced rows into data/external/aiid_incidents.json and aiid_reports.json}
        {--no-db : Do not write to the database (with --write-json, refresh the files only)}';

    protected $description = 'Sync the latest AI Incident Database records (incidents, entities, classifications, report metadata) from its API';

    public const LAST_RUN_KEY = 'aiid-api-last-run';

    /** Local snapshot of every live-synced row, on the private disk (persistent storage in production). */
    public const LOCAL_INCIDENTS = 'external/aiid_live_incidents.json';

    public const LOCAL_REPORTS = 'external/aiid_live_reports.json';

    private const PAGE = 100;

    public function handle(AiidApiClient $api): int
    {
        $since = $this->since();
        $max = max(1, (int) $this->option('max'));
        $this->line($since ? "Fetching incidents modified after {$since}…" : 'Fetching every incident…');

        $incidents = [];
        $skip = 0;
        try {
            do {
                $page = $api->incidents(self::PAGE, $skip, $since);
                foreach ($page as $row) {
                    $incidents[(int) $row['incident_id']] = $row;
                }
                $skip += self::PAGE;
                $this->output->write('.');
            } while (count($page) === self::PAGE && count($incidents) < $max);
            $this->newLine();
            $classifications = $incidents ? $api->classifications(array_keys($incidents)) : [];
        } catch (Throwable $e) {
            $this->error('AIID API unavailable: '.$e->getMessage());
            $this->recordRun(0, 0, $e->getMessage());

            return self::FAILURE;
        }

        if (! $incidents) {
            $this->info('No new or modified incidents.');
            $this->recordRun(0, 0);

            return self::SUCCESS;
        }

        $now = now();
        $incidentRows = [];
        $reportRows = [];
        foreach ($incidents as $id => $row) {
            $incidentRows[$id] = $this->incidentRow($row, $classifications[$id] ?? [], $now);
            foreach ($row['reports'] ?? [] as $r) {
                if (empty($r['report_number']) || empty($r['url'])) {
                    continue;
                }
                $reportRows[(int) $r['report_number']] = [
                    'report_number' => (int) $r['report_number'], 'incident_id' => $id, 'title' => mb_substr((string) ($r['title'] ?: '(untitled)'), 0, 300), 'url' => mb_substr((string) $r['url'], 0, 2048),
                    'source_domain' => mb_substr((string) ($r['source_domain'] ?? ''), 0, 190) ?: null, 'date_published' => $r['date_published'] ? substr((string) $r['date_published'], 0, 10) : null,
                    'authors' => array_slice(array_map(fn ($a) => mb_substr((string) $a, 0, 120), $r['authors'] ?? []), 0, 6), 'language' => mb_substr((string) ($r['language'] ?? ''), 0, 8) ?: null,
                ];
            }
        }

        $new = 0;
        if (! $this->option('no-db')) {
            $existing = ExternalIncident::whereIn('incident_id', array_keys($incidentRows))->get()->keyBy('incident_id');
            $new = count($incidentRows) - $existing->count();
            DB::transaction(function () use ($incidentRows, $reportRows, $existing, $now) {
                foreach (array_chunk($incidentRows, 200, true) as $chunk) {
                    $rows = [];
                    foreach ($chunk as $id => $r) {
                        // Keep a snapshot-derived classification when the API has none for this record.
                        $old = $existing[$id] ?? null;
                        foreach (['mit_domain', 'mit_subdomain', 'entity', 'intent', 'timing', 'harm_level'] as $k) {
                            if ($r[$k] === null && $old) {
                                $r[$k] = $old->{$k};
                            }
                        }
                        foreach (['sectors', 'countries'] as $k) {
                            if ($r[$k] === [] && $old && $old->{$k}) {
                                $r[$k] = $old->{$k};
                            }
                        }
                        $r['snapshot_date'] = $old?->snapshot_date?->toDateString();
                        $r['created_at'] = $old?->created_at ?? $now;
                        $r['updated_at'] = $now;
                        foreach (['deployers', 'developers', 'harmed', 'sectors', 'countries', 'entities', 'implicated_systems', 'similar_incidents'] as $k) {
                            $r[$k] = json_encode($r[$k], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                        }
                        $rows[] = $r;
                    }
                    ExternalIncident::upsert($rows, ['incident_id'], array_diff(array_keys($rows[0]), ['incident_id', 'created_at']));
                }
                foreach (array_chunk($reportRows, 500) as $chunk) {
                    $rows = array_map(fn ($r) => ['authors' => json_encode($r['authors'], JSON_UNESCAPED_UNICODE)] + $r + ['synced_at' => $now->toDateTimeString(), 'created_at' => $now->toDateTimeString(), 'updated_at' => $now->toDateTimeString()], $chunk);
                    ExternalIncidentReport::upsert($rows, ['report_number'], array_diff(array_keys($rows[0]), ['report_number', 'created_at']));
                }
            });
            RecordSlugs::assignIncidents();
            Cache::forget('risk-narrative-v1');
        }

        if (! $this->option('no-db')) {
            // Durable copy on the private disk (persistent storage): re-imported on deploy so live-synced
            // records survive a rebuilt database even when the API is unreachable.
            $this->mergeJson($incidentRows, $reportRows, $now, Storage::disk('local')->path(self::LOCAL_INCIDENTS), Storage::disk('local')->path(self::LOCAL_REPORTS));
        }
        if ($this->option('write-json')) {
            $this->mergeJson($incidentRows, $reportRows, $now, base_path(ExternalDataset::AIID_INCIDENTS), base_path('data/external/aiid_reports.json'));
        }

        $latest = max(array_keys($incidentRows));
        $this->recordRun(count($incidentRows), count($reportRows), null, $latest);
        $this->info(sprintf('Synced %d incidents (%d new) and %d reports from the AI Incident Database; latest id %d.', count($incidentRows), $new, count($reportRows), $latest));

        return self::SUCCESS;
    }

    /** @return array<string, mixed> */
    private function incidentRow(array $row, array $cls, Carbon $now): array
    {
        $names = fn (?array $list) => array_slice(array_values(array_filter(array_map(fn ($e) => mb_substr(trim((string) ($e['name'] ?? '')), 0, 120), $list ?? []))), 0, 8);
        $ents = fn (?array $list) => array_slice(array_values(array_filter(array_map(fn ($e) => ['id' => mb_substr((string) ($e['entity_id'] ?? ''), 0, 120), 'name' => mb_substr(trim((string) ($e['name'] ?? '')), 0, 120)], $list ?? []), fn ($e) => $e['id'] !== '' && $e['name'] !== '')), 0, 12);
        $mit = $cls['MIT'] ?? [];
        $cset = $cls['CSETv1'] ?? [];
        $strip = fn ($v, string $pattern) => is_string($v) && trim($v) !== '' ? mb_substr(trim(preg_replace($pattern, '', trim($v))), 0, 120) : null;
        $harm = isset($cset['AI Harm Level']) && is_string($cset['AI Harm Level']) && trim($cset['AI Harm Level']) !== '' ? mb_substr(trim($cset['AI Harm Level']), 0, 60) : null;
        $sectors = array_slice(array_values(array_filter(array_map(fn ($s) => mb_strtolower(trim((string) $s)), is_array($cset['Sector of Deployment'] ?? null) ? $cset['Sector of Deployment'] : []))), 0, 5);
        $country = isset($cset['Location Country (two letters)']) && is_string($cset['Location Country (two letters)']) && preg_match('/^[A-Za-z]{2}$/', trim($cset['Location Country (two letters)'])) ? strtoupper(trim($cset['Location Country (two letters)'])) : null;
        $nlp = collect($row['nlp_similar_incidents'] ?? [])->filter(fn ($s) => ! empty($s['incident_id']))->sortByDesc('similarity')->take(5)->map(fn ($s) => ['id' => (int) $s['incident_id'], 'similarity' => round((float) ($s['similarity'] ?? 0), 3)])->values()->all();
        $date = substr((string) $row['date'], 0, 10);

        return [
            'incident_id' => (int) $row['incident_id'], 'occurred_on' => $date, 'year' => (int) substr($date, 0, 4), 'title' => mb_substr((string) $row['title'], 0, 200), 'description' => mb_substr((string) ($row['description'] ?? ''), 0, 500) ?: null,
            'deployers' => $names($row['AllegedDeployerOfAISystem'] ?? null), 'developers' => $names($row['AllegedDeveloperOfAISystem'] ?? null), 'harmed' => $names($row['AllegedHarmedOrNearlyHarmedParties'] ?? null),
            'report_count' => count($row['reports'] ?? []),
            'mit_domain' => $strip($mit['Risk Domain'] ?? null, '/^\d+\.\s*/'), 'mit_subdomain' => $strip($mit['Risk Subdomain'] ?? null, '/^\d+\.\d+\.?\s*/'),
            'entity' => $strip($mit['Entity'] ?? null, '/^$/'), 'intent' => $strip($mit['Intent'] ?? null, '/^$/'), 'timing' => $strip($mit['Timing'] ?? null, '/^$/'),
            'sectors' => $sectors, 'countries' => $country ? [$country] : [], 'harm_level' => $harm,
            'editor_notes' => mb_substr(trim((string) ($row['editor_notes'] ?? '')), 0, 2000) ?: null,
            'entities' => ['deployers' => $ents($row['AllegedDeployerOfAISystem'] ?? null), 'developers' => $ents($row['AllegedDeveloperOfAISystem'] ?? null), 'harmed' => $ents($row['AllegedHarmedOrNearlyHarmedParties'] ?? null)],
            'implicated_systems' => $ents($row['implicated_systems'] ?? null),
            'similar_incidents' => ['editor' => array_slice(array_map('intval', array_filter($row['editor_similar_incidents'] ?? [])), 0, 8), 'nlp' => $nlp],
            'modified_at' => ! empty($row['date_modified']) ? Carbon::parse($row['date_modified'])->toDateTimeString() : null,
            'synced_at' => $now->toDateTimeString(),
        ];
    }

    private function since(): ?string
    {
        if ($this->option('full')) {
            return null;
        }
        if ($this->option('since')) {
            return Carbon::parse($this->option('since'))->toIso8601ZuluString();
        }
        $last = $this->option('no-db') ? null : ExternalIncident::max('modified_at');
        if ($last) {
            return Carbon::parse($last)->subDay()->toIso8601ZuluString();
        }
        $snapshot = $this->option('no-db') ? null : ExternalIncident::max('snapshot_date');

        return Carbon::parse($snapshot ?: now()->subDays(30))->subDays(7)->toIso8601ZuluString();
    }

    /** Merge rows by id into a pair of JSON files (repository copy or local snapshot); existing rows are kept. */
    private function mergeJson(array $incidentRows, array $reportRows, Carbon $now, string $incPath, string $repPath): void
    {
        File::ensureDirectoryExists(dirname($incPath));
        File::ensureDirectoryExists(dirname($repPath));
        $file = File::exists($incPath) ? (json_decode(File::get($incPath), true) ?: ['incidents' => []]) : ['incidents' => []];
        $byId = [];
        foreach ($file['incidents'] ?? [] as $i) {
            $byId[(int) $i['incident_id']] = $i;
        }
        foreach ($incidentRows as $id => $r) {
            $old = $byId[$id] ?? [];
            $byId[$id] = [
                'incident_id' => $id, 'date' => $r['occurred_on'], 'title' => $r['title'], 'description' => (string) $r['description'],
                'deployers' => $r['deployers'], 'developers' => $r['developers'], 'harmed' => $r['harmed'], 'report_count' => $r['report_count'],
                'mit_domain' => $r['mit_domain'] ?? ($old['mit_domain'] ?? ''), 'mit_subdomain' => $r['mit_subdomain'] ?? ($old['mit_subdomain'] ?? ''), 'entity' => $r['entity'] ?? ($old['entity'] ?? ''), 'intent' => $r['intent'] ?? ($old['intent'] ?? ''), 'timing' => $r['timing'] ?? ($old['timing'] ?? ''),
                'sectors' => $r['sectors'] ?: ($old['sectors'] ?? []), 'countries' => $r['countries'] ?: ($old['countries'] ?? []), 'harm_level' => $r['harm_level'] ?? ($old['harm_level'] ?? ''),
                'editor_notes' => (string) $r['editor_notes'], 'entities' => $r['entities'], 'implicated_systems' => $r['implicated_systems'], 'similar_incidents' => $r['similar_incidents'], 'modified_at' => $r['modified_at'],
            ];
        }
        $all = array_values($byId);
        usort($all, fn ($a, $b) => [$b['date'], $b['incident_id']] <=> [$a['date'], $a['incident_id']]);
        $file = array_merge($file, ['api_synced_at' => $now->toDateTimeString(), 'generated_at' => $now->toDateString(), 'count' => count($all), 'incidents' => $all]);
        File::put($incPath, json_encode($file, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        $rfile = File::exists($repPath) ? (json_decode(File::get($repPath), true) ?: ['reports' => []]) : ['reports' => []];
        $byNo = [];
        foreach ($rfile['reports'] ?? [] as $r) {
            $byNo[(int) $r['report_number']] = $r;
        }
        foreach ($reportRows as $n => $r) {
            $byNo[$n] = $r;
        }
        $reports = array_values($byNo);
        usort($reports, fn ($a, $b) => [$a['incident_id'], $a['report_number']] <=> [$b['incident_id'], $b['report_number']]);
        $rfile = array_merge($rfile, ['api_synced_at' => $now->toDateTimeString(), 'generated_at' => $now->toDateString(), 'count' => count($reports), 'reports' => $reports]);
        File::put($repPath, json_encode($rfile, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n");
        $this->line(basename(dirname($incPath)).'/'.basename($incPath).' updated: '.count($all).' incidents, '.count($reports).' reports.');
    }

    private function recordRun(int $incidents, int $reports, ?string $error = null, ?int $latest = null): void
    {
        try {
            Cache::forever(self::LAST_RUN_KEY, ['at' => now()->toDateTimeString(), 'incidents' => $incidents, 'reports' => $reports, 'latest_id' => $latest, 'error' => $error ? mb_substr($error, 0, 200) : null]);
        } catch (Throwable) {
            // Cache unavailable (e.g. during CI); the run itself is unaffected.
        }
    }
}
