<?php

namespace App\Services\Deadlines;

use App\Models\Deadline;
use App\Models\DeadlineRevision;
use App\Models\Jurisdiction;
use App\Models\TaxonomyTerm;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Which recorded dates apply to one organisation: its markets, its role, the
 * kind of system, its sector and use case. Every row is a Deadline on record,
 * filtered by the terms and applicability rules on its instrument and, when
 * it has one, its obligation. Nothing is computed that is not in /deadlines;
 * the engine only decides which of them to show and why.
 */
final class DeadlineEngine
{
    public const STEPS = 5;

    /** @return array{jurisdictions:list<string>, role:?string, system_types:list<string>, risk:?string, sectors:list<string>, use_cases:list<string>} */
    public function normalise(array $input): array
    {
        $slugs = fn ($v) => array_values(array_unique(array_filter(array_map('strval', (array) ($v ?? [])), fn ($s) => preg_match('/^[a-z0-9_-]{1,64}$/', $s) === 1)));
        $one = fn ($v) => is_string($v) && preg_match('/^[a-z0-9_-]{1,64}$/', $v) ? $v : null;

        return [
            'jurisdictions' => array_slice($slugs($input['jurisdictions'] ?? []), 0, 8),
            'role' => $one($input['role'] ?? null),
            'system_types' => array_slice($slugs($input['system_types'] ?? []), 0, 8),
            'risk' => $one($input['risk'] ?? null),
            'sectors' => array_slice($slugs($input['sectors'] ?? []), 0, 8),
            'use_cases' => array_slice($slugs($input['use_cases'] ?? []), 0, 8),
        ];
    }

    /** The step the answers have reached: the first one still unanswered, capped at the result. */
    public function step(array $a, ?int $requested = null): int
    {
        if ($a['jurisdictions'] === []) {
            return 1;
        }
        $step = $requested ? max(1, min(self::STEPS, $requested)) : 1;

        return $step;
    }

    /**
     * @return Collection<int, array{deadline:Deadline, why:list<string>, level:string, revision:?DeadlineRevision}>
     */
    public function applicable(array $a): Collection
    {
        if ($a['jurisdictions'] === []) {
            return collect();
        }
        $jurisdictionIds = Jurisdiction::published()->whereIn('slug', $a['jurisdictions'])->pluck('id');
        $deadlines = Deadline::query()
            ->with(['policyInstrument.jurisdiction', 'policyInstrument.terms', 'obligation.terms', 'obligation.applicabilityRules'])
            ->whereHas('policyInstrument', fn ($q) => $q->published()->whereIn('jurisdiction_id', $jurisdictionIds))
            ->whereIn('deadline_status', ['scheduled', 'tbd', 'passed'])
            ->orderByRaw('due_on is null, due_on')->orderBy('sort_order')
            ->get();
        $revisions = DeadlineRevision::whereIn('policy_instrument_id', $deadlines->pluck('policy_instrument_id')->unique())->orderByDesc('changed_at')->get()->groupBy(fn ($r) => $r->policy_instrument_id.'|'.$r->deadline_key);

        return $deadlines->map(function (Deadline $d) use ($a, $revisions) {
            $match = $this->match($d, $a);
            if ($match === null) {
                return null;
            }

            return ['deadline' => $d, 'why' => $match['why'], 'level' => $match['level'], 'revision' => $revisions->get($d->policy_instrument_id.'|'.DeadlineRevision::keyFor($d->title))?->first()];
        })->filter()->values();
    }

