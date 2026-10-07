<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\ImplementationMeasure;
use App\Models\PolicyInstrument;
use App\Services\Implementation\Trackers;
use App\Support\PageTitle;
use App\Support\Seo;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * The implementation tracker (/policies/{policy}/implementation): the guidelines,
 * codes of practice, delegated and implementing acts and templates an instrument
 * depends on, each with its due date, actual dates and a status in which "overdue"
 * is derived from today. And the standards tracker (/ai-standards): harmonised
 * standards and ISO/IEC work items, metadata only.
 *
 * Both are thin until enough recorded (non-draft) entries exist, and stay noindex
 * until then.
 */
class ImplementationController extends Controller
{
    public function policy(string $policy): View
    {
        $instrument = PolicyInstrument::published()->where('slug', $policy)->with(['jurisdiction', 'deadlines'])->first();
        abort_unless($instrument, 404);
        $measures = Trackers::measures(['instrument' => $instrument->slug])->reject->isStandard()->values();
        abort_if($measures->isEmpty(), 404);

        $counts = $this->counts($measures);
        $name = $instrument->short_title ?: $instrument->title;
        $answer = sprintf(
            '%d implementation %s tracked for the %s: %d recorded from a cited source (%d verified by a named reviewer) and %d still in draft. %s',
            $measures->count(), Str::plural('measure', $measures->count()), $name, $counts['recorded'], $counts['verified'], $counts['drafts'],
            $counts['overdue'] > 0 ? $counts['overdue'].' '.($counts['overdue'] === 1 ? 'is' : 'are').' overdue: the date the instrument sets has passed with nothing adopted.' : 'None is overdue on the dates recorded.'
        );
        $url = route('policies.implementation', $instrument->slug);
        $indexable = Trackers::implementationIndexable($instrument);
        $title = $name.' Implementation Tracker: Guidelines, Codes, Acts';
        $seo = Seo::make(PageTitle::fit($title), PageTitle::description($answer), $url, $indexable)
            ->withBreadcrumbs([['Home', route('home')], ['Policies', route('policies.index')], [$name, $instrument->url()], ['Implementation', $url]])
            ->withModified($measures->max('updated_at'))
            ->withPageType('CollectionPage', ['name' => $title, 'description' => $answer, 'about' => ['@type' => 'Legislation', 'name' => $instrument->title]])
            ->withJsonLd(Seo::dataset($name.' implementation measures', 'Delegated and implementing acts, guidelines, codes of practice, templates and AI Board outputs for the '.$name.', with due and actual dates and a derived status.', $url, ['application/json' => route('api.v1.implementation', ['instrument' => $instrument->slug]), 'text/csv' => route('open-data.csv', 'implementation')], $measures->max('updated_at')))
            ->withFaq([
                ['question' => 'When is a measure overdue?', 'answer' => 'When the date the instrument sets for it has passed and it has been neither adopted nor published. The status is computed from the recorded dates each time the page is served, so it never lags the calendar.'],
                ['question' => 'Why are some entries drafts?', 'answer' => 'A draft is listed so the gap is visible: nothing about it has yet been read from an official source, so its dates and status are empty rather than guessed.'],
            ]);
        if (! $indexable) {
            $seo->noindex();
        }
        $deadlines = $instrument->deadlines;

        return view('site.implementation.show', compact('seo', 'instrument', 'name', 'measures', 'counts', 'answer', 'deadlines'));
    }

    public function standards(): View
    {
        $body = request()->query('body');
        $filters = ['standards' => true, 'body' => array_key_exists((string) $body, ImplementationMeasure::BODIES) ? $body : null];
        $all = Trackers::measures(['standards' => true]);
        $measures = Trackers::measures($filters);
        $counts = $this->counts($all);
        $harmonised = $all->where('kind', 'harmonised_standard');
        $cited = $harmonised->filter(fn ($m) => $m->oj_citation_on !== null);
        $answer = sprintf(
            '%d %s tracked: %d harmonised %s under the EU AI Act (%d cited in the Official Journal on the records here) and %d ISO/IEC %s. %d recorded from a cited source, %d still in draft. Metadata only: no standard text is reproduced.',
            $all->count(), Str::plural('standard', $all->count()), $harmonised->count(), Str::plural('standard', $harmonised->count()), $cited->count(),
            $all->where('kind', 'iso_work_item')->count(), Str::plural('standard', $all->where('kind', 'iso_work_item')->count()),
            $counts['recorded'], $counts['drafts']
        );
        $indexable = ! $filters['body'] && Trackers::standardsIndexable();
        $title = 'AI Standards Tracker: Harmonised and ISO/IEC Standards';
        $seo = Seo::make(PageTitle::fit($title), PageTitle::description($answer), route('standards.index'), $indexable)
            ->withBreadcrumbs([['Home', route('home')], ['AI standards', route('standards.index')]])
            ->withModified($all->max('updated_at'))
            ->withPageType('CollectionPage', ['name' => $title, 'description' => $answer])
            ->withJsonLd(Seo::dataset('AI standards work items', 'CEN-CENELEC JTC 21 harmonised standards and ISO/IEC JTC 1/SC 42 standards: reference, body, stage, publication and Official Journal citation dates. Metadata only.', route('standards.index'), ['application/json' => route('api.v1.implementation', ['standards' => 1])], $all->max('updated_at')))
            ->withFaq([
                ['question' => 'Does this page reproduce standards?', 'answer' => 'No. Standards are sold under licence by their publishers. This page records metadata only (reference, committee, stage and dates) and links to the publisher\'s page.'],
                ['question' => 'What is a harmonised standard?', 'answer' => 'A European standard written at the Commission\'s request whose reference is cited in the Official Journal; following it gives a presumption of conformity with the requirements it covers. Until the citation, it gives none, which is why the citation date is tracked.'],
            ]);
        if (! $indexable) {
            $seo->noindex();
        }

        return view('site.implementation.standards', compact('seo', 'filters', 'measures', 'all', 'counts', 'answer'));
    }

    /** @return array{recorded: int, verified: int, drafts: int, overdue: int} */
    private function counts(Collection $measures): array
    {
        $recorded = $measures->reject->isDraft();

        return [
            'recorded' => $recorded->count(),
            'verified' => $recorded->filter->isVerified()->count(),
            'drafts' => $measures->count() - $recorded->count(),
            'overdue' => $measures->filter(fn ($m) => $m->effectiveStatus() === 'overdue')->count(),
        ];
    }
}
