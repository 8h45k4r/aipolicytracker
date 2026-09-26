<?php

namespace App\Services\Report;

use App\Models\ChangeEvent;
use App\Models\Deadline;
use App\Models\Jurisdiction;
use App\Models\Obligation;
use App\Models\PolicyInstrument;
use App\Models\ReportSnapshot;
use App\Services\Hubs\HubCatalog;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * The state of AI regulation, computed from the records: how many
 * jurisdictions have binding AI law, what kinds of instrument exist, what
 * changed in the quarter, what is due next, and how much of the corpus is
 * verified. A quarter's figures are frozen as a snapshot when the quarter
 * closes (or on demand), so a past report reads the same next year.
 */
final class StateOfAiRegulation
{
    public const VERSION = '1.0';

    public static function currentQuarter(?CarbonImmutable $now = null): string
    {
        $now ??= CarbonImmutable::now();

        return $now->format('Y').'-Q'.$now->quarter;
    }

    /** @return array{start:CarbonImmutable, end:CarbonImmutable} */
    public static function bounds(string $quarter): array
    {
        [$year, $q] = explode('-Q', $quarter);
        $start = CarbonImmutable::create((int) $year, ((int) $q - 1) * 3 + 1, 1)->startOfDay();

        return ['start' => $start, 'end' => $start->addMonths(3)->subDay()->endOfDay()];
    }

    /** Everything the report shows, computed live for a quarter. */
    public static function compute(string $quarter): array
    {
        ['start' => $start, 'end' => $end] = self::bounds($quarter);
        $jurisdictions = Jurisdiction::published()->whereIn('jurisdiction_type', ['country', 'supranational'])->withCount([
            'policyInstruments as instruments_count' => fn ($q) => $q->published(),
            'policyInstruments as binding_count' => fn ($q) => $q->published()->where('is_binding', true),
            'policyInstruments as in_force_count' => fn ($q) => $q->published()->where('is_binding', true)->whereIn('status', ['in_force', 'partially_applicable']),
        ])->get();
        $policies = PolicyInstrument::published()->get(['id', 'instrument_type', 'status', 'is_binding', 'review_status', 'official_source_url', 'jurisdiction_id']);
        $changes = ChangeEvent::published()->whereDate('occurred_on', '>=', $start->toDateString())->whereDate('occurred_on', '<=', $end->toDateString())->get(['id', 'impact_level', 'jurisdiction_id', 'title', 'slug', 'occurred_on']);
        $nextStart = $end->addDay();
        $upcoming = Deadline::with('policyInstrument.jurisdiction')->whereHas('policyInstrument', fn ($p) => $p->published())->where('deadline_status', 'scheduled')
            ->whereDate('due_on', '>=', $nextStart->toDateString())->whereDate('due_on', '<=', $nextStart->addMonths(3)->toDateString())->orderBy('due_on')->get();

        $byRegion = $jurisdictions->groupBy(fn ($j) => $j->region ?: 'Other')->map(fn ($g) => [
            'jurisdictions' => $g->count(),
            'with_records' => $g->where('instruments_count', '>', 0)->count(),
            'binding' => $g->where('binding_count', '>', 0)->count(),
            'in_force' => $g->where('in_force_count', '>', 0)->count(),
        ])->sortKeys()->all();
        $tiles = $jurisdictions->where('instruments_count', '>', 0)->sortBy('name')->map(fn ($j) => [
            'slug' => $j->slug, 'name' => $j->name, 'short' => $j->short_name ?: $j->name, 'region' => $j->region ?: 'Other',
            'level' => $j->in_force_count > 0 ? 'in_force' : ($j->binding_count > 0 ? 'binding' : 'guidance'), 'instruments' => $j->instruments_count, 'url' => $j->url(),
        ])->values()->all();

        return [
            'quarter' => $quarter,
            'version' => self::VERSION,
            'period' => ['start' => $start->toDateString(), 'end' => $end->toDateString()],
            'totals' => [
                'jurisdictions_covered' => $jurisdictions->count(),
                'jurisdictions_with_records' => $jurisdictions->where('instruments_count', '>', 0)->count(),
                'jurisdictions_with_binding_law' => $jurisdictions->where('binding_count', '>', 0)->count(),
                'jurisdictions_with_binding_in_force' => $jurisdictions->where('in_force_count', '>', 0)->count(),
                'instruments' => $policies->count(),
                'binding_instruments' => $policies->where('is_binding', true)->count(),
                'obligations' => Obligation::published()->count(),
                'verified_instruments' => $policies->where('review_status', 'verified')->count(),
                'sourced_instruments' => $policies->filter(fn ($p) => filled($p->official_source_url))->count(),
                'changes_in_quarter' => $changes->count(),
                'urgent_changes_in_quarter' => $changes->where('impact_level', 'urgent')->count(),
                'deadlines_next_quarter' => $upcoming->count(),
            ],
            'by_type' => $policies->groupBy('instrument_type')->map->count()->sortDesc()->all(),
            'by_status' => $policies->groupBy('status')->map->count()->sortDesc()->all(),
            'by_region' => $byRegion,
            'tiles' => $tiles,
            'changes_by_jurisdiction' => $changes->groupBy('jurisdiction_id')->map->count()->sortDesc()->take(10)->mapWithKeys(fn ($n, $id) => [Jurisdiction::whereKey($id)->value('name') ?? (string) $id => $n])->all(),
            'top_changes' => $changes->sortByDesc('occurred_on')->take(8)->map(fn ($c) => ['title' => $c->title, 'slug' => $c->slug, 'occurred_on' => $c->occurred_on?->toDateString(), 'impact_level' => $c->impact_level, 'jurisdiction' => Jurisdiction::whereKey($c->jurisdiction_id)->value('name')])->values()->all(),
            'upcoming' => $upcoming->take(12)->map(fn ($d) => ['title' => $d->title, 'due_on' => $d->due_on?->toDateString(), 'policy' => $d->policyInstrument->short_title ?: $d->policyInstrument->title, 'policy_url' => $d->policyInstrument->url(), 'jurisdiction' => $d->policyInstrument->jurisdiction->name])->values()->all(),
            'computed_at' => now()->toAtomString(),
        ];
    }

