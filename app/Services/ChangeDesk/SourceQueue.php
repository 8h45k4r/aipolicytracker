<?php

namespace App\Services\ChangeDesk;

use App\Enums\PolicyStatus;
use App\Models\Follow;
use App\Models\PolicyInstrument;
use App\Models\SourceCheck;
use Illuminate\Support\Collection;

/**
 * Which official sources are due a look, most urgent first. A binding instrument that is
 * proposed, adopted or applying is due weekly; anything else still current, monthly.
 * Records people watch come first among equals: that is where a missed change costs most.
 */
final class SourceQueue
{
    private const NOT_CURRENT = [PolicyStatus::Superseded, PolicyStatus::Repealed, PolicyStatus::Archived];

    /** @return Collection<int, array{policy: PolicyInstrument, cadence: int, last: ?SourceCheck, due: bool, overdue_days: ?int, watchers: int}> */
    public static function rows(): Collection
    {
        $watchers = Follow::query()->where('subject_type', 'policy')->selectRaw('subject_slug, count(*) as n')->groupBy('subject_slug')->pluck('n', 'subject_slug');
        // The newest check per instrument (a few hundred rows a year; no need for a window query).
        $checks = SourceCheck::query()->where('record_type', 'policy')->orderByDesc('checked_at')->orderByDesc('id')->get()->unique('record_slug')->keyBy('record_slug');

        return PolicyInstrument::published()->with('jurisdiction:id,slug,name,short_name')->withCount(['obligations' => fn ($q) => $q->whereNotNull('published_at')])
            ->get(['id', 'slug', 'aipt_id', 'title', 'short_title', 'status', 'is_binding', 'jurisdiction_id', 'official_source_url', 'last_verified_at'])
            ->reject(fn (PolicyInstrument $p) => in_array(PolicyStatus::tryFrom((string) $p->status), self::NOT_CURRENT, true))
            ->map(function (PolicyInstrument $p) use ($watchers, $checks) {
                $cadence = (int) config('changedesk.cadence_days.'.($p->is_binding ? 'binding' : 'other'));
                $last = $checks->get($p->slug);
                $lastAt = $last?->checked_at;
                $overdue = $lastAt ? (int) floor($lastAt->diffInDays(now())) - $cadence : null;

                return ['policy' => $p, 'cadence' => $cadence, 'last' => $last, 'due' => $lastAt === null || $overdue >= 0, 'overdue_days' => $overdue, 'watchers' => (int) ($watchers[$p->slug] ?? 0)];
            })
            ->sortBy([
                fn ($a, $b) => $b['due'] <=> $a['due'],
                fn ($a, $b) => $b['policy']->is_binding <=> $a['policy']->is_binding,
                fn ($a, $b) => $b['watchers'] <=> $a['watchers'],
                fn ($a, $b) => ($a['last']?->checked_at?->timestamp ?? 0) <=> ($b['last']?->checked_at?->timestamp ?? 0),
            ])
            ->values();
    }

    /**
     * Where to deepen next: watched instruments with few or no recorded duties. Depth goes
     * where readers already are, not where it is easiest to add.
     *
     * @return Collection<int, array{policy: PolicyInstrument, watchers: int, obligations: int}>
     */
    public static function deepenNext(int $limit = 10): Collection
    {
        return self::rows()->filter(fn ($r) => $r['watchers'] > 0 && $r['policy']->obligations_count < 5)
            ->sortByDesc('watchers')->take($limit)
            ->map(fn ($r) => ['policy' => $r['policy'], 'watchers' => $r['watchers'], 'obligations' => (int) $r['policy']->obligations_count])->values();
    }
}