    /**
     * Whether a deadline applies, and why. An instrument that names no actor,
     * system type, sector or use case is taken to apply generally; a term list
     * that names some, and not the answer, excludes it. A deadline tied to an
     * obligation is judged on the obligation's terms and rules, which are the
     * more specific.
     *
     * @return array{why:list<string>, level:string}|null
     */
    private function match(Deadline $d, array $a): ?array
    {
        $p = $d->policyInstrument;
        $o = $d->obligation;
        $terms = $o && $o->terms->isNotEmpty() ? $o->terms : $p->terms;
        $level = $o ? 'obligation' : 'instrument';
        $why = [];

        $check = function (string $taxonomy, array $wanted, string $reason, array $wildcards = []) use ($terms, &$why): bool {
            $listed = $terms->where('taxonomy', $taxonomy)->pluck('slug')->all();
            if ($listed === [] || $wanted === []) {
                return true;
            }
            $hit = array_intersect($listed, array_merge($wanted, $wildcards));
            if ($hit === []) {
                return false;
            }
            $why[] = $reason;

            return true;
        };
        if (! $check('actor', array_filter([$a['role']]), 'names your role')) {
            return null;
        }
        if (! $check('ai_system_type', $a['system_types'], 'covers this kind of system')) {
            return null;
        }
        if (! $check('risk_category', array_filter([$a['risk']]), 'matches the risk tier')) {
            return null;
        }
        if (! $check('sector', $a['sectors'], 'applies to your sector', ['cross_sector'])) {
            return null;
        }
        if (! $check('use_case', $a['use_cases'], 'covers your use case')) {
            return null;
        }

        // Applicability rules on the obligation are the record's own words on who it binds.
        if ($o && $o->applicabilityRules->isNotEmpty()) {
            $ruleOk = $o->applicabilityRules->contains(function ($r) use ($a) {
                $any = fn (?array $list, array $wanted) => empty($list) || $wanted === [] || array_intersect($list, $wanted) !== [];

                return $any($r->actors, array_filter([$a['role']])) && $any($r->ai_system_types, $a['system_types']) && $any($r->sectors, $a['sectors']) && $any($r->risk_categories, array_filter([$a['risk']])) && $any($r->use_cases, $a['use_cases']);
            });
            if (! $ruleOk) {
                return null;
            }
            $why[] = 'an applicability rule on the duty matches';
        }
        if ($why === []) {
            $why[] = $o ? 'the duty names no narrower scope' : 'the instrument names no narrower scope';
        }

        return ['why' => $why, 'level' => $level];
    }

    /** @return array<string, array<string,string>> the answer options, from the taxonomies and the published jurisdictions */
    public function options(): array
    {
        $terms = fn (string $t) => TaxonomyTerm::where('taxonomy', $t)->orderBy('sort_order')->orderBy('name')->pluck('name', 'slug')->all();

        return [
            'roles' => $terms('actor'),
            'system_types' => $terms('ai_system_type'),
            'risks' => $terms('risk_category'),
            'sectors' => $terms('sector'),
            'use_cases' => $terms('use_case'),
        ];
    }

    /** A one-paragraph, computed summary of a result. */
    public function summary(Collection $rows, array $a, array $labels): string
    {
        $dated = $rows->filter(fn ($r) => $r['deadline']->due_on !== null);
        $future = $dated->filter(fn ($r) => $r['deadline']->due_on->isFuture());
        $next = $future->first();
        $places = collect($a['jurisdictions'])->map(fn ($s) => $labels['jurisdictions'][$s] ?? $s)->join(', ', ' and ');

        $text = sprintf('%d recorded %s %s for %s', $rows->count(), Str::plural('date', $rows->count()), $rows->count() === 1 ? 'applies' : 'apply', $places ?: 'the selected jurisdictions');
        if ($a['role'] && isset($labels['roles'][$a['role']])) {
            $text .= ' in the role of '.mb_strtolower($labels['roles'][$a['role']]);
        }
        $text .= sprintf(': %d in the future, %d passed, %d not yet dated.', $future->count(), $dated->count() - $future->count(), $rows->count() - $dated->count());
        if ($next) {
            $text .= sprintf(' The next is %s: %s (%s).', $next['deadline']->due_on->format('j F Y'), $next['deadline']->title, $next['deadline']->policyInstrument->short_title ?: $next['deadline']->policyInstrument->title);
        }
        $moved = $rows->filter(fn ($r) => $r['revision'] !== null);
        if ($moved->isNotEmpty()) {
            $text .= sprintf(' %d of these %s moved since first recorded; each shows the original date.', $moved->count(), $moved->count() === 1 ? 'has' : 'have');
        }

        return $text;
    }
}