    /** The report for a quarter: the frozen snapshot if one exists, else computed live. */
    public static function report(string $quarter): array
    {
        $frozen = ReportSnapshot::where('quarter', $quarter)->where('version', self::VERSION)->first();
        if ($frozen) {
            return $frozen->data + ['frozen' => true, 'frozen_at' => $frozen->frozen_at->toAtomString()];
        }

        return self::compute($quarter) + ['frozen' => false, 'frozen_at' => null];
    }

    /** Freeze a quarter (idempotent per quarter and version). */
    public static function freeze(string $quarter): ReportSnapshot
    {
        return ReportSnapshot::updateOrCreate(['quarter' => $quarter, 'version' => self::VERSION], ['data' => self::compute($quarter), 'frozen_at' => now()]);
    }

    /** Quarters that have a frozen snapshot, newest first. @return Collection<int,string> */
    public static function frozenQuarters(): Collection
    {
        return ReportSnapshot::where('version', self::VERSION)->orderByDesc('quarter')->pluck('quarter');
    }

    public static function isValidQuarter(string $quarter): bool
    {
        return preg_match('/^20[0-9]{2}-Q[1-4]$/', $quarter) === 1;
    }

    public static function csv(array $report): string
    {
        $fh = fopen('php://temp', 'r+');
        fputcsv($fh, ['section', 'key', 'value']);
        foreach ($report['totals'] as $k => $v) {
            fputcsv($fh, ['totals', $k, $v]);
        }
        foreach (['by_type', 'by_status', 'changes_by_jurisdiction'] as $section) {
            foreach ($report[$section] as $k => $v) {
                fputcsv($fh, [$section, $k, $v]);
            }
        }
        foreach ($report['by_region'] as $region => $row) {
            foreach ($row as $k => $v) {
                fputcsv($fh, ['by_region:'.$region, $k, $v]);
            }
        }
        foreach ($report['tiles'] as $t) {
            fputcsv($fh, ['jurisdiction:'.$t['slug'], $t['level'], $t['instruments']]);
        }
        rewind($fh);
        $out = (string) stream_get_contents($fh);
        fclose($fh);

        return $out;
    }

    public static function regionHubUrl(string $region): ?string
    {
        $slug = HubCatalog::regionSlugFor($region);

        return $slug ? HubCatalog::regionUrl($slug) : null;
    }
}
