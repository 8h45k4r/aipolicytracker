<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Services\Completeness\CompletenessReport;
use App\Support\Seo;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Publishes what the corpus is missing, and turns that into a queue anyone can
 * work from.
 *
 * The honest framing matters here. This measures completeness of what is
 * published, not coverage of the world: it can say that 46 instruments have no
 * source date, and it cannot say how many instruments exist that we have never
 * recorded. Both pages say so rather than letting a percentage imply the
 * stronger claim.
 */
class CoverageController extends Controller
{
    /** Longest queue a single page will render, so a large corpus cannot produce an unusable page. */
    private const QUEUE_LIMIT = 200;

    public function show(CompletenessReport $completeness): View
    {
        $report = $completeness->report();
        $seo = Seo::make(
            'Coverage: what every published record carries, and what it is missing',
            'What a published AI policy record must carry to be checkable, how many records carry it today, and exactly which ones do not. The same check runs on every data change.',
            route('coverage')
        )->withBreadcrumbs([['Home', route('home')], ['Methodology', route('methodology')], ['Coverage', route('coverage')]])
            ->withPageType('CollectionPage');

        return view('site.pages.coverage', compact('seo', 'report'));
    }

    public function gaps(Request $request, CompletenessReport $completeness): View
    {
        $kinds = $completeness->kinds();
        $kind = array_key_exists((string) $request->query('kind'), $kinds) ? (string) $request->query('kind') : null;
        $check = $completeness->check((string) $request->query('check'));
        // A check filter implies its kind, so the two controls cannot contradict each other.
        if ($check) {
            $kind = $check['kind'];
        }

        $queue = $completeness->queue($kind, $check['id'] ?? null);
        $seo = Seo::make(
            'Gaps: the open queue of records missing something',
            'Every published record that is missing a source link, a summary, a provision reference or another field a checkable record needs. Each one links to the form that fixes it.',
            route('gaps')
        )->withBreadcrumbs([['Home', route('home')], ['Coverage', route('coverage')], ['Gaps', route('gaps')]])
            ->withPageType('CollectionPage');
        // A filtered queue is one view of the same records, so only the unfiltered page is indexed.
        if ($kind || $check) {
            $seo->noindex();
        }

        return view('site.pages.gaps', [
            'seo' => $seo,
            'queue' => $queue->take(self::QUEUE_LIMIT),
            'total' => $queue->count(),
            'limit' => self::QUEUE_LIMIT,
            'kinds' => $kinds,
            'checks' => $completeness->checks(),
            'activeKind' => $kind,
            'activeCheck' => $check,
        ]);
    }
}
