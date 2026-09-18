<?php

namespace App\Services\Verification;

use App\Models\ChangeEvent;
use App\Models\Jurisdiction;
use App\Models\Obligation;
use App\Models\PolicyInstrument;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Applies config/verification.php to the published corpus: which rule owns a
 * record, how old its last check is, and whether it is overdue.
 *
 * The point of the policy is that staleness is measured and published rather
 * than discovered by a reader. Nothing here changes a record; it only reports.
 */
class VerificationPolicy
{
    /** Record kinds under the policy, in the order the pages list them. */
    public const KINDS = [
        'policy' => PolicyInstrument::class,
        'obligation' => Obligation::class,
        'change' => ChangeEvent::class,
        'jurisdiction' => Jurisdiction::class,
    ];

    /** @return array<string, array{label: string, owner: string}> */
    public function tracks(): array
    {
        return (array) config('verification.tracks', []);
    }

    /** @return list<array{id: string, label: string, track: string, days: int, critical: bool, applies: callable}> */
    public function rules(): array
    {
        return VerificationRuleset::rules();
    }

    /** The first rule that claims this record, or null when the policy does not cover it. */
    public function ruleFor(Model $record): ?array
    {
        foreach ($this->rules() as $rule) {
            if (($rule['applies'])($record)) {
                return $rule;
            }
        }

        return null;
    }

    /** Days since the record's facts were last confirmed, or null when never confirmed. */
    public function ageInDays(Model $record): ?int
    {
        $verified = $record->last_verified_at ?? null;

        return $verified ? (int) $verified->startOfDay()->diffInDays(now()->startOfDay()) : null;
    }

    /**
     * One row per published record under the policy.
     *
     * @return Collection<int, array{kind: string, record: Model, rule: array, age: ?int, overdue: bool, never: bool}>
     */
    public function assess(): Collection
    {
        $rows = collect();
        foreach (self::KINDS as $kind => $class) {
            /** @var class-string<Model> $class */
            foreach ($class::published()->get() as $record) {
                $rule = $this->ruleFor($record);
                if (! $rule) {
                    continue;
                }
                $age = $this->ageInDays($record);
                $rows->push([
                    'kind' => $kind,
                    'record' => $record,
                    'rule' => $rule,
                    'age' => $age,
                    'never' => $age === null,
                    // Never confirmed counts as overdue: an unchecked fact is not a fresh one.
                    'overdue' => $age === null || $age > (int) $rule['days'],
                ]);
            }
        }

        return $rows;
    }

    /**
     * Policy report: totals, per-rule and per-track breakdowns, and the overdue records.
     *
     * @return array{
     *     covered: int, overdue: int, critical_overdue: int, never: int, budget: int, pass: bool,
     *     rules: list<array{id: string, label: string, track: string, days: int, critical: bool, covered: int, overdue: int, never: int}>,
     *     tracks: array<string, array{label: string, owner: string, covered: int, overdue: int}>,
     *     stale: Collection
     * }
     */
    public function report(): array
    {
        $rows = $this->assess();
        $budget = (int) config('verification.critical_budget', 0);
        $criticalOverdue = $rows->filter(fn ($r) => $r['overdue'] && $r['rule']['critical'])->count();

        $rules = [];
        foreach ($this->rules() as $rule) {
            $own = $rows->where('rule.id', $rule['id']);
            $rules[] = [
                'id' => $rule['id'],
                'label' => $rule['label'],
                'track' => $rule['track'],
                'days' => (int) $rule['days'],
                'critical' => (bool) $rule['critical'],
                'covered' => $own->count(),
                'overdue' => $own->where('overdue', true)->count(),
                'never' => $own->where('never', true)->count(),
            ];
        }

        $tracks = [];
        foreach ($this->tracks() as $id => $meta) {
            $own = $rows->where('rule.track', $id);
            $tracks[$id] = $meta + ['covered' => $own->count(), 'overdue' => $own->where('overdue', true)->count()];
        }

        return [
            'covered' => $rows->count(),
            'overdue' => $rows->where('overdue', true)->count(),
            'critical_overdue' => $criticalOverdue,
            'never' => $rows->where('never', true)->count(),
            'budget' => $budget,
            'pass' => $criticalOverdue <= $budget,
            'rules' => $rules,
            'tracks' => $tracks,
            'stale' => $rows->where('overdue', true)->sortByDesc(fn ($r) => $r['age'] ?? PHP_INT_MAX)->values(),
        ];
    }
}
