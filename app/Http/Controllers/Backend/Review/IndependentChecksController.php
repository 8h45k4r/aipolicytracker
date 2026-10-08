<?php

namespace App\Http\Controllers\Backend\Review;

use App\Http\Controllers\Controller;
use App\Models\PolicyInstrument;
use App\Services\Report\StateOfAiRegulation;
use App\Services\Reviewers\ReviewerRoster;
use App\Services\Verification\IndependentChecks;
use App\Services\Verification\SecondReview;
use App\Services\Verification\VerificationSample;
use App\Support\Admin\CsvStream;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The quarterly independent second checks, from the reviewer's side: the quarter's
 * sample (drawn by the same service as `verification:sample`), how far through it the
 * second reviewers are, the agreement figures under the same threshold the public page
 * uses, and every disputed field recorded so far.
 *
 * Read-only. A second check is a `second_review` block in the policy YAML, added by pull
 * request and checked by policy:validate. The review queue never writes YAML from a
 * request (its decisions are exported by a command), so there is no form here either.
 */
class IndependentChecksController extends Controller
{
    public function index(Request $request, VerificationSample $sampler, IndependentChecks $checks, ReviewerRoster $roster): View
    {
        $sample = $sampler->draw($this->quarter($request));
        $rows = $this->rows($sample['slugs']);
        $double = $checks->records();

        return view('backend.review.independent-checks', [
            'sample' => $sample,
            'rows' => $rows,
            'checked' => $rows->where('double_checked', true)->count(),
            'summary' => $checks->summary(),
            'disputes' => $this->disputes($double),
            'reviewers' => $roster->published()->pluck('name')->filter()->values(),
            'quarters' => $this->recentQuarters(),
            'fields' => SecondReview::FIELDS,
        ]);
    }

    /** The sample as CSV, for second reviewers to work from. */
    public function export(Request $request, VerificationSample $sampler): StreamedResponse
    {
        $sample = $sampler->draw($this->quarter($request));

        return CsvStream::from($this->sampleQuery($sample['slugs']), 'independent-checks-'.$sample['quarter'],
            ['quarter', 'seed', 'slug', 'title', 'jurisdiction', 'record_url', 'official_source_url', 'first_reviewer', 'last_verified_at', 'second_reviewer', 'second_reviewed_on', 'agreed', 'fields_disputed'],
            function (PolicyInstrument $p) use ($sample) {
                $review = $p->secondReview();

                return [
                    $sample['quarter'], $sample['seed'], $p->slug, $p->title, $p->jurisdiction?->name, $p->url(), $p->official_source_url,
                    $p->reviewed_by, $p->last_verified_at?->toDateString(),
                    $review['reviewed_by'] ?? null, isset($review['reviewed_on']) ? (string) $review['reviewed_on'] : null,
                    $review === null ? null : (bool) ($review['agreed'] ?? false),
                    $review === null ? null : collect((array) ($review['fields_disputed'] ?? []))->pluck('field')->filter()->implode('; '),
                ];
            });
    }

    private function quarter(Request $request): string
    {
        $quarter = (string) $request->query('quarter', '');

        return StateOfAiRegulation::isValidQuarter($quarter) ? $quarter : StateOfAiRegulation::currentQuarter();
    }

    /** @param list<string> $slugs */
    private function sampleQuery(array $slugs): Builder
    {
        return PolicyInstrument::query()->with('jurisdiction')->whereIn('slug', $slugs)->orderBy('slug');
    }

    /**
     * One row per sampled record: who verified it, and the second check if there is one.
     *
     * @param  list<string>  $slugs
     */
    private function rows(array $slugs): Collection
    {
        return $this->sampleQuery($slugs)->get()->map(function (PolicyInstrument $p) {
            $review = $p->secondReview();
            // A second_review naming the first reviewer is not a second check; say so
            // rather than show it as missing.
            $sameReviewer = $review === null && is_array($p->second_review) && ! empty($p->second_review['reviewed_by']);

            return [
                'slug' => $p->slug,
                'title' => $p->short_title ?: $p->title,
                'url' => $p->url(),
                'source' => $p->official_source_url,
                'jurisdiction' => $p->jurisdiction?->name,
                'first_reviewer' => $p->reviewed_by,
                'last_verified_at' => $p->last_verified_at,
                'review' => $review,
                'same_reviewer' => $sameReviewer,
                'double_checked' => $p->isDoubleChecked(),
                'disputed' => collect((array) ($review['fields_disputed'] ?? []))->pluck('field')->filter()->values()->all(),
            ];
        });
    }

    /**
     * Every disputed field on a double-checked record, unresolved first.
     *
     * @return Collection<int, array{record: PolicyInstrument, review: array, field: string, label: string, first: mixed, second: mixed, note: ?string, resolution: ?string}>
     */
    private function disputes(Collection $records): Collection
    {
        return $records->flatMap(function (PolicyInstrument $p) {
            $review = (array) $p->secondReview();

            return collect((array) ($review['fields_disputed'] ?? []))->filter(fn ($d) => is_array($d) && isset($d['field']))
                ->map(fn (array $d) => [
                    'record' => $p,
                    'review' => $review,
                    'field' => (string) $d['field'],
                    'label' => SecondReview::FIELDS[$d['field']]['label'] ?? (string) $d['field'],
                    'first' => $d['first'] ?? null,
                    'second' => ((array) ($review['coded'] ?? []))[$d['field']] ?? null,
                    'note' => $d['note'] ?? null,
                    'resolution' => $d['resolution'] ?? null,
                ]);
        })->sortBy(fn ($d) => (filled($d['resolution']) ? '1' : '0').$d['record']->slug.$d['field'])->values();
    }

    /** The current quarter and the three before it, for the quarter switch. @return list<string> */
    private function recentQuarters(): array
    {
        $now = now()->toImmutable();

        return array_map(fn (int $i) => StateOfAiRegulation::currentQuarter($now->subQuarters($i)), range(0, 3));
    }
}
