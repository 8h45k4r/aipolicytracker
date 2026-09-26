@extends('site.layouts.app')
@section('content')
<div class="container-site py-8 max-w-3xl">
    <x-site.breadcrumbs :items="$seo->breadcrumbs" />
    <p class="eyebrow mt-3">Deadline calendar</p>
    <h1 class="mt-2 text-2xl sm:text-3xl lg:text-4xl font-semibold tracking-tight text-brand-navy">Application dates in your own calendar</h1>
    <p class="mt-3 max-w-[68ch] text-brand-body leading-7">Subscribe once and dated deadlines from the instruments we track appear in your diary, with reminders 30 and 7 days ahead. The feed updates as records change, so a moved date moves in your calendar rather than in a spreadsheet you forgot to check.</p>

    <div class="mt-6 card-flat p-5">
        <p class="label">Subscription address</p>
        <p class="mt-1 font-mono text-sm break-all text-brand-navy">{{ route('calendar.feed') }}</p>
        <p class="mt-3 flex flex-wrap gap-2"><a href="{{ route('calendar.feed') }}" class="btn-primary" data-track="calendar_subscribe">Download or subscribe</a><a href="{{ route('deadlines.engine') }}" class="btn-secondary" data-track="deadline_engine_click">Which date applies to you?</a><a href="{{ route('changes.index') }}" class="btn-secondary">See the change log</a></p>
        <p class="mt-3 meta">In Google Calendar choose "Other calendars", then "From URL". In Outlook choose "Add calendar", then "Subscribe from web". In Apple Calendar choose "File", then "New Calendar Subscription".</p>
    </div>

    @if($jurisdictions->isNotEmpty())
    <section class="mt-8" aria-labelledby="by-place">
        <h2 id="by-place" class="section-title">Narrow it to one jurisdiction</h2>
        <ul class="mt-3 grid gap-2 sm:grid-cols-2 text-sm">
            @foreach($jurisdictions as $j)
            <li class="flex justify-between gap-3 border-b border-brand-line py-1.5"><span class="text-brand-body">{{ $j->name }}</span><a href="{{ route('calendar.feed.jurisdiction', $j->slug) }}" class="text-brand-blue hover:underline">subscribe</a></li>
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
        <ul class="mt-3 divide-y divide-brand-line border-y border-brand-line text-sm">
            @foreach($events as $d)
            <li class="flex flex-wrap items-start justify-between gap-3 py-2">
                <span><a href="{{ $d->policyInstrument->url() }}" class="text-brand-navy hover:underline">{{ $d->title }}</a>
                    <span class="meta">· {{ $d->policyInstrument->jurisdiction->name }} · {{ $d->policyInstrument->short_title ?: $d->policyInstrument->title }}</span></span>
                <span class="font-mono text-xs text-brand-navy whitespace-nowrap">{{ $d->due_on->format('j M Y') }}</span>
            </li>
            @endforeach
        </ul>
        <p class="mt-3 meta">{{ $events->count() }} {{ \Illuminate\Support\Str::plural('date', $events->count()) }} in the feed.</p>
        @endif
    </section>
    <x-site.disclaimer class="mt-10" />
</div>
@endsection
