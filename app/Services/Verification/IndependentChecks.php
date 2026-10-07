<?php

namespace App\Services\Verification;

use App\Models\PolicyInstrument;
use Illuminate\Support\Collection;

/**
 * Reads the independent second checks recorded on published policy records and hands
 * them to AgreementStatistics. The public figure on /methodology comes from here.
 */
class IndependentChecks
{
    /** Published, verified policy records that carry an independent second check. */
    public function records(): Collection
    {
        return PolicyInstrument::published()
            ->where('review_status', 'verified')
            ->whereNotNull('second_review')
            ->orderBy('slug')
            ->get()
            ->filter(fn (PolicyInstrument $p) => $p->isDoubleChecked())
            ->values();
    }

    /**
     * Agreement statistics over the published double-checked records, plus the
     * population they were drawn from.
     *
     * @return array{n: int, min_sample: int, sufficient: bool, records_fully_agreed: int, fields: ?array, verified: int, sample_percent: int}
     */
    public function summary(): array
    {
        $stats = (new AgreementStatistics((int) config('verification.independent_checks.min_sample', 20)))
            ->compute($this->records()->map(fn (PolicyInstrument $p) => $p->secondReview())->all());

        return $stats + [
            'verified' => PolicyInstrument::published()->where('review_status', 'verified')->count(),
            'sample_percent' => (int) config('verification.independent_checks.sample_percent', 20),
        ];
    }
}
