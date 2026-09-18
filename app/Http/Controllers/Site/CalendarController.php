<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
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
    public function show(DeadlineCalendar $calendar): View
    {
        $events = $calendar->events();
        $jurisdictions = Jurisdiction::published()->whereHas('policyInstruments.deadlines')->orderBy('name')->get(['slug', 'name']);
        $seo = Seo::make(
            'AI policy deadline calendar (subscribe)',
            'Subscribe to dated application deadlines from tracked AI policy instruments in your own calendar, with reminders 30 and 7 days ahead. Only dates recorded to an exact day are published.',
            route('calendar')
        )->withBreadcrumbs([['Home', route('home')], ['Changes', route('changes.index')], ['Deadline calendar', route('calendar')]])
            ->withPageType('CollectionPage')
            // The page is a view of a feed that exists. Naming the .ics distribution
            // tells a machine it can subscribe rather than scrape.
            ->withJsonLd(Seo::dataset(
                'AI policy compliance deadlines',
                'Dated compliance deadlines from recorded AI policy instruments, each linked to the instrument and its official source.',
                route('calendar'),
                ['text/calendar' => route('calendar.feed')],
            ));

        return view('site.pages.calendar', compact('seo', 'events', 'jurisdictions'));
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
