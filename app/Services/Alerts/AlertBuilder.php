<?php

namespace App\Services\Alerts;

use App\Models\ChangeEvent;
use App\Models\Deadline;
use App\Models\Follow;
use App\Models\Obligation;
use App\Models\PolicyInstrument;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Resolves what a user follows into the published changes and upcoming
 * application dates that concern those records. A followed jurisdiction
 * matches every change recorded for it; a followed policy matches its own
 * changes and deadlines; a followed obligation matches its instrument's
 * changes and its own deadlines.
 */
class AlertBuilder
{
    /** Days ahead within which application dates are listed. */
    public const DEADLINE_HORIZON_DAYS = 30;

    /** Days-before milestones on which a deadline alone justifies an email. */
    public const DEADLINE_MILESTONES = [30, 7, 1];

    /**
     * @return array{changes: Collection<int, ChangeEvent>, deadlines: Collection<int, Deadline>, milestone: bool, follows: int}
     */
    public function build(User $user, CarbonInterface $since, CarbonInterface $until): array
    {
        $follows = Follow::where('user_id', $user->id)->get();
        $jurisdictionIds = [];
        $instrumentIds = [];
        $obligationIds = [];
        foreach ($follows->groupBy('subject_type') as $type => $rows) {
            $slugs = $rows->pluck('subject_slug')->all();
            match ($type) {
                'jurisdiction' => $jurisdictionIds = \App\Models\Jurisdiction::published()->whereIn('slug', $slugs)->pluck('id')->all(),
                'policy' => $instrumentIds = PolicyInstrument::published()->whereIn('slug', $slugs)->pluck('id')->all(),
                'obligation' => [$obligationIds, $instrumentIds] = $this->obligations($slugs, $instrumentIds),
                default => null,
            };
        }
        if ($jurisdictionIds === [] && $instrumentIds === [] && $obligationIds === []) {
            return ['changes' => collect(), 'deadlines' => collect(), 'milestone' => false, 'follows' => $follows->count()];
        }

        $changes = ChangeEvent::published()->with(['jurisdiction', 'policyInstrument'])
            ->whereDate('occurred_on', '>', $since->toDateString())->whereDate('occurred_on', '<=', $until->toDateString())
            ->where(function ($q) use ($jurisdictionIds, $instrumentIds) {
                $q->whereRaw('1 = 0');
                if ($jurisdictionIds !== []) {
                    $q->orWhereIn('jurisdiction_id', $jurisdictionIds);
                }
                if ($instrumentIds !== []) {
                    $q->orWhereIn('policy_instrument_id', $instrumentIds);
                }
            })->orderByDesc('occurred_on')->orderByDesc('id')->get();

        $deadlines = Deadline::with('policyInstrument.jurisdiction')
            ->whereDate('due_on', '>=', $until->toDateString())->whereDate('due_on', '<=', $until->copy()->addDays(self::DEADLINE_HORIZON_DAYS)->toDateString())
            ->where(function ($q) use ($instrumentIds, $obligationIds) {
                $q->whereRaw('1 = 0');
                if ($instrumentIds !== []) {
                    $q->orWhereIn('policy_instrument_id', $instrumentIds);
                }
                if ($obligationIds !== []) {
                    $q->orWhereIn('obligation_id', $obligationIds);
                }
            })->orderBy('due_on')->get();

        $milestone = $deadlines->contains(fn (Deadline $d) => in_array((int) $until->copy()->startOfDay()->diffInDays($d->due_on->copy()->startOfDay(), false), self::DEADLINE_MILESTONES, true));

        return ['changes' => $changes, 'deadlines' => $deadlines, 'milestone' => $milestone, 'follows' => $follows->count()];
    }

    /** @return array{0: list<int>, 1: list<int>} obligation ids and the instrument ids they belong to (merged into $instrumentIds) */
    private function obligations(array $slugs, array $instrumentIds): array
    {
        $rows = Obligation::published()->whereIn('slug', $slugs)->get(['id', 'policy_instrument_id']);

        return [$rows->pluck('id')->all(), array_values(array_unique(array_merge($instrumentIds, $rows->pluck('policy_instrument_id')->filter()->all())))];
    }
}
