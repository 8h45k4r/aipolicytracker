<?php

namespace App\Services\Completeness;

use App\Models\ChangeEvent;
use App\Models\Jurisdiction;
use App\Models\Obligation;
use App\Models\PolicyInstrument;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Applies config/completeness.php to the published corpus: which records are
 * missing something a published record ought to carry, and how much.
 *
 * Deliberately separate from VerificationPolicy. That one asks how old a fact
 * is; this one asks whether the fact was ever recorded. A record can pass one
 * and fail the other, and a reader is entitled to both numbers.
 *
 * Nothing here writes. It reports, the page publishes it and the command gates
 * on it, all from this single pass so the three can never disagree.
 */
class CompletenessReport
{
    /** Record kinds under the policy, in the order the pages list them. */
    public const KINDS = [
        'policy' => PolicyInstrument::class,
        'obligation' => Obligation::class,
        'change' => ChangeEvent::class,
        'jurisdiction' => Jurisdiction::class,
    ];

    /** @var Collection<int, array{kind: string, record: Model, check: array}>|null */
    private ?Collection $gaps = null;

    /** @var array<string, int>|null */
    private ?array $totals = null;

    /** Records each check was actually run against, keyed by check id. @var array<string, int>|null */
    private ?array $applicable = null;

    /** @return array<string, array{label: string, plural: string}> */
    public function kinds(): array
    {
        return (array) config('completeness.kinds', []);
    }

    /** @return list<array{id: string, kind: string, field: string, label: string, why: string, severity: string, missing: callable, applies?: callable}> */
    public function checks(): array
    {
        return (array) config('completeness.checks', []);
    }

    /** @return list<array> The checks that apply to one record kind. */
    public function checksFor(string $kind): array
    {
        return array_values(array_filter($this->checks(), fn ($c) => $c['kind'] === $kind));
    }

    public function check(string $id): ?array
    {
        foreach ($this->checks() as $check) {
            if ($check['id'] === $id) {
                return $check;
            }
        }

        return null;
    }

    /**
     * One row per failed check, walked once and cached for the request.
     *
     * A check that does not apply to a record produces no row at all, rather
     * than a passing row: a strategy document is not "missing" obligations it
     * was never meant to carry, and counting it as such would inflate the
     * denominator and flatter the percentage.
     *
     * @return Collection<int, array{kind: string, record: Model, check: array}>
     */
    public function gaps(): Collection
    {
        if ($this->gaps !== null) {
            return $this->gaps;
        }

        $gaps = collect();
        $totals = [];
        $applicable = array_fill_keys(array_column($this->checks(), 'id'), 0);

        foreach (self::KINDS as $kind => $class) {
            /** @var class-string<Model> $class */
            $checks = $this->checksFor($kind);
            $records = $class::published()->get();
            $totals[$kind] = $records->count();

            foreach ($records as $record) {
                foreach ($checks as $check) {
                    if (isset($check['applies']) && ! ($check['applies'])($record)) {
                        continue;
                    }
                    $applicable[$check['id']]++;
                    if (($check['missing'])($record)) {
                        $gaps->push(['kind' => $kind, 'record' => $record, 'check' => $check]);
                    }
                }
            }
        }

        $this->totals = $totals;
        $this->applicable = $applicable;

        return $this->gaps = $gaps;
    }

    /** Published record counts per kind. @return array<string, int> */
    public function totals(): array
    {
        $this->gaps();

        return $this->totals ?? [];
    }

    /**
     * The corpus report: totals, per-kind and per-check breakdowns, and the verdict.
     *
     * `complete` counts records with no gap of any severity, so the headline
     * number is the strict one. Every other number below it explains the gap.
     *
     * @return array{
     *     records: int, complete: int, incomplete: int, required_gaps: int, expected_gaps: int,
     *     budget: int, pass: bool,
     *     kinds: list<array{id: string, label: string, plural: string, records: int, complete: int, required_gaps: int, expected_gaps: int}>,
     *     checks: list<array{id: string, kind: string, label: string, why: string, severity: string, field: string, applicable: int, missing: int}>
     * }
     */
    public function report(): array
    {
        $gaps = $this->gaps();
        $totals = $this->totals();
        $budget = (int) config('completeness.required_budget', 0);
        $required = $gaps->where('check.severity', 'required')->count();

        $kinds = [];
        foreach ($this->kinds() as $id => $meta) {
            $own = $gaps->where('kind', $id);
            $records = $totals[$id] ?? 0;
            $kinds[] = [
                'id' => $id,
                'label' => $meta['label'],
                'plural' => $meta['plural'],
                'records' => $records,
                'complete' => $records - $own->pluck('record')->map(fn ($r) => $r->getKey())->unique()->count(),
                'required_gaps' => $own->where('check.severity', 'required')->count(),
                'expected_gaps' => $own->where('check.severity', 'expected')->count(),
            ];
        }

        $checks = [];
        foreach ($this->checks() as $check) {
            $missing = $gaps->where('check.id', $check['id']);
            $checks[] = [
                'id' => $check['id'],
                'kind' => $check['kind'],
                'label' => $check['label'],
                'why' => $check['why'],
                'severity' => $check['severity'],
                'field' => $check['field'],
                // Records the check was actually run against, so "12 of 40" is honest
                // for a check that only applies to some of them.
                'applicable' => $this->applicable[$check['id']] ?? 0,
                'missing' => $missing->count(),
            ];
        }

        $records = array_sum($totals);
        $incomplete = $gaps->map(fn ($g) => $g['kind'].':'.$g['record']->getKey())->unique()->count();

        return [
            'records' => $records,
            'complete' => $records - $incomplete,
            'incomplete' => $incomplete,
            'required_gaps' => $required,
            'expected_gaps' => $gaps->where('check.severity', 'expected')->count(),
            'budget' => $budget,
            'pass' => $required <= $budget,
            'kinds' => $kinds,
            'checks' => $checks,
        ];
    }

    /**
     * The queue: gaps in the order they should be worked.
     *
     * Required before expected, then in the file's check order, so the list a
     * contributor sees is the list an editor would work, not an arbitrary one.
     *
     * @return Collection<int, array{kind: string, record: Model, check: array}>
     */
    public function queue(?string $kind = null, ?string $checkId = null): Collection
    {
        $order = array_flip(array_column($this->checks(), 'id'));

        return $this->gaps()
            ->when($kind, fn ($c) => $c->where('kind', $kind))
            ->when($checkId, fn ($c) => $c->where('check.id', $checkId))
            ->sortBy([
                fn ($a, $b) => ($a['check']['severity'] === 'required' ? 0 : 1) <=> ($b['check']['severity'] === 'required' ? 0 : 1),
                fn ($a, $b) => ($order[$a['check']['id']] ?? 99) <=> ($order[$b['check']['id']] ?? 99),
            ])
            ->values();
    }
}
