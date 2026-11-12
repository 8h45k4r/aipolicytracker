<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Consultation;
use App\Models\Deadline;
use App\Models\Jurisdiction;
use App\Services\PolicyData\DeadlineCalendar;
use App\Support\Seo;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
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

    /** How far from today the month grid may be paged, either way. */
    public const GRID_MONTHS = 36;

    public function show(Request $request, DeadlineCalendar $calendar): View
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
        // Two views of the same dates. The list is the page search engines index; the month
        // grid is a way of reading it, so it is served noindex and its months are not crawled
        // one by one.
        $view = $request->query('view') === 'calendar' ? 'calendar' : 'list';
        $grid = $view === 'calendar' ? $this->monthGrid($this->month($request->query('month'))) : null;
        $seo = Seo::make('Upcoming AI compliance deadlines and dates', $description, route('calendar'), $view === 'list')
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

        return view('site.pages.calendar', compact('seo', 'events', 'upcoming', 'recent', 'consultations', 'meetings', 'next', 'yearAhead', 'jurisdictions', 'view', 'grid'));
    }

    /** The month asked for as YYYY-MM, within GRID_MONTHS of today; otherwise this month. */
    private function month(mixed $value): Carbon
    {
        $now = now()->startOfMonth();
        if (is_string($value) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $value) === 1) {
            $month = Carbon::createFromFormat('Y-m-d', $value.'-01')->startOfDay();
            if (abs($now->diffInMonths($month, false)) <= self::GRID_MONTHS) {
                return $month;
            }
        }

        return $now;
    }

    /**
     * A month as weeks of days, Monday first, each day with what falls on it: recorded
     * deadlines (exact day only, the same rule as the feed), comment periods closing and
     * meetings held, the last two only when not hidden and labelled when not reviewed.
     *
     * @return array{month: Carbon, prev: ?string, next: ?string, weeks: list<list<array{date: Carbon, in_month: bool, today: bool, items: list<array>}>>, count: int}
     */
    private function monthGrid(Carbon $month): array
    {
        $from = $month->copy()->startOfMonth();
        $to = $month->copy()->endOfMonth();
        $items = [];
        $deadlines = Deadline::query()->with('policyInstrument.jurisdiction')
            ->whereNotNull('due_on')->where('date_precision', DeadlineCalendar::PUBLISHABLE_PRECISION)
            ->whereIn('deadline_status', ['scheduled', 'passed'])
            ->whereBetween('due_on', [$from->toDateString(), $to->toDateString()])
            ->whereHas('policyInstrument', fn ($q) => $q->published())->orderBy('due_on')->get();
        foreach ($deadlines as $d) {
            $items[$d->due_on->toDateString()][] = ['kind' => 'deadline', 'title' => $d->title, 'detail' => $d->policyInstrument->jurisdiction->name.' · '.($d->policyInstrument->short_title ?: $d->policyInstrument->title), 'url' => $d->policyInstrument->url(), 'reviewed' => true];
        }
        $consultations = Consultation::whereIn('status', ['confirmed', 'collected'])
            ->where(fn ($q) => $q->where(fn ($c) => $c->where('kind', 'comment_period')->whereBetween('comments_close_on', [$from->toDateString(), $to->toDateString()]))
                ->orWhere(fn ($m) => $m->where('kind', 'meeting')->whereBetween('event_on', [$from->toDateString(), $to->toDateString()])))->get();
        foreach ($consultations as $c) {
            $items[$c->listedOn()->toDateString()][] = ['kind' => $c->kind === 'meeting' ? 'meeting' : 'consultation', 'title' => $c->title, 'detail' => trim(($c->kind === 'meeting' ? 'Meeting' : 'Comments close').' · '.$c->agency), 'url' => $c->url, 'reviewed' => $c->status === 'confirmed'];
        }

        $weeks = [];
        $day = $from->copy()->startOfWeek(Carbon::MONDAY);
        $end = $to->copy()->endOfWeek(Carbon::SUNDAY);
        $today = now()->toDateString();
        while ($day->lte($end)) {
            $week = [];
            for ($i = 0; $i < 7; $i++, $day->addDay()) {
                $week[] = ['date' => $day->copy(), 'in_month' => $day->month === $from->month, 'today' => $day->toDateString() === $today, 'items' => $items[$day->toDateString()] ?? []];
            }
            $weeks[] = $week;
        }
        $now = now()->startOfMonth();
        $prev = $from->copy()->subMonth();
        $next = $from->copy()->addMonth();

        return [
            'month' => $from,
            'prev' => abs($now->diffInMonths($prev, false)) <= self::GRID_MONTHS ? $prev->format('Y-m') : null,
            'next' => abs($now->diffInMonths($next, false)) <= self::GRID_MONTHS ? $next->format('Y-m') : null,
            'weeks' => $weeks,
            'count' => array_sum(array_map('count', $items)),
        ];
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
