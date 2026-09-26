<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Services\Report\StateOfAiRegulation;
use App\Support\PageTitle;
use App\Support\Seo;
use Carbon\Carbon;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * /state-of-ai-regulation: a quarterly report computed from the records,
 * with a tile map of jurisdictions by level of AI law (and the same facts
 * as a list), frozen snapshots for past quarters, a methodology section,
 * a CSV and a Dataset. The current quarter is live; a frozen quarter reads
 * the same forever.
 */
class StateOfController extends Controller
{
    public function show(?string $quarter = null): View
    {
        $current = StateOfAiRegulation::currentQuarter();
        $quarter ??= $current;
        abort_unless(StateOfAiRegulation::isValidQuarter($quarter), 404);
        $isCurrent = $quarter === $current;
        $report = StateOfAiRegulation::report($quarter);
        abort_if(! $isCurrent && ! $report['frozen'] && $quarter > $current, 404);
        $frozen = StateOfAiRegulation::frozenQuarters();
        $t = $report['totals'];
        $label = str_replace('-Q', ' Q', $quarter);

        $answer = sprintf(
            'In %s this site records %s AI policy instruments across %d jurisdictions with at least one record; %d jurisdictions have binding AI-specific law on record and %d have binding law in force. %d instruments (%d%%) are verified against their official source. %d changes were recorded in the quarter, %d of them urgent, and %d dated deadlines fall in the next quarter.',
            $label, number_format($t['instruments']), $t['jurisdictions_with_records'], $t['jurisdictions_with_binding_law'], $t['jurisdictions_with_binding_in_force'],
            $t['verified_instruments'], $t['instruments'] ? (int) round(100 * $t['verified_instruments'] / $t['instruments']) : 0, $t['changes_in_quarter'], $t['urgent_changes_in_quarter'], $t['deadlines_next_quarter']
        );
        $faq = [
            ['question' => 'How many countries have AI laws in '.$label.'?', 'answer' => sprintf('%d of the %d jurisdictions covered have at least one binding AI-specific instrument on record, and %d have one in force. The rest govern AI through strategies, guidance and existing law. Every count is computed from the records and links to them.', $t['jurisdictions_with_binding_law'], $t['jurisdictions_covered'], $t['jurisdictions_with_binding_in_force'])],
            ['question' => 'What changed this quarter?', 'answer' => sprintf('%d dated, source-linked changes were recorded between %s and %s; the top entries are listed on the page and every one links to its record and official source.', $t['changes_in_quarter'], $report['period']['start'], $report['period']['end'])],
            ['question' => 'Is a past quarter\'s report frozen?', 'answer' => 'Yes. When a quarter closes its figures are frozen as a snapshot (version '.StateOfAiRegulation::VERSION.') so the report reads the same later, even as records are added or corrected. The current quarter is computed live and says so.'],
            ['question' => 'How is "verified" defined?', 'answer' => 'A record a named reviewer has checked against the official source, with the date of that check on the record. Sourced means the record links an official source; verified means someone confirmed it matches.'],
        ];
        $title = 'State of AI Regulation '.$label.': Countries, Laws & Changes';
        $url = $isCurrent ? route('state-of.show') : route('state-of.quarter', $quarter);
        $seo = Seo::make(PageTitle::fit($title, [': Countries, Laws & Changes', '']), PageTitle::description($answer), $url, $isCurrent || $report['frozen'])
            ->withBreadcrumbs(array_values(array_filter([['Home', route('home')], ['State of AI regulation', route('state-of.show')], $isCurrent ? null : [$label, $url]])))
            ->withModified($report['frozen'] ? Carbon::parse($report['frozen_at']) : now())
            ->withPageType('Report', ['headline' => 'The state of AI regulation, '.$label, 'reportNumber' => $quarter, 'author' => ['@id' => url('/').'#organization']])
            ->withJsonLd(Seo::dataset('State of AI regulation '.$label, 'Quarterly figures computed from the AI policy records: jurisdictions by level of AI law, instruments by type and status, changes in the quarter, deadlines next quarter, verification coverage.', $url, ['text/csv' => route('state-of.csv', $quarter)], $report['frozen'] ? Carbon::parse($report['frozen_at']) : now(), 'state-of-ai-regulation-'.$quarter))
            ->withFaq($faq);

        return view('site.pages.state-of', compact('seo', 'report', 'quarter', 'label', 'isCurrent', 'frozen', 'answer'));
    }

    public function csv(string $quarter): Response
    {
        abort_unless(StateOfAiRegulation::isValidQuarter($quarter), 404);
        $report = StateOfAiRegulation::report($quarter);

        return response(StateOfAiRegulation::csv($report), 200, ['Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="state-of-ai-regulation-'.$quarter.'.csv"', 'Cache-Control' => 'public, max-age=3600']);
    }
}
