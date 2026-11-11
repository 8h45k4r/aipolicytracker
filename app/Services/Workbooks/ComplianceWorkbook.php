<?php

namespace App\Services\Workbooks;

use App\Models\ApplicabilityProfile;
use App\Models\Control;
use App\Models\Jurisdiction;
use App\Models\Obligation;
use App\Models\ProfileWorkbookVersion;
use App\Services\Applicability\ApplicabilityScreener;
use App\Services\Templates\DatasetVersion;
use App\Services\Templates\Workbook;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * A saved profile's compliance workbook: the duties the applicability screen
 * finds for the declared system, with their dates, the controls recorded as
 * meeting them, the evidence a reviewer expects and the official sources, plus
 * columns for the account's own assessment.
 *
 * The rows come from the same screen as the free applicability check and its
 * register download, so the workbook never says more than the public records.
 * What the account pays for is the upkeep: a version is stored whenever the
 * screened records change (a new duty, a changed date, a review), the history
 * says what changed, and the owner is told.
 *
 * It never decides that a duty applies or is met. Status columns are the
 * account's own; a pending-review record says so on every row.
 */
final class ComplianceWorkbook
{
    public const STATUS_OPTIONS = ['Not assessed', 'Applies', 'Does not apply', 'In progress', 'Met'];

    public function __construct(private readonly ApplicabilityScreener $screener) {}

    /**
     * The rows a version is built from, keyed by obligation slug and sorted, so two
     * builds over the same records hash the same.
     *
     * @return array<string, array<string, mixed>>
     */
    public function snapshot(ApplicabilityProfile $profile): array
    {
        $obligations = $this->screener->screen($profile->answers)['obligations']
            ->load(['policyInstrument.jurisdiction', 'evidenceArtifacts', 'deadlines', 'controls' => fn ($q) => $q->whereNotNull('controls.published_at')]);

        $rows = [];
        foreach ($obligations as $o) {
            /** @var Obligation $o */
            $p = $o->policyInstrument;
            [$date, $basis] = $o->applies_from ? [$o->applies_from, 'duty'] : ($p->applies_from ? [$p->applies_from, 'instrument'] : ($p->in_force_on ? [$p->in_force_on, 'in force'] : [null, null]));
            $rows[$o->slug] = [
                'aipt_id' => $o->aipt_id,
                'title' => $o->title,
                'category' => $o->category,
                'instrument' => $p->short_title ?: $p->title,
                'instrument_slug' => $p->slug,
                'instrument_status' => $p->status,
                'jurisdiction' => $p->jurisdiction->name,
                'binding' => (bool) $o->is_binding,
                'reference' => $o->source_reference,
                'applies_from' => $date?->toDateString(),
                'date_basis' => $basis,
                'summary' => $o->summary,
                'action' => $o->practical_action,
                'verification' => $o->verificationState(),
                'verified_on' => $o->last_verified_at?->toDateString(),
                'source_url' => $o->official_source_url ?: $p->official_source_url,
                'source_title' => $p->source_title,
                'source_publisher' => $p->source_publisher,
                'controls' => $o->controls->sortBy('slug')->mapWithKeys(fn (Control $c) => [$c->slug => ['title' => $c->title, 'relationship' => $c->pivot->relationship]])->all(),
                'evidence' => $o->evidenceArtifacts->sortBy('title')->pluck('title')->values()->all(),
                'deadlines' => $o->deadlines->sortBy('due_on')->map(fn ($d) => ['date' => $d->due_on?->toDateString(), 'title' => $d->title, 'status' => $d->deadline_status])->values()->all(),
            ];
        }
        ksort($rows);

        return $rows;
    }

