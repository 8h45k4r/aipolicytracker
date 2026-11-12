<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Consultation;
use App\Models\Deadline;
use App\Models\Jurisdiction;
use App\Services\PolicyData\DeadlineCalendar;
use App\Support\Seo;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * Application dates as a subscribable calendar. A compliance lead subscribes
 * once and the dates appear in their own diary, with reminders, instead of
 * being copied by hand from a web page.
 */
class CalendarController extends Controller
{
    /** How long a passed date stays listed on the page. */
    public const RECENT_DAYS = 180;

    public function show(DeadlineCalendar $calendar): View
    {
        $events = $calendar->events();
        $today = now()->startOfDay();
        $upcoming = $events->filter(fn ($d) => $d->due_on->gte($today))->values();
        // Dates that have just passed stay on the page for a while: "did that deadline
        // apply yet?" is asked as often as "what is next?". The feed keeps only what is ahead.
        $recent = Deadline::query()->with(['policyInstrument.jurisdiction'])
            ->whereNotNull('due_on')->where('date_precision', DeadlineCalendar::PUBLISHABLE_PRECISION)
            ->whereBetween('due_on', [$today->copy()->subDays(self::RECENT_DAYS)->toDateString(), $today->copy()->subDay()->toDateString()])
            ->whereIn('deadline_status', ['scheduled', 'passed'])
            ->whereHas('policyInstrument', fn ($q) => $q->published())
            ->orderByDesc('due_on')->get();
        // Open comment periods: those a reviewer confirmed, and those collected from an official
        // source and not yet reviewed, kept apart and labelled. Hidden entries never show.
        $consultations = Consultation::open()->whereIn('status', ['confirmed', 'collected'])->orderBy('comments_close_on')->get()->groupBy('status');
        $meetings = Consultation::upcomingMeetings()->whereIn('status', ['confirmed', 'collected'])->orderBy('event_on')->get()->groupBy('status');
        $next = $upcoming->first();
        $yearAhead = $upcoming->filter(fn ($d) => $d->due_on->lte($today->copy()->addYear()))->count();
        $jurisdictions = Jurisdiction::published()->whereHas('policyInstruments.deadlines')->orderBy('name')->get(['slug', 'name']);
        $description = $next
            ? 'Upcoming AI compliance deadlines from tracked laws, next: '.$next->due_on->format('j M Y').'. Each date links to its official source. Subscribe in your calendar.'
            : 'Upcoming AI compliance deadlines from tracked laws, each linked to its official source. Subscribe in your own calendar, with reminders.';
        $seo = Seo::make('Upcoming AI compliance deadlines and dates', $description, route('calendar'))
            ->withBreadcrumbs([['Home', route('home')], ['Changes', route('changes.index')], ['Upcoming deadlines', route('calendar')]])
            ->withPageType('CollectionPage', ['mainEntity' => Seo::itemList($upcoming->take(50), fn ($d) => $d->due_on->toDateString().': '.$d->title, fn ($d) => $d->policyInstrument->url(), 'Upcoming AI compliance deadlines')])
            // The page is a view of a feed that exists. Naming the .ics distribution
            // tells a machine it can subscribe rather than scrape.
            ->withJsonLd(Seo::dataset(
                'AI policy compliance deadlines',
                'Dated compliance deadlines from recorded AI policy instruments, each linked to the instrument and its official source.',
                route('calendar'),
                ['text/calendar' => route('calendar.feed')],
            ));

        return view('site.pages.calendar', compact('seo', 'events', 'upcoming', 'recent', 'consultations', 'meetings', 'next', 'yearAhead', 'jurisdictions'));
    }

    /** The feed itself. A jurisdiction slug narrows it. */
    public function feed(DeadlineCalendar $calendar, ?string $jurisdiction = null): Response
    {
        $place = $jurisdiction ? Jurisdiction::published()->where('slug', $jurisdiction)->firstOrFail() : null;
        $name = $place ? config('aipolicytracker.site_name').': '.$place->name.' AI policy dates' : config('aipolicytracker.site_name').': AI policy dates';
        $body = $calendar->render(
            $calendar->events($place?->slug),
            $name,
            'Dated application deadlines from tracked AI policy instruments. Informational only, not legal advice.'
        );

        return response($body, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'inline; filename="'.($place?->slug ?? 'ai-policy').'-deadlines.ics"',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
