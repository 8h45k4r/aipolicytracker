<?php

namespace App\Services\Alerts;

use App\Models\ApplicabilityProfile;
use App\Models\ChangeEvent;
use App\Models\Deadline;
use App\Models\Follow;
use App\Models\Jurisdiction;
use App\Models\Obligation;
use App\Models\PolicyInstrument;
use App\Models\User;
use App\Services\Applicability\ApplicabilityScreener;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Resolves what a user follows, and the applicability profiles they saved, into
 * the published changes and upcoming application dates that concern them.
 *
 * A followed jurisdiction matches every change recorded for it; a followed
 * policy matches its own changes and deadlines; a followed obligation matches
 * its instrument's changes and its own deadlines. A saved profile matches
 * changes in the jurisdictions it covers and in the instruments the same screen
 * the user saw scored above zero, so the alert can say which described system a
 * change may touch. Screening is relevance, never a legal determination.
 */
class AlertBuilder
{
    /** Days ahead within which application dates are listed. */
    public const DEADLINE_HORIZON_DAYS = 30;

    /** Days-before milestones on which a deadline alone justifies an email. */
    public const DEADLINE_MILESTONES = [30, 7, 1];

    public function __construct(private ApplicabilityScreener $screener) {}

    /**
     * @return array{changes: Collection<int, ChangeEvent>, deadlines: Collection<int, Deadline>, milestone: bool, follows: int, profiles: Collection<int, ApplicabilityProfile>, reasons: array<int, list<string>>}
     */
    public function build(User $user, CarbonInterface $since, CarbonInterface $until): array
    {
        $follows = Follow::where('user_id', $user->id)->get();
        $profiles = ApplicabilityProfile::where('user_id', $user->id)->get();
        $empty = ['changes' => collect(), 'deadlines' => collect(), 'milestone' => false, 'follows' => $follows->count(), 'profiles' => $profiles, 'reasons' => []];

        [$jurisdictionIds, $instrumentIds, $obligationIds] = $this->followedIds($follows);
        $profileScope = [];
        foreach ($profiles as $profile) {
            $watched = $profile->watchedIds($this->screener);
            $profileScope[$profile->id] = $watched;
            $jurisdictionIds = array_merge($jurisdictionIds, $watched['jurisdiction_ids']);
            $instrumentIds = array_merge($instrumentIds, $watched['instrument_ids']);
        }
        $jurisdictionIds = array_values(array_unique($jurisdictionIds));
        $instrumentIds = array_values(array_unique($instrumentIds));
        if ($jurisdictionIds === [] && $instrumentIds === [] && $obligationIds === []) {
            return $empty;
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

        return [
            'changes' => $changes,
            'deadlines' => $deadlines,
            'milestone' => $milestone,
            'follows' => $follows->count(),
            'profiles' => $profiles,
            'reasons' => $this->reasons($changes, $profiles, $profileScope),
        ];
    }

    /**
     * Obligations a profile flags for a change, so the email can say what to review.
     *
     * @return Collection<int, Obligation>
     */
    public function obligationsFor(ApplicabilityProfile $profile, ChangeEvent $change): Collection
    {
        $screen = $this->screener->screen($profile->answers);

        return $screen['obligations']->filter(fn (Obligation $o) => $o->policy_instrument_id === $change->policy_instrument_id)->values();
    }

    /** @return array{0: list<int>, 1: list<int>, 2: list<int>} jurisdiction, instrument and obligation ids from follows */
    private function followedIds(Collection $follows): array
    {
        $jurisdictionIds = $instrumentIds = $obligationIds = [];
        foreach ($follows->groupBy('subject_type') as $type => $rows) {
            $slugs = $rows->pluck('subject_slug')->all();
            match ($type) {
                'jurisdiction' => $jurisdictionIds = Jurisdiction::published()->whereIn('slug', $slugs)->pluck('id')->all(),
                'policy' => $instrumentIds = PolicyInstrument::published()->whereIn('slug', $slugs)->pluck('id')->all(),
                'obligation' => [$obligationIds, $instrumentIds] = $this->obligations($slugs, $instrumentIds),
                default => null,
            };
        }

        return [$jurisdictionIds, $instrumentIds, $obligationIds];
    }

    /**
     * Why each change is in the alert: the profiles whose scope it falls in.
     *
     * @return array<int, list<string>> change id => profile names
     */
    private function reasons(Collection $changes, Collection $profiles, array $profileScope): array
    {
        $reasons = [];
        foreach ($changes as $change) {
            foreach ($profiles as $profile) {
                $scope = $profileScope[$profile->id] ?? null;
                if (! $scope) {
                    continue;
                }
                $inScope = in_array($change->policy_instrument_id, $scope['instrument_ids'], true)
                    || in_array($change->jurisdiction_id, $scope['jurisdiction_ids'], true);
                if ($inScope) {
                    $reasons[$change->id][] = $profile->name;
                }
            }
        }

        return $reasons;
    }

    /** @return array{0: list<int>, 1: list<int>} obligation ids and the instrument ids they belong to */
    private function obligations(array $slugs, array $instrumentIds): array
    {
        $rows = Obligation::published()->whereIn('slug', $slugs)->get(['id', 'policy_instrument_id']);

        return [$rows->pluck('id')->all(), array_values(array_unique(array_merge($instrumentIds, $rows->pluck('policy_instrument_id')->filter()->all())))];
    }
}
