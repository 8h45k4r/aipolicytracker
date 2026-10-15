<?php

namespace App\Services\ChangeDesk;

use App\Enums\PolicyStatus;
use App\Models\Follow;
use App\Models\PolicyInstrument;
use App\Models\SourceCheck;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Which official sources are due a look, most urgent first. A binding instrument is due
 * every config('changedesk.cadence_days.binding') days and anything else still current every
 * config('changedesk.cadence_days.other') days. Records people watch come first among equals:
 * that is where a missed change costs most.
 */
final class SourceQueue
{
    private const NOT_CURRENT = [PolicyStatus::Superseded, PolicyStatus::Repealed, PolicyStatus::Archived];

    /**
     * The last look counts from the newest desk check, or from the record's own
     * last_checked_at in data/ when the desk has not logged one yet.
     *
     * @return Collection<int, array{policy: PolicyInstrument, cadence: int, last_at: ?Carbon, last_outcome: ?string, due: bool, overdue_days: ?int, watchers: int}>
     */
    public static function rows(): Collection
    {
        $watchers = Follow::query()->where('subject_type', 'policy')->selectRaw('subject_slug, count(*) as n')->groupBy('subject_slug')->pluck('n', 'subject_slug');
        // Only the newest check per instrument.
        $checks = SourceCheck::query()->where('record_type', 'policy')
            ->whereIn('id', SourceCheck::query()->where('record_type', 'policy')->selectRaw('max(id)')->groupBy('record_slug'))
            ->get(['id', 'record_slug', 'outcome', 'checked_at'])->keyBy('record_slug');

        return PolicyInstrument::published()->with('jurisdiction:id,slug,name,short_name')->withCount(['obligations' => fn ($q) => $q->whereNotNull('published_at')])
            ->get(['id', 'slug', 'aipt_id', 'title', 'short_title', 'status', 'is_binding', 'jurisdiction_id', 'official_source_url', 'last_verified_at', 'last_checked_at'])
            ->reject(fn (PolicyInstrument $p) => in_array(PolicyStatus::tryFrom((string) $p->status), self::NOT_CURRENT, true))
            ->map(function (PolicyInstrument $p) use ($watchers, $checks) {
                $cadence = (int) config('changedesk.cadence_days.'.($p->is_binding ? 'binding' : 'other'));
                $check = $checks->get($p->slug);
                $lastAt = $check?->checked_at ?? $p->last_checked_at;
                $overdue = $lastAt ? (int) floor($lastAt->diffInDays(now())) - $cadence : null;

                return ['policy' => $p, 'cadence' => $cadence, 'last_at' => $lastAt, 'last_outcome' => $check?->outcome, 'due' => $lastAt === null || $overdue >= 0, 'overdue_days' => $overdue, 'watchers' => (int) ($watchers[$p->slug] ?? 0)];
            })
            ->sortBy([
                fn ($a, $b) => $b['due'] <=> $a['due'],
                fn ($a, $b) => $b['policy']->is_binding <=> $a['policy']->is_binding,
                fn ($a, $b) => $b['watchers'] <=> $a['watchers'],
                fn ($a, $b) => ($a['last_at']?->timestamp ?? 0) <=> ($b['last_at']?->timestamp ?? 0),
                fn ($a, $b) => $b['policy']->obligations_count <=> $a['policy']->obligations_count,
                fn ($a, $b) => strcmp($a['policy']->slug, $b['policy']->slug),
            ])
            ->values();
    }

    /**
     * Where to deepen next: watched instruments with few or no recorded duties. Depth goes
     * where readers already are, not where it is easiest to add.
     *
     * @param  ?Collection<int, array>  $rows  rows() when the caller already has them
     * @return Collection<int, array{policy: PolicyInstrument, watchers: int, obligations: int}>
     */
    public static function deepenNext(int $limit = 10, ?Collection $rows = null): Collection
    {
        return ($rows ?? self::rows())->filter(fn ($r) => $r['watchers'] > 0 && $r['policy']->obligations_count < 5)
            ->sortByDesc('watchers')->take($limit)
            ->map(fn ($r) => ['policy' => $r['policy'], 'watchers' => $r['watchers'], 'obligations' => (int) $r['policy']->obligations_count])->values();
    }
}
