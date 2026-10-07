<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\EnforcementEvent;
use App\Models\Jurisdiction;
use App\Services\Implementation\Trackers;
use App\Support\PageTitle;
use App\Support\Seo;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * /enforcement: every enforcement event recorded against any instrument, with
 * filters by jurisdiction, kind and year. The hub is thin until enough events are
 * recorded, so it stays noindex (and out of the sitemap) below the threshold.
 */
class EnforcementController extends Controller
{
    public function index(Request $request): View
    {
        $filters = [
            'jurisdiction' => preg_match('/^[a-z0-9-]{1,120}$/', (string) $request->query('jurisdiction')) ? $request->query('jurisdiction') : null,
            'kind' => array_key_exists((string) $request->query('kind'), EnforcementEvent::KINDS) ? $request->query('kind') : null,
            'year' => preg_match('/^(19|20)\d{2}$/', (string) $request->query('year')) ? (int) $request->query('year') : null,
        ];
        $all = Trackers::enforcementQuery()->get();
        $events = Trackers::enforcementQuery($filters)->get();
        $recorded = $all->reject->isDraft();
        $verified = $recorded->filter->isVerified();
        $jurisdictions = Jurisdiction::whereIn('id', $all->pluck('jurisdiction_id')->unique())->orderBy('name')->get();
        $years = $all->pluck('occurred_on')->filter()->map->format('Y')->unique()->sortDesc()->values();
        $byKind = $events->groupBy(fn ($e) => $e->kindLabel() ?? 'Not stated')->map->count()->all();

        $answer = $all->isEmpty()
            ? 'No enforcement action is recorded yet. An action enters this list only once it has been read from the regulator\'s or court\'s own publication; until then the list stays empty rather than repeating press reports.'
            : sprintf(
                '%d enforcement %s recorded across %d %s: %d verified against the official source by a named reviewer, %d awaiting that review. Kinds: %s.',
                $all->count(), Str::plural('action', $all->count()), $jurisdictions->count(), Str::plural('jurisdiction', $jurisdictions->count()),
                $verified->count(), $recorded->count() - $verified->count(),
                collect($all->groupBy(fn ($e) => $e->kindLabel() ?? 'not stated')->map->count())->map(fn ($n, $k) => mb_strtolower($k).' ('.$n.')')->join(', ')
            );
        $filtered = array_filter($filters) !== [];
        $indexable = ! $filtered && $recorded->count() >= Trackers::MIN_INDEXABLE_ENFORCEMENT;
        $title = 'AI Enforcement Tracker: Fines, Orders and Court Decisions';
        $seo = Seo::make(PageTitle::fit($title), PageTitle::description($answer), route('enforcement.index'), $indexable)
            ->withBreadcrumbs([['Home', route('home')], ['Enforcement', route('enforcement.index')]])
            ->withModified($all->max('updated_at'))
            ->withPageType('CollectionPage', ['name' => $title, 'description' => $answer])
            ->withJsonLd(Seo::dataset('AI enforcement actions', 'Fines, orders, warnings, settlements, court decisions and annulments under recorded AI and data laws, each with regulator, respondent, legal basis, outcome, appeal status and the official source.', route('enforcement.index'), ['application/json' => route('api.v1.enforcement'), 'text/csv' => route('open-data.csv', 'enforcement'), 'application/x-ndjson' => route('open-data.ndjson', 'enforcement')], $all->max('updated_at')))
            ->withFaq([
                ['question' => 'What counts as an enforcement action here?', 'answer' => 'A fine, order, warning, settlement, court decision or annulment by a regulator or court under an instrument this site records, read from the regulator\'s or court\'s own publication. Each carries the regulator, the organisation acted against, the provision relied on, the amount where one was published, the outcome and whether it was appealed.'],
                ['question' => 'Why is an amount missing?', 'answer' => 'Because the official source did not state one. A missing amount is shown as a dash, never estimated.'],
            ]);
        if (! $indexable) {
            $seo->noindex();
        }

        return view('site.enforcement.index', compact('seo', 'filters', 'events', 'all', 'recorded', 'verified', 'jurisdictions', 'years', 'byKind', 'answer'));
    }
}
