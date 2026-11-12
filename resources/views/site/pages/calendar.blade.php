@extends('site.layouts.app')
@section('content')
<div class="container-site py-8 max-w-3xl">
    <x-site.breadcrumbs :items="$seo->breadcrumbs" />
    <p class="eyebrow mt-3">Deadline calendar</p>
    <h1 class="mt-2 text-2xl sm:text-3xl lg:text-4xl font-semibold tracking-tight text-brand-navy">Upcoming AI compliance deadlines</h1>
    {{-- The answer first, computed from the records: what a reader (or an answer engine) asks this page. --}}
    @if($next)
    <p class="mt-3 max-w-[68ch] text-brand-body leading-7" data-answer>The next recorded deadline is <strong>{{ $next->title }}</strong> ({{ $next->policyInstrument->jurisdiction->name }}, {{ $next->policyInstrument->short_title ?: $next->policyInstrument->title }}) on <strong><time datetime="{{ $next->due_on->toDateString() }}">{{ $next->due_on->format('j F Y') }}</time></strong>. {{ $yearAhead }} {{ \Illuminate\Support\Str::plural('date', $yearAhead) }} recorded to an exact day {{ $yearAhead === 1 ? 'falls' : 'fall' }} in the next twelve months.</p>
    @else
    <p class="mt-3 max-w-[68ch] text-brand-body leading-7" data-answer>No tracked instrument has an upcoming deadline recorded to an exact day right now.</p>
    @endif
    <p class="mt-3 max-w-[68ch] text-sm text-brand-body leading-6">Every date comes from a tracked AI law or policy and links to its record and official source. A date is listed only once it is recorded to an exact day; one known only as a month or year stays on its record instead. Check the official source before relying on a date: a record marked pending review has not yet been confirmed by a reviewer.</p>


    {{-- Two views of the same dates: the list (indexed) and a month grid. Plain links, so both work without script. --}}
    <nav class="mt-6 flex flex-wrap items-center gap-2" aria-label="Calendar view">
        <a href="{{ route('calendar') }}" class="{{ $view === 'list' ? 'btn-primary' : 'btn-secondary' }} !min-h-[36px] !py-1" @if($view === 'list') aria-current="page" @endif data-track="calendar_view_list">List</a>
        <a href="{{ route('calendar', ['view' => 'calendar']) }}" class="{{ $view === 'calendar' ? 'btn-primary' : 'btn-secondary' }} !min-h-[36px] !py-1" @if($view === 'calendar') aria-current="page" @endif data-track="calendar_view_grid" rel="nofollow">Calendar</a>
    </nav>

    @if($view === 'calendar')
    @php($m = $grid['month'])
    <section class="mt-6" aria-labelledby="grid-h">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 id="grid-h" class="section-title">{{ $m->format('F Y') }}</h2>
            <p class="flex gap-2 text-sm">
                @if($grid['prev'])<a href="{{ route('calendar', ['view' => 'calendar', 'month' => $grid['prev']]) }}" rel="nofollow" class="btn-secondary !min-h-[36px] !py-1" aria-label="Previous month">&larr; {{ $m->copy()->subMonth()->format('M Y') }}</a>@endif
                @if($m->format('Y-m') !== now()->format('Y-m'))<a href="{{ route('calendar', ['view' => 'calendar']) }}" rel="nofollow" class="btn-secondary !min-h-[36px] !py-1">This month</a>@endif
                @if($grid['next'])<a href="{{ route('calendar', ['view' => 'calendar', 'month' => $grid['next']]) }}" rel="nofollow" class="btn-secondary !min-h-[36px] !py-1" aria-label="Next month">{{ $m->copy()->addMonth()->format('M Y') }} &rarr;</a>@endif
            </p>
        </div>
        <p class="mt-1 meta"><span class="inline-block h-2 w-2 rounded-full bg-brand-navy align-middle" aria-hidden="true"></span> recorded deadline · <span class="inline-block h-2 w-2 rounded-full bg-brand-blue align-middle" aria-hidden="true"></span> comments close · <span class="inline-block h-2 w-2 rounded-full bg-state-warn align-middle" aria-hidden="true"></span> public meeting · "not reviewed" marks an entry collected automatically</p>
        <div class="mt-3 overflow-x-auto">
        <table class="w-full table-fixed border-collapse text-xs" data-month-grid>
            <caption class="sr-only">AI policy dates in {{ $m->format('F Y') }}</caption>
            <thead><tr>@foreach(['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $wd)<th scope="col" class="border border-brand-line bg-brand-paper py-1 font-semibold text-brand-navy">{{ $wd }}</th>@endforeach</tr></thead>
            <tbody>
            @foreach($grid['weeks'] as $week)
            <tr>
                @foreach($week as $d)
                <td class="h-20 align-top border border-brand-line p-1 {{ $d['in_month'] ? '' : 'bg-brand-paper text-brand-muted' }} {{ $d['today'] ? 'ring-2 ring-inset ring-brand-blue' : '' }}">
                    <div class="font-mono {{ $d['today'] ? 'font-semibold text-brand-blue' : '' }}"><time datetime="{{ $d['date']->toDateString() }}">{{ $d['date']->day }}</time>@if($d['today'])<span class="sr-only"> (today)</span>@endif</div>
                    @foreach($d['items'] as $item)
                    <a href="{{ $item['url'] }}" class="mt-0.5 block truncate rounded-sm px-1 hover:underline {{ $item['kind'] === 'deadline' ? 'bg-brand-navy text-white' : ($item['kind'] === 'meeting' ? 'bg-state-warnbg text-brand-navy' : 'bg-brand-blue/10 text-brand-navy') }}" title="{{ $item['title'] }}" @if($item['kind'] !== 'deadline') rel="nofollow noopener" @endif data-track="calendar_grid_item">{{ $item['title'] }}</a>
                    @endforeach
                </td>
                @endforeach
            </tr>
            @endforeach
            </tbody>
        </table>
        </div>
        {{-- The same month as a list: what a screen reader or a narrow screen reads. --}}
        <h3 class="mt-6 font-semibold text-brand-navy">{{ $m->format('F Y') }} as a list</h3>
        @php($days = collect($grid['weeks'])->flatten(1)->filter(fn ($d) => $d['in_month'] && $d['items'] !== []))
        @if($days->isEmpty())
        <p class="mt-2 text-sm text-brand-muted">Nothing recorded for {{ $m->format('F Y') }}.@if($next) The next recorded deadline is on {{ $next->due_on->format('j F Y') }}: <a href="{{ route('calendar', ['view' => 'calendar', 'month' => $next->due_on->format('Y-m')]) }}" rel="nofollow">go to {{ $next->due_on->format('F Y') }}</a>.@endif</p>
        @else
        <ul class="mt-2 divide-y divide-brand-line border-y border-brand-line text-sm" data-month-agenda>
            @foreach($days as $d)
            @foreach($d['items'] as $item)
            <li class="grid gap-1 py-2 sm:grid-cols-[7.5rem_1fr]"><time datetime="{{ $d['date']->toDateString() }}" class="font-mono text-xs text-brand-navy pt-0.5">{{ $d['date']->format('j M Y') }}</time>
                <span><a href="{{ $item['url'] }}" class="text-brand-navy hover:underline" @if($item['kind'] !== 'deadline') rel="nofollow noopener" @endif>{{ $item['title'] }}</a> <span class="meta">· {{ $item['detail'] }}{{ $item['reviewed'] ? '' : ' · not reviewed' }}</span></span></li>
            @endforeach
            @endforeach
        </ul>
        @endif
    </section>
    @else

    @if($upcoming->isNotEmpty())
    <section class="mt-8" aria-labelledby="upcoming-h">
        <h2 id="upcoming-h" class="section-title">Upcoming deadlines</h2>
        @foreach($upcoming->groupBy(fn ($d) => $d->due_on->year) as $year => $rows)
        <h3 class="mt-5 font-semibold text-brand-navy">{{ $year }}</h3>
        <ul class="mt-2 divide-y divide-brand-line border-y border-brand-line text-sm">
            @foreach($rows as $d)
            <li class="grid gap-1 py-2 sm:grid-cols-[7.5rem_1fr]">
                <time datetime="{{ $d->due_on->toDateString() }}" class="font-mono text-xs text-brand-navy whitespace-nowrap pt-0.5">{{ $d->due_on->format('j M Y') }}</time>
                <span><a href="{{ $d->policyInstrument->url() }}" class="text-brand-navy hover:underline">{{ $d->title }}</a>
                    <span class="meta">· {{ $d->policyInstrument->jurisdiction->name }} · {{ $d->policyInstrument->short_title ?: $d->policyInstrument->title }}@if($d->source_reference) · {{ $d->source_reference }}@endif @if($d->official_source_url ?: $d->policyInstrument->official_source_url)· <a href="{{ $d->official_source_url ?: $d->policyInstrument->official_source_url }}" rel="nofollow noopener" class="hover:underline" data-track="source_click">official source</a>@endif · {{ $d->policyInstrument->verificationLabel() }}</span></span>
            </li>
            @endforeach
        </ul>
        @endforeach
    </section>
    @endif

    @if($recent->isNotEmpty())
    <section class="mt-8" aria-labelledby="recent-h">
        <h2 id="recent-h" class="section-title">Recently passed</h2>
        <p class="mt-1 text-sm text-brand-body">Dates in the last {{ \App\Http\Controllers\Site\CalendarController::RECENT_DAYS }} days. They have applied and are no longer in the feed.</p>
        <ul class="mt-3 divide-y divide-brand-line border-y border-brand-line text-sm">
            @foreach($recent as $d)
            <li class="grid gap-1 py-2 sm:grid-cols-[7.5rem_1fr]">
                <time datetime="{{ $d->due_on->toDateString() }}" class="font-mono text-xs text-brand-muted whitespace-nowrap pt-0.5">{{ $d->due_on->format('j M Y') }}</time>
                <span><a href="{{ $d->policyInstrument->url() }}" class="text-brand-navy hover:underline">{{ $d->title }}</a> <span class="meta">· {{ $d->policyInstrument->jurisdiction->name }} · {{ $d->policyInstrument->short_title ?: $d->policyInstrument->title }}</span></span>
            </li>
            @endforeach
        </ul>
    </section>
    @endif

    <section class="mt-8" aria-labelledby="consult-h" id="consultations">
        <h2 id="consult-h" class="section-title">Open consultations and comment deadlines</h2>
        <p class="mt-1 text-sm text-brand-body">Public consultations on AI from official sources, by the date comments close. Today this covers the US Federal Register; other sources are added only where the publisher offers its own feed.</p>
        @php($confirmed = $consultations['confirmed'] ?? collect())
        @php($collected = $consultations['collected'] ?? collect())
        @if($confirmed->isEmpty() && $collected->isEmpty())
        <p class="mt-3 text-sm text-brand-muted">No open consultations on AI are recorded right now.</p>
        @endif
        @if($confirmed->isNotEmpty())
        <h3 class="mt-4 font-semibold text-brand-navy">Reviewed</h3>
        <ul class="mt-2 divide-y divide-brand-line border-y border-brand-line text-sm">
            @foreach($confirmed as $c)
            <li class="grid gap-1 py-2 sm:grid-cols-[7.5rem_1fr]"><span class="font-mono text-xs text-brand-navy pt-0.5">closes <time datetime="{{ $c->comments_close_on->toDateString() }}">{{ $c->comments_close_on->format('j M Y') }}</time></span>
                <span><a href="{{ $c->url }}" rel="nofollow noopener" class="text-brand-navy hover:underline" data-track="consultation_click">{{ $c->title }}</a> <span class="meta">· {{ $c->agency }} · {{ $c->document_type }} · {{ $c->sourceLabel() }}@if($c->comment_url) · <a href="{{ $c->comment_url }}" rel="nofollow noopener" class="hover:underline" data-track="consultation_comment">comment</a>@endif</span></span></li>
            @endforeach
        </ul>
        @endif
        @if($collected->isNotEmpty())
        <h3 class="mt-4 font-semibold text-brand-navy">Collected automatically, not yet reviewed</h3>
        <p class="mt-1 meta">Taken from the publisher's own feed because the title or summary mentions AI. No reviewer has yet confirmed that each is about AI governance or checked its date: open the official page before relying on it.</p>
        <ul class="mt-2 divide-y divide-brand-line border-y border-brand-line text-sm" data-unreviewed>
            @foreach($collected as $c)
            <li class="grid gap-1 py-2 sm:grid-cols-[7.5rem_1fr]"><span class="font-mono text-xs text-brand-muted pt-0.5">closes <time datetime="{{ $c->comments_close_on->toDateString() }}">{{ $c->comments_close_on->format('j M Y') }}</time></span>
                <span><a href="{{ $c->url }}" rel="nofollow noopener" class="text-brand-navy hover:underline" data-track="consultation_click">{{ $c->title }}</a> <span class="meta">· {{ $c->agency }} · {{ $c->document_type }} · {{ $c->sourceLabel() }}</span></span></li>
            @endforeach
        </ul>
        @endif
    </section>

    <section class="mt-8" aria-labelledby="meet-h" id="meetings">
        <h2 id="meet-h" class="section-title">Upcoming public meetings and hearings</h2>
        <p class="mt-1 text-sm text-brand-body">Public meetings, hearings and workshops on AI announced in official notices, by the date they are held. The date is read from the notice's own words, which are shown beside it. Today this covers the US Federal Register.</p>
        @php($mConfirmed = $meetings['confirmed'] ?? collect())
        @php($mCollected = $meetings['collected'] ?? collect())
        @if($mConfirmed->isEmpty() && $mCollected->isEmpty())
        <p class="mt-3 text-sm text-brand-muted">No upcoming public meetings on AI are recorded right now.</p>
        @endif
        @foreach(['Reviewed' => $mConfirmed, 'Collected automatically, not yet reviewed' => $mCollected] as $heading => $rows)
        @if($rows->isNotEmpty())
        <h3 class="mt-4 font-semibold text-brand-navy">{{ $heading }}</h3>
        @if($heading !== 'Reviewed')<p class="mt-1 meta">Taken from the publisher's own feed because the notice names a meeting and mentions AI. No reviewer has yet confirmed it or its date: open the official notice before relying on it.</p>@endif
        <ul class="mt-2 divide-y divide-brand-line border-y border-brand-line text-sm" @if($heading !== 'Reviewed') data-unreviewed-meetings @endif>
            @foreach($rows as $c)
            <li class="grid gap-1 py-2 sm:grid-cols-[7.5rem_1fr]"><span class="font-mono text-xs {{ $heading === 'Reviewed' ? 'text-brand-navy' : 'text-brand-muted' }} pt-0.5"><time datetime="{{ $c->event_on->toDateString() }}">{{ $c->event_on->format('j M Y') }}</time></span>
                <span><a href="{{ $c->url }}" rel="nofollow noopener" class="text-brand-navy hover:underline" data-track="meeting_click">{{ $c->title }}</a> <span class="meta">· {{ $c->agency }} · {{ $c->sourceLabel() }}@if($c->dates_text) · as published: {{ $c->dates_text }}@endif</span></span></li>
            @endforeach
        </ul>
        @endif
        @endforeach
    </section>
    @endif

    <h2 class="section-title mt-10">Application dates in your own calendar</h2>
    <p class="mt-2 max-w-[68ch] text-brand-body leading-7">Subscribe once and dated deadlines from the instruments we track appear in your diary, with reminders 30 and 7 days ahead. The feed updates as records change, so a moved date moves in your calendar rather than in a spreadsheet you forgot to check.</p>

    <div class="mt-6 card-flat p-5">
        <p class="label">Subscription address</p>
        <p class="mt-1 font-mono text-sm break-all text-brand-navy">{{ route('calendar.feed') }}</p>
        <p class="mt-3 flex flex-wrap gap-2"><a href="{{ \App\Services\PolicyData\DeadlineCalendar::subscribeUrl(route('calendar.feed')) }}" class="btn-primary" data-track="calendar_subscribe">Subscribe in your calendar</a><a href="{{ route('calendar.feed') }}" class="btn-secondary" data-track="calendar_download">Download .ics</a><a href="{{ route('deadlines.engine') }}" class="btn-secondary" data-track="deadline_engine_click">Which date applies to you?</a><a href="{{ route('changes.index') }}" class="btn-secondary">See the change log</a></p>
        <p class="mt-3 meta">In Google Calendar choose "Other calendars", then "From URL". In Outlook choose "Add calendar", then "Subscribe from web". In Apple Calendar choose "File", then "New Calendar Subscription".</p>
    </div>

    @if($jurisdictions->isNotEmpty())
    <section class="mt-8" aria-labelledby="by-place">
        <h2 id="by-place" class="section-title">Narrow it to one jurisdiction</h2>
        <ul class="mt-3 grid gap-2 sm:grid-cols-2 text-sm">
            @foreach($jurisdictions as $j)
            <li class="flex justify-between gap-3 border-b border-brand-line py-1.5"><span class="text-brand-body">{{ $j->name }}</span><a href="{{ \App\Services\PolicyData\DeadlineCalendar::subscribeUrl(route('calendar.feed.jurisdiction', $j->slug)) }}" class="text-brand-blue hover:underline" aria-label="Subscribe to {{ $j->name }} dates">subscribe</a></li>
            @endforeach
        </ul>
    </section>
    @endif

    <section class="mt-10" aria-labelledby="in-feed">
        <h2 id="in-feed" class="section-title">What is in the feed</h2>
        <p class="mt-1 text-sm text-brand-body">Only scheduled dates recorded to an exact day. A date we hold as a month, a year or still to be decided is left out rather than guessed into a day, because a calendar entry claims a precision the record does not have. Passed and superseded dates are left out too.</p>
        @if($events->isEmpty())
        <div class="mt-4"><x-site.empty title="No dated deadlines are scheduled right now">As soon as a tracked instrument records an exact application date, it appears here and in the feed.</x-site.empty></div>
        @else
        <p class="mt-3 meta">{{ $events->count() }} {{ \Illuminate\Support\Str::plural('date', $events->count()) }} in the feed.</p>
        @endif
    </section>
    <x-site.disclaimer class="mt-10" />
</div>
@endsection
