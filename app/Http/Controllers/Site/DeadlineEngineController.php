<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Deadline;
use App\Models\Jurisdiction;
use App\Services\Deadlines\DeadlineEngine;
use App\Services\Deadlines\DeadlinePdf;
use App\Services\PolicyData\DeadlineCalendar;
use App\Support\PageTitle;
use App\Support\Seo;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * /deadlines/which-date-applies: five questions, answered one page at a
 * time with plain forms (no JavaScript needed; the answers travel in the
 * query string), then a personal timeline computed from the recorded
 * deadlines, with .ics and PDF exports of the same rows.
 */
class DeadlineEngineController extends Controller
{
    public function show(Request $request, DeadlineEngine $engine): View
    {
        $answers = $engine->normalise($request->query());
        $step = $engine->step($answers, $request->integer('step') ?: null);
        $done = $step >= DeadlineEngine::STEPS && $answers['jurisdictions'] !== [];
        $options = $engine->options();
        $jurisdictions = Jurisdiction::published()->withPublishedInstrument()->withCount(['policyInstruments' => fn ($q) => $q->published()])->orderBy('name')->get();
        $labels = $options + ['jurisdictions' => $jurisdictions->pluck('name', 'slug')->all()];
        $rows = $done ? $engine->applicable($answers) : collect();
        $summary = $done ? $engine->summary($rows, $answers, $labels) : null;
        $query = array_filter($answers, fn ($v) => $v !== null && $v !== []);

        $faq = [
            ['question' => 'How is my timeline computed?', 'answer' => 'Only from the deadlines on record. Each is kept if the instrument, or the duty it belongs to, names your role, kind of system, risk tier, sector and use case, or names no narrower scope. The reason each date is shown is printed beside it.'],
            ['question' => 'What does "originally X, now Y" mean?', 'answer' => 'A re-import found the recorded date changed since it was first stored, for example after an amendment. The original date is kept so the move is visible; the source link goes to the current official text.'],
            ['question' => 'Is this a legal determination?', 'answer' => 'No. It is a filter over recorded dates by the scope recorded for each, not a judgement that a law applies to you. Confirm each date against the official source before planning around it.'],
            ['question' => 'Can I subscribe to these dates?', 'answer' => 'Yes. The .ics export of a result is a calendar file with reminders; the calendar page offers a subscription feed for every jurisdiction.'],
        ];
        $seo = Seo::make(PageTitle::deadlineEngine(), 'Answer five questions (markets, role, system, sector, use case) and get a personal timeline of recorded AI regulation dates, with the reason each applies, the original date where it moved, .ics and PDF.', route('deadlines.engine'), $request->query() === [])
            ->withBreadcrumbs([['Home', route('home')], ['Deadline calendar', route('calendar')], ['Which date applies?', route('deadlines.engine')]])
            ->withModified(Deadline::max('updated_at') ? Carbon::parse(Deadline::max('updated_at')) : null)
            ->withPageType('WebApplication', ['name' => 'Which AI regulation date applies to you?', 'applicationCategory' => 'BusinessApplication', 'operatingSystem' => 'Any', 'isAccessibleForFree' => true, 'offers' => ['@type' => 'Offer', 'price' => '0', 'priceCurrency' => 'USD']])
            ->withFaq($faq);
        if ($request->query() !== []) {
            $seo->noindex();
        }

        return view('site.deadlines.engine', compact('seo', 'answers', 'step', 'done', 'options', 'jurisdictions', 'labels', 'rows', 'summary', 'query'));
    }

    public function ics(Request $request, DeadlineEngine $engine, DeadlineCalendar $calendar): Response
    {
        $answers = $engine->normalise($request->query());
        abort_if($answers['jurisdictions'] === [], 404);
        $events = $engine->applicable($answers)->map(fn ($r) => $r['deadline'])
            ->filter(fn ($d) => $d->due_on && $d->date_precision === DeadlineCalendar::PUBLISHABLE_PRECISION && in_array($d->deadline_status, DeadlineCalendar::PUBLISHABLE_STATUS, true))->values();
        $body = $calendar->render($events, 'AI regulation dates that apply to you', 'Recorded application dates and deadlines filtered by your answers on aipolicytracker.org. Informational only; not legal advice.');

        return response($body, 200, ['Content-Type' => 'text/calendar; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="ai-regulation-dates.ics"', 'X-Robots-Tag' => 'noindex', 'Cache-Control' => 'private, no-store']);
    }

    public function pdf(Request $request, DeadlineEngine $engine, DeadlinePdf $pdf): Response
    {
        $answers = $engine->normalise($request->query());
        abort_if($answers['jurisdictions'] === [], 404);
        $options = $engine->options();
        $labels = $options + ['jurisdictions' => Jurisdiction::published()->pluck('name', 'slug')->all()];
        $rows = $engine->applicable($answers);

        return response($pdf->render($rows, $answers, $labels, $engine->summary($rows, $answers, $labels)), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="ai-regulation-dates.pdf"', 'X-Robots-Tag' => 'noindex', 'Cache-Control' => 'private, no-store']);
    }
}
