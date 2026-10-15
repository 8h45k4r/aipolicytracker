@extends('site.layouts.app')
@section('content')
<div class="container-site py-8 max-w-4xl">
    <x-site.breadcrumbs :items="$seo->breadcrumbs" />
    <p class="eyebrow mt-3">Methodology</p>
    <h1 class="mt-2 text-2xl sm:text-3xl lg:text-4xl font-semibold tracking-tight text-brand-navy">How fast changes reach the record</h1>
    <p class="mt-3 max-w-[70ch] text-brand-body leading-7">A tracker is only as useful as it is current. Our target: a change to an AI law appears here within <strong class="text-brand-navy">{{ $targetDays }} days</strong> of the official text that makes it. This page measures that from the change log itself, so it shows how we are doing rather than how we would like to be doing.</p>

    <section class="mt-8" aria-labelledby="now-h">
        <h2 id="now-h" class="section-title">Since {{ $since->format('j F Y') }}</h2>
        @if($summary['count'] === 0)
        <x-site.empty class="mt-3" title="No changes measured yet">Measurement started on {{ $since->format('j F Y') }}. The first figures appear here once changes are logged after that date.</x-site.empty>
        @else
        <dl class="mt-4 grid gap-3 sm:grid-cols-3">
            <div class="stat"><dt class="stat-label">Median days to log</dt><dd class="stat-value" data-freshness-median>{{ rtrim(rtrim(number_format($summary['median'], 1), '0'), '.') }}</dd><dd class="stat-note">Target: {{ $targetDays }} or fewer</dd></div>
            <div class="stat"><dt class="stat-label">Within target</dt><dd class="stat-value">{{ $summary['within_target'] }}%</dd><dd class="stat-note">of {{ $summary['count'] }} {{ \Illuminate\Support\Str::plural('change', $summary['count']) }}</dd></div>
            <div class="stat"><dt class="stat-label">Slowest 10% took</dt><dd class="stat-value">{{ $summary['p90'] }}</dd><dd class="stat-note">days or more</dd></div>
        </dl>
        @endif
    </section>

    @if($months !== [])
    <section class="mt-10" aria-labelledby="months-h">
        <h2 id="months-h" class="section-title">Month by month</h2>
        <div class="table-wrap mt-3"><table><caption class="sr-only">Changes logged, median days to log, share within target and source checks, by month</caption>
            <thead><tr><th scope="col">Month</th><th scope="col">Changes logged</th><th scope="col">Median days</th><th scope="col">Within target</th><th scope="col">Official sources checked</th></tr></thead>
            <tbody>@foreach($months as $m)<tr><td>{{ $m['month'] }}</td><td>{{ $m['count'] }}</td><td>{{ $m['median'] === null ? '—' : rtrim(rtrim(number_format($m['median'], 1), '0'), '.') }}</td><td>{{ $m['within_target'] === null ? '—' : $m['within_target'].'%' }}</td><td>{{ $m['checks'] }}</td></tr>@endforeach</tbody>
        </table></div>
    </section>
    @endif

    @if($latest->isNotEmpty())
    <section class="mt-10" aria-labelledby="latest-h">
        <h2 id="latest-h" class="section-title">Latest changes and their lag</h2>
        <div class="table-wrap mt-3"><table><caption class="sr-only">Latest changes: official date, date logged here and days between</caption>
            <thead><tr><th scope="col">Change</th><th scope="col">Official date</th><th scope="col">Logged here</th><th scope="col">Days</th></tr></thead>
            <tbody>@foreach($latest as $r)<tr><td><a href="{{ $r['change']->url() }}">{{ $r['change']->title }}</a></td><td class="whitespace-nowrap">{{ $r['official']->format('j M Y') }}</td><td class="whitespace-nowrap">{{ $r['change']->first_published_at->format('j M Y') }}</td><td class="{{ $r['lag'] > $targetDays ? 'text-state-bad' : 'text-state-good' }}">{{ $r['lag'] }}</td></tr>@endforeach</tbody>
        </table></div>
    </section>
    @endif

    <section class="mt-10" aria-labelledby="how-h">
        <h2 id="how-h" class="section-title">How this is measured</h2>
        <ul class="mt-3 space-y-2 text-sm text-brand-body leading-7 list-disc pl-5">
            <li><strong class="text-brand-navy">Days to log</strong> runs from the date of the official text (the source document's date, or the date of the event where there is none) to the day the change first appeared on this site.</li>
            <li>A change that happened before it could be found, such as a law published without notice, counts against us all the same. The figure is not adjusted.</li>
            <li>Measurement started on {{ $since->format('j F Y') }}. Earlier first-appearance dates come from a bulk import, so they describe the import, not the desk.</li>
            <li>The change desk looks at the official source of every binding instrument that is proposed, adopted or applying every {{ $cadence['binding'] }} days, and every other current instrument every {{ $cadence['other'] }} days. Each record shows when its source was last checked and what was found.</li>
        </ul>
        <p class="mt-4 text-sm"><a href="{{ route('methodology') }}">Methodology</a> · <a href="{{ route('changes.index') }}">Change log</a> · <a href="{{ route('corrections') }}">Corrections</a></p>
    </section>
    <x-site.disclaimer class="mt-10" />
</div>
@endsection