    public static function hash(array $snapshot): string
    {
        return hash('sha256', json_encode($snapshot, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    /**
     * Stores a new version when the screened rows differ from the latest one (or
     * there is none yet). Returns the new version, or null when nothing changed.
     */
    public function refresh(ApplicabilityProfile $profile): ?ProfileWorkbookVersion
    {
        $snapshot = $this->snapshot($profile);
        $hash = self::hash($snapshot);

        return DB::transaction(function () use ($profile, $snapshot, $hash) {
            $latest = $profile->workbookVersions()->lockForUpdate()->first();
            if ($latest && $latest->content_hash === $hash) {
                return null;
            }

            return $profile->workbookVersions()->create([
                'version' => ($latest?->version ?? 0) + 1,
                'content_hash' => $hash,
                'snapshot' => $snapshot,
                'record_changes' => $latest ? self::diff($latest->snapshot, $snapshot) : null,
                'dataset_version' => DatasetVersion::current(),
            ]);
        });
    }

    /** The latest version, building the first one when there is none. */
    public function current(ApplicabilityProfile $profile): ProfileWorkbookVersion
    {
        return $this->refresh($profile) ?? $profile->latestWorkbook();
    }

    /**
     * What changed between two snapshots, by obligation: added, removed, and the
     * fields that differ on the rest.
     *
     * @return array{added: list<array>, removed: list<array>, changed: list<array>}
     */
    public static function diff(array $before, array $after): array
    {
        $label = fn (array $row, string $slug) => ['slug' => $slug, 'title' => $row['title'], 'instrument' => $row['instrument']];
        $changed = [];
        foreach (array_intersect_key($after, $before) as $slug => $row) {
            $fields = array_keys(array_filter($row, fn ($v, $k) => ($before[$slug][$k] ?? null) !== $v, ARRAY_FILTER_USE_BOTH));
            if ($fields !== []) {
                $changed[] = $label($row, $slug) + ['fields' => array_values($fields)];
            }
        }

        return [
            'added' => array_values(array_map($label, array_diff_key($after, $before), array_keys(array_diff_key($after, $before)))),
            'removed' => array_values(array_map($label, array_diff_key($before, $after), array_keys(array_diff_key($before, $after)))),
            'changed' => $changed,
        ];
    }

    /** Plain-language names for the fields the history reports. */
    public const FIELD_LABELS = [
        'title' => 'title', 'category' => 'category', 'instrument' => 'instrument', 'instrument_slug' => 'instrument',
        'instrument_status' => 'instrument status', 'jurisdiction' => 'jurisdiction', 'binding' => 'binding force',
        'reference' => 'source reference', 'applies_from' => 'application date', 'date_basis' => 'application date',
        'summary' => 'what it requires', 'action' => 'practical action', 'verification' => 'review status',
        'verified_on' => 'verification date', 'source_url' => 'official source', 'source_title' => 'official source',
        'source_publisher' => 'official source', 'controls' => 'mapped controls', 'evidence' => 'expected evidence',
        'deadlines' => 'deadlines', 'aipt_id' => 'permanent id',
    ];

    /** @return list<string> */
    public static function fieldNames(array $fields): array
    {
        return array_values(array_unique(array_map(fn ($f) => self::FIELD_LABELS[$f] ?? str_replace('_', ' ', $f), $fields)));
    }

    /** The XLSX file for a stored version. */
    public function xlsx(ApplicabilityProfile $profile, ProfileWorkbookVersion $version): string
    {
        $rows = $version->snapshot;
        $today = Carbon::today();
        $verification = fn (array $r) => match ($r['verification']) {
            'verified' => 'Verified against the official source'.($r['verified_on'] ? ' '.$r['verified_on'] : ''),
            'pending_review' => 'Pending review: confirm against the official source',
            default => 'Source-linked, not yet reviewed',
        };

        $obligations = [];
        $deadlines = [];
        $controls = [];
        $evidence = [];
        $sources = [];
        foreach ($rows as $slug => $r) {
            $url = route('obligations.show', $slug);
            $obligations[] = [
                'id' => $r['aipt_id'], 'duty' => $r['title'], 'instrument' => $r['instrument'], 'jurisdiction' => $r['jurisdiction'],
                'binding' => $r['binding'] ? 'Legal requirement' : 'Voluntary', 'reference' => $r['reference'],
                'applies_from' => $r['applies_from'], 'summary' => $r['summary'], 'action' => $r['action'],
                'controls' => implode('; ', array_column($r['controls'], 'title')), 'evidence' => implode('; ', $r['evidence']),
                'verification' => $verification($r), 'url' => $url,
            ];
            if ($r['applies_from']) {
                $deadlines[] = ['date' => $r['applies_from'], 'what' => 'Applies from ('.$r['date_basis'].' date)', 'duty' => $r['title'], 'instrument' => $r['instrument'], 'jurisdiction' => $r['jurisdiction'], 'state' => $r['applies_from'] <= $today->toDateString() ? 'In force' : 'Upcoming', 'url' => $url];
            }
            foreach ($r['deadlines'] as $d) {
                if ($d['date']) {
                    $deadlines[] = ['date' => $d['date'], 'what' => $d['title'], 'duty' => $r['title'], 'instrument' => $r['instrument'], 'jurisdiction' => $r['jurisdiction'], 'state' => $d['date'] <= $today->toDateString() ? 'Passed' : 'Upcoming', 'url' => $url];
                }
            }
            foreach ($r['controls'] as $cslug => $c) {
                $controls[$cslug] ??= ['control' => $c['title'], 'duties' => [], 'url' => route('controls.show', $cslug)];
                $controls[$cslug]['duties'][] = $r['title'].($c['relationship'] ? ' ('.$c['relationship'].')' : '');
            }
            foreach ($r['evidence'] as $e) {
                $evidence[] = ['evidence' => $e, 'duty' => $r['title'], 'instrument' => $r['instrument'], 'url' => $url];
            }
            $sources[$r['instrument_slug']] ??= ['instrument' => $r['instrument'], 'jurisdiction' => $r['jurisdiction'], 'status' => str_replace('_', ' ', (string) $r['instrument_status']), 'source' => $r['source_title'], 'publisher' => $r['source_publisher'], 'source_url' => $r['source_url'], 'url' => route('policies.show', $r['instrument_slug'])];
        }
        usort($deadlines, fn ($a, $b) => strcmp($a['date'], $b['date']));
        $controls = array_values(array_map(fn ($c) => ['control' => $c['control'], 'duties' => implode('; ', $c['duties']), 'count' => count($c['duties']), 'url' => $c['url']], $controls));

        $history = $profile->workbookVersions()->where('version', '<=', $version->version)->get()->flatMap(function (ProfileWorkbookVersion $v) {
            if ($v->record_changes === null) {
                return [['version' => $v->version, 'date' => $v->created_at->toDateString(), 'change' => 'First version', 'duty' => count($v->snapshot).' duties screened', 'detail' => null]];
            }
            $out = [];
            foreach (['added' => 'Added', 'removed' => 'No longer screened', 'changed' => 'Changed'] as $key => $word) {
                foreach ($v->record_changes[$key] ?? [] as $c) {
                    $out[] = ['version' => $v->version, 'date' => $v->created_at->toDateString(), 'change' => $word, 'duty' => $c['title'].' ('.$c['instrument'].')', 'detail' => isset($c['fields']) ? implode(', ', self::fieldNames($c['fields'])) : null];
                }
            }

            return $out;
        })->values()->all();

        $text = ['width' => 40];
        $select = ['type' => 'select', 'options' => self::STATUS_OPTIONS, 'width' => 14, 'note' => 'Your assessment; the workbook does not decide it.'];
        $sheets = [
            ['name' => 'Gap analysis', 'columns' => [
                ['key' => 'id', 'label' => 'Permanent id', 'width' => 16],
                ['key' => 'duty', 'label' => 'Duty', 'width' => 50],
                ['key' => 'instrument', 'label' => 'Instrument', 'width' => 28],
                ['key' => 'jurisdiction', 'label' => 'Jurisdiction', 'width' => 16],
                ['key' => 'binding', 'label' => 'Nature', 'width' => 16],
                ['key' => 'reference', 'label' => 'Source reference', 'width' => 22],
                ['key' => 'applies_from', 'label' => 'Applies from', 'width' => 13, 'type' => 'date'],
                ['key' => 'summary', 'label' => 'What it requires', 'width' => 60],
                ['key' => 'action', 'label' => 'Practical action', 'width' => 50],
                ['key' => 'controls', 'label' => 'Recorded controls', 'width' => 40],
                ['key' => 'evidence', 'label' => 'Evidence a reviewer expects', 'width' => 40],
                ['key' => 'verification', 'label' => 'Record review status', 'width' => 30],
                ['key' => 'status', 'label' => 'Status'] + $select,
                ['key' => 'gap', 'label' => 'Gap', 'width' => 40, 'note' => 'What is missing to meet the duty.'],
                ['key' => 'owner', 'label' => 'Owner', 'width' => 18],
                ['key' => 'due', 'label' => 'Target date', 'width' => 13, 'type' => 'date'],
                ['key' => 'evidence_link', 'label' => 'Evidence link', 'type' => 'url'] + $text,
                ['key' => 'url', 'label' => 'Record', 'width' => 44, 'type' => 'url'],
            ], 'rows' => $obligations, 'rules' => [
                ['column' => 'status', 'op' => 'equal', 'value' => 'Met', 'fill' => 'D4EDDA'],
                ['column' => 'status', 'op' => 'equal', 'value' => 'Applies', 'fill' => 'FFF3CD'],
                ['column' => 'status', 'op' => 'equal', 'value' => 'In progress', 'fill' => 'FFF3CD'],
            ]],
            ['name' => 'Deadlines', 'columns' => [
                ['key' => 'date', 'label' => 'Date', 'width' => 13, 'type' => 'date'],
                ['key' => 'state', 'label' => 'State', 'width' => 12],
                ['key' => 'what', 'label' => 'What', 'width' => 40],
                ['key' => 'duty', 'label' => 'Duty', 'width' => 50],
                ['key' => 'instrument', 'label' => 'Instrument', 'width' => 28],
                ['key' => 'jurisdiction', 'label' => 'Jurisdiction', 'width' => 16],
                ['key' => 'url', 'label' => 'Record', 'width' => 44, 'type' => 'url'],
            ], 'rows' => $deadlines, 'note' => 'State as of '.$version->created_at->toDateString().', the day this version was built.'],
            ['name' => 'Controls', 'columns' => [
                ['key' => 'control', 'label' => 'Control', 'width' => 40],
                ['key' => 'count', 'label' => 'Duties it serves', 'width' => 10, 'type' => 'number'],
                ['key' => 'duties', 'label' => 'Duties (satisfies or supports)', 'width' => 70],
                ['key' => 'status', 'label' => 'Status'] + $select,
                ['key' => 'owner', 'label' => 'Owner', 'width' => 18],
                ['key' => 'url', 'label' => 'Record', 'width' => 44, 'type' => 'url'],
            ], 'rows' => $controls],
            ['name' => 'Evidence', 'columns' => [
                ['key' => 'evidence', 'label' => 'Evidence a reviewer expects', 'width' => 50],
                ['key' => 'duty', 'label' => 'For duty', 'width' => 50],
                ['key' => 'instrument', 'label' => 'Instrument', 'width' => 28],
                ['key' => 'held', 'label' => 'Held?', 'type' => 'select', 'options' => ['Not yet', 'Draft', 'Held'], 'width' => 10],
                ['key' => 'evidence_link', 'label' => 'Where it is', 'type' => 'url'] + $text,
                ['key' => 'url', 'label' => 'Record', 'width' => 44, 'type' => 'url'],
            ], 'rows' => $evidence],
            ['name' => 'Sources', 'columns' => [
                ['key' => 'instrument', 'label' => 'Instrument', 'width' => 36],
                ['key' => 'jurisdiction', 'label' => 'Jurisdiction', 'width' => 16],
                ['key' => 'status', 'label' => 'Status', 'width' => 14],
                ['key' => 'source', 'label' => 'Official source', 'width' => 40],
                ['key' => 'publisher', 'label' => 'Publisher', 'width' => 28],
                ['key' => 'source_url', 'label' => 'Source link', 'width' => 44, 'type' => 'url'],
                ['key' => 'url', 'label' => 'Record', 'width' => 44, 'type' => 'url'],
            ], 'rows' => array_values($sources)],
            ['name' => 'Version history', 'columns' => [
                ['key' => 'version', 'label' => 'Version', 'width' => 9, 'type' => 'number'],
                ['key' => 'date', 'label' => 'Built', 'width' => 13, 'type' => 'date'],
                ['key' => 'change', 'label' => 'Change', 'width' => 18],
                ['key' => 'duty', 'label' => 'Duty', 'width' => 60],
                ['key' => 'detail', 'label' => 'What changed', 'width' => 40],
            ], 'rows' => $history],
            ['name' => 'System', 'columns' => [['key' => 'k', 'label' => 'Field', 'width' => 24], ['key' => 'v', 'label' => 'Value', 'width' => 90]], 'rows' => $this->systemRows($profile)],
        ];

        $readme = [
            'title' => 'Compliance workbook: '.$profile->name,
            'short' => 'The duties the applicability screen finds for this system, with dates, controls, expected evidence and official sources, and columns for your own gap analysis.',
            'inside' => [
                'Gap analysis: one row per screened duty, with status, gap, owner and target date to complete',
                'Deadlines: application dates and recorded deadlines in date order',
                'Controls: each recorded control and the duties it satisfies or supports',
                'Evidence: what a reviewer expects to see for each duty',
                'Sources: the official source behind each instrument',
                'Version history: what changed in the records since the first version',
                'System: the system as you described it, and the answers the screen used',
            ],
            'frameworks' => [],
            'version' => 'version '.$version->version.', built '.$version->created_at->format('Y-m-d'),
            'generated_at' => $version->created_at->format('Y-m-d'),
            'dataset_version' => $version->dataset_version ?? DatasetVersion::current(),
            'url' => route('profiles.show', $profile),
            'licence' => config('templates.licence'),
            'disclaimer' => 'An educational screen over recorded scope, not a determination that any duty applies to this system or is met. A record pending review has not been confirmed against the official source. '.config('templates.disclaimer'),
        ];

        $path = tempnam(sys_get_temp_dir(), 'workbook').'.xlsx';
        try {
            (new Workbook($readme, $sheets))->save($path);

            return (string) file_get_contents($path);
        } finally {
            @unlink($path);
        }
    }

    /** @return list<array{k:string, v:string}> */
    private function systemRows(ApplicabilityProfile $profile): array
    {
        $names = Jurisdiction::whereIn('slug', $profile->answers['jurisdictions'] ?? [])->pluck('name', 'slug')->all();
        $rows = [
            ['k' => 'System', 'v' => $profile->name],
            ['k' => 'Description', 'v' => (string) $profile->system_description],
            ['k' => 'Owner', 'v' => (string) $profile->system_owner],
            ['k' => 'Lifecycle', 'v' => ApplicabilityProfile::LIFECYCLE[$profile->lifecycle] ?? ''],
        ];
        foreach ($profile->answers as $key => $value) {
            $rows[] = ['k' => 'Answer: '.str_replace('_', ' ', $key), 'v' => is_array($value) ? implode(', ', array_map(fn ($s) => $names[$s] ?? $s, $value)) : (string) $value];
        }

        return $rows;
    }
}
